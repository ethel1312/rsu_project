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
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'shift_id' => ['nullable', 'integer', 'exists:shifts,id']]);
        $date = $data['date'] ?? now('America/Lima')->toDateString();
        $schedules = ProgrammingSchedule::with('runs')->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)
            ->when($data['shift_id'] ?? null, fn ($q, $id) => $q->where('shift_id', $id))->get()->filter(fn ($s) => $s->runsOn($date));
        $cards = $schedules->map(function ($schedule) use ($date, $service) {
            $ids = array_merge([$schedule->driver_id], $schedule->helper_ids);
            $missing = array_values(array_filter($ids, fn ($id) => ! $service->attended((int) $id, $date)));
            $run = $schedule->runs->first(fn ($r) => $r->service_date->toDateString() === $date);

            return compact('schedule', 'missing', 'run') + ['present' => count($ids) - count($missing)];
        });
        $complete = $cards->filter(fn ($c) => $c['missing'] === [])->count();
        $missingCount = $cards->flatMap(fn ($c) => $c['missing'])->unique()->count();
        $changes = ScheduleChange::with('actor')->whereDate('service_date', $date)->whereIn('programming_schedule_id', $schedules->modelKeys())->latest('id')->get();

        return view('admin.programming.dashboard.index', compact('cards', 'complete', 'missingCount', 'date', 'changes') + ['shifts' => Shift::orderBy('name')->get()]);
    }

    public function show(Request $request, ProgrammingSchedule $schedule, ScheduleChangeService $service)
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];
        abort_unless($schedule->runsOn($date), 404);
        $editable = true;
        try {
            $service->assertEditable($schedule, $date);
        } catch (ValidationException $e) {
            $editable = false;
        }
        $editable = $editable && $date === now('America/Lima')->toDateString();
        $crew = collect(array_merge([$schedule->driver_id], $schedule->helper_ids))->map(fn ($id) => ['employee' => Employee::find($id), 'id' => $id, 'present' => $service->attended((int) $id, $date), 'role' => (int) $id === (int) $schedule->driver_id ? 'driver' : 'helper']);
        $candidates = [];
        if ($editable) {
            foreach ($crew->where('present', false) as $member) {
                foreach (Employee::where('status', true)->whereHas('employeeType', fn ($q) => $q->where('name', $member['role'] === 'driver' ? 'Conductor' : 'Ayudante'))->get() as $employee) {
                    if (! $service->attended($employee->id, $date)) {
                        continue;
                    }
                    try {
                        $candidate = clone $schedule;
                        $candidate->fill($service->replacement($schedule, $member['role'], $member['id'], $employee->id));
                        $service->validateCandidate($schedule, $candidate, $date, 'dashboard');
                        $candidates[$member['id']][] = $employee;
                    } catch (ValidationException $e) { /* Not available for this specific slot. */
                    }
                }
            }
        }

        return view('admin.programming.dashboard.show', array_merge(app(ScheduleChangeController::class)->catalog(), compact('schedule', 'date', 'crew', 'editable', 'candidates')));
    }

    public function update(Request $request, ProgrammingSchedule $schedule, ScheduleChangeService $service)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'], 'type' => ['required', Rule::in(array_keys(ScheduleChange::TYPES))],
            'old_id' => ['required', 'integer'], 'new_id' => ['required', 'integer'],
            'reason_id' => ['required', 'integer', Rule::exists('change_reasons', 'id')->whereNull('deleted_at')],
            'detail' => ['nullable', 'string', 'max:1000'],
        ]);
        $changed = DB::transaction(function () use ($schedule, $data, $service) {
            $service->lock();

            return $service->applyDay(ProgrammingSchedule::lockForUpdate()->findOrFail($schedule->id), $data['date'], [$data], 'dashboard');
        }, 3);

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
                if (! $service->attended((int) $id, $date)) {
                    throw ValidationException::withMessages(['attendance' => 'Todos los integrantes deben registrar asistencia antes de iniciar.']);
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
