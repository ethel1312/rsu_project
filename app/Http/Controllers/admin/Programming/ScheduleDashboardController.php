<?php

namespace App\Http\Controllers\admin\Programming;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Programming\ProgrammingSchedule;
use App\Models\Programming\ScheduleChange;
use App\Models\Programming\Shift;
use App\Models\Programming\Zone;
use App\Services\Programming\ScheduleChangeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduleDashboardController extends Controller
{
    public function index(Request $request, ScheduleChangeService $service)
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'shift_id' => ['nullable', 'integer', 'exists:shifts,id'], 'filter' => ['nullable', Rule::in(['all', 'complete', 'incomplete', 'missing'])]]);
        $date = $data['date'] ?? now('America/Lima')->toDateString();
        $schedules = ProgrammingSchedule::with('runs')->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)
            ->when($data['shift_id'] ?? null, fn ($q, $id) => $q->where('shift_id', $id))->get()->filter(fn ($s) => $s->runsOn($date));
        $cards = $schedules->map(function ($schedule) use ($date, $service) {
            $ids = array_merge([$schedule->driver_id], $schedule->helper_ids);
            $missing = array_values(array_filter($ids, fn ($id) => ! $service->attended((int) $id, $date, (int) $schedule->shift_id)));
            $run = $schedule->runs->first(fn ($r) => $r->service_date->toDateString() === $date);

            return compact('schedule', 'missing', 'run') + ['present' => count($ids) - count($missing)];
        });
        $total = $cards->count();
        $filter = $data['filter'] ?? 'all';
        $missingPersonnel = $cards->flatMap(fn ($card) => collect($card['missing'])->map(fn ($id) => ['id' => $id, 'shift' => $card['schedule']->shift_name, 'group' => $card['schedule']->group_name]))->groupBy('id');
        $missingEmployees = Employee::whereIn('id', $missingPersonnel->keys())->get()->keyBy('id');
        $complete = $cards->filter(fn ($c) => $c['missing'] === [])->count();
        $missingCount = $cards->flatMap(fn ($c) => $c['missing'])->unique()->count();
        $changes = ScheduleChange::with('actor')->whereDate('service_date', $date)->whereIn('programming_schedule_id', $schedules->modelKeys())->latest('id')->get();

        $cards = $cards->filter(fn ($card) => match ($filter) {
            'complete' => $card['missing'] === [],
            'incomplete', 'missing' => $card['missing'] !== [],
            default => true,
        });
        return view('admin.programming.dashboard.index', compact('cards', 'total', 'filter', 'missingPersonnel', 'missingEmployees', 'complete', 'missingCount', 'date', 'changes') + ['shifts' => Shift::orderBy('name')->get()]);
    }

    public function show(Request $request, ProgrammingSchedule $schedule, ScheduleChangeService $service)
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];
        abort_unless($schedule->runsOn($date), 404);
        $editable = $date === now('America/Lima')->toDateString();
        $editBlockReason = $editable ? null : 'Desde el dashboard solo se puede editar la programación del día de hoy.';
        if ($editable) {
            try {
                $service->assertEditable($schedule, $date);
            } catch (ValidationException $e) {
                $editable = false;
                $editBlockReason = collect($e->errors())->flatten()->first() ?? 'La programación no está disponible para cambios.';
            }
        }
        $crew = collect(array_merge([$schedule->driver_id], $schedule->helper_ids))->map(fn ($id) => ['employee' => Employee::find($id), 'id' => $id, 'present' => $service->attended((int) $id, $date, (int) $schedule->shift_id), 'role' => (int) $id === (int) $schedule->driver_id ? 'driver' : 'helper']);
        $targetShift = $request->validate(['candidate_shift_id' => ['nullable', 'integer', 'exists:shifts,id']])['candidate_shift_id'] ?? $schedule->shift_id;
        $candidateBase = clone $schedule;
        if ((int) $targetShift !== (int) $schedule->shift_id) {
            $candidateBase->fill($service->replacement($schedule, 'shift', $schedule->shift_id, (int) $targetShift));
        }
        $candidates = [];
        if ($editable) {
            foreach ($crew->where('present', false) as $member) {
                foreach (Employee::where('status', true)->whereHas('employeeType', fn ($q) => $q->where('name', $member['role'] === 'driver' ? 'Conductor' : 'Ayudante'))->get() as $employee) {
                    if (! $service->attended($employee->id, $date, (int) $targetShift)) {
                        continue;
                    }
                    try {
                        $candidate = clone $candidateBase;
                        $candidate->fill($service->replacement($candidateBase, $member['role'], $member['id'], $employee->id));
                        $service->validateCandidate($schedule, $candidate, $date, 'dashboard');
                        $candidates[$member['id']][] = $employee;
                    } catch (ValidationException $e) { /* Not available for this specific slot. */
                    }
                }
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['candidates' => collect($candidates)->map(fn ($items) => collect($items)->map(fn ($employee) => ['id' => $employee->id, 'label' => $employee->full_name.' · DNI '.$employee->dni]))]);
        }
        $data = array_merge(app(ScheduleChangeController::class)->catalog(), compact('schedule', 'date', 'crew', 'editable', 'editBlockReason', 'candidates'));

        if ($request->ajax()) {
            return view('admin.programming.dashboard.modal-detail', $data);
        }

        return view('admin.programming.dashboard.show', $data);
    }

    public function update(Request $request, ProgrammingSchedule $schedule, ScheduleChangeService $service)
    {
        $rules = [
            'date' => ['required', 'date_format:Y-m-d'],
            'reason_id' => ['required', 'integer', Rule::exists('change_reasons', 'id')->whereNull('deleted_at')],
            'detail' => ['nullable', 'string', 'max:1000'],
        ];
        if ($request->has('changes')) {
            $rules += [
                'changes' => ['required', 'array', 'min:1'],
                'changes.*.type' => ['required', Rule::in(array_keys(ScheduleChange::TYPES))],
                'changes.*.old_id' => ['required', 'integer'],
                'changes.*.new_id' => ['required', 'integer'],
            ];
        } else {
            // Keep the standalone detail page's single-change form compatible.
            $rules += [
                'type' => ['required', Rule::in(array_keys(ScheduleChange::TYPES))],
                'old_id' => ['required', 'integer'], 'new_id' => ['required', 'integer'],
            ];
        }
        $data = $request->validate($rules);
        $edits = isset($data['changes'])
            ? array_map(fn ($change) => $change + ['reason_id' => $data['reason_id'], 'detail' => $data['detail'] ?? ''], $data['changes'])
            : [array_intersect_key($data, array_flip(['type', 'old_id', 'new_id', 'reason_id', 'detail']))];
        $changed = DB::transaction(function () use ($schedule, $data, $edits, $service) {
            $service->lock();

            return $service->applyDay(ProgrammingSchedule::lockForUpdate()->findOrFail($schedule->id), $data['date'], $edits, 'dashboard');
        }, 3);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Los reemplazos se aplicaron y quedaron registrados en el historial.',
                'redirect' => route('admin.programming.dashboard.index', ['date' => $data['date']]),
            ]);
        }

        return redirect()->route('admin.programming.dashboard.show', ['schedule' => $changed, 'date' => $data['date']])->with('success', 'Cambio aplicado al día y guardado en el historial.');
    }

    public function route(Request $request, ProgrammingSchedule $schedule)
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];
        abort_unless($schedule->runsOn($date), 404);
        $run = $schedule->runs()->whereDate('service_date', $date)->first();
        $zone = Zone::find($schedule->zone_id);

        return view('admin.programming.dashboard.route', compact('schedule', 'date', 'run', 'zone'));
    }

    public function start(Request $request, ProgrammingSchedule $schedule, ScheduleChangeService $service)
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];
        DB::transaction(function () use ($schedule, $date, $service) {
            $service->lock();
            $schedule = ProgrammingSchedule::lockForUpdate()->findOrFail($schedule->id);
            if (! $schedule->runsOn($date) || $date !== now('America/Lima')->toDateString() || $schedule->status === 'Finalizada') {
                abort(422, 'Solo puede iniciar un recorrido programado para hoy.');
            }
            if ($schedule->runs()->whereDate('service_date', $date)->exists()) {
                abort(422, 'El recorrido ya fue iniciado.');
            }
            foreach (array_merge([$schedule->driver_id], $schedule->helper_ids) as $id) {
                if (! $service->attended((int) $id, $date, (int) $schedule->shift_id)) {
                    throw ValidationException::withMessages(['attendance' => 'Todos los integrantes deben registrar asistencia en este día y turno antes de iniciar.']);
                }
            }
            $schedule->runs()->create(['service_date' => $date, 'started_at' => now(), 'recorded_by' => auth()->id()]);
            $service->history($schedule, 'started', null, ['fecha' => $date, 'estado' => 'En curso'], 'Recorrido iniciado para '.$date);
        }, 3);

        return back()->with('success', 'Recorrido iniciado. Registre el recorrido seguido antes de finalizar.');
    }

    public function finish(Request $request, ProgrammingSchedule $schedule, ScheduleChangeService $service)
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'route_points' => ['required', 'json']]);
        $points = json_decode($data['route_points'], true);
        validator(['points' => $points], ['points' => ['required', 'array', 'min:2', 'max:5000'], 'points.*' => ['required', 'array', 'size:2'], 'points.*.0' => ['required', 'numeric', 'between:-90,90'], 'points.*.1' => ['required', 'numeric', 'between:-180,180']])->validate();
        DB::transaction(function () use ($schedule, $data, $points, $service) {
            $service->lock();
            $schedule = ProgrammingSchedule::lockForUpdate()->findOrFail($schedule->id);
            abort_unless($schedule->runsOn($data['date']), 422);
            $run = $schedule->runs()->whereDate('service_date', $data['date'])->lockForUpdate()->first();
            if (! $run || ! $run->started_at || $run->finished_at) {
                throw ValidationException::withMessages(['run' => 'Debe iniciar el recorrido antes de finalizarlo; no puede finalizarlo dos veces.']);
            }
            $run->update(['finished_at' => now(), 'route_points' => $points, 'recorded_by' => auth()->id()]);
            $service->history($schedule, 'finished', ['fecha' => $data['date'], 'estado' => 'En curso'], ['fecha' => $data['date'], 'estado' => 'Finalizada'], 'Recorrido finalizado. Trazado declarado por el usuario; '.$data['date']);
        }, 3);

        return redirect()->route('admin.programming.schedules.route', ['schedule' => $schedule, 'date' => $data['date']])->with('success', 'Recorrido finalizado y guardado.');
    }
}
