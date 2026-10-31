<?php

namespace App\Http\Controllers\admin\Programming;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Programming\ChangeReason;
use App\Models\Programming\ProgrammingSchedule;
use App\Models\Programming\ScheduleChange;
use App\Models\Programming\Shift;
use App\Models\Programming\Zone;
use App\Models\Vehicle;
use App\Services\Programming\ScheduleChangeService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduleChangeController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'], 'type' => ['nullable', Rule::in(array_keys(ScheduleChange::TYPES))]]);
        $changes = ScheduleChange::with(['schedule', 'actor'])
            ->when($data['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($data['type'] ?? null, fn ($q, $type) => $q->where('type', $type))->latest('id')->paginate(20)->withQueryString();

        return view('admin.programming.changes.index', compact('changes'));
    }

    public function create()
    {
        return view('admin.programming.changes.create', $this->catalog());
    }

    public function catalog(): array
    {
        return ['zones' => Zone::orderBy('name')->get(), 'shifts' => Shift::orderBy('name')->get(),
            'vehicles' => Vehicle::orderBy('plate')->get(), 'reasons' => ChangeReason::orderBy('name')->get(),
            'employees' => Employee::with('employeeType')->where('status', true)->orderBy('last_name')->get()];
    }

    public function preview(Request $request, ScheduleChangeService $service)
    {
        $data = $this->data($request);
        $targets = $this->targets($data);
        // Validate the complete resulting assignment, including conflicts between selected days.
        $preview = DB::transaction(function () use ($service, $targets, $data) {
            $service->lock();
            DB::beginTransaction();
            try {
                $rows = [];
                foreach ($targets as [$id, $date]) {
                    $schedule = ProgrammingSchedule::findOrFail($id);
                    $before = $service->snapshot($schedule);
                    $changed = $service->applyDay($schedule, $date, [$data], 'mass');
                    $rows[] = ['schedule_id' => $id, 'date' => $date, 'zone' => $changed->zone_name, 'before' => $before, 'after' => $service->snapshot($changed)];
                }

                return $rows;
            } finally {
                DB::rollBack();
            }
        });

        return response()->json(['rows' => $preview, 'count' => count($preview), 'token' => $this->token($data, $targets)]);
    }

    public function store(Request $request, ScheduleChangeService $service)
    {
        $data = $this->data($request);
        $request->validate(['confirmed' => ['accepted'], 'preview_token' => ['required', 'string']]);
        $count = DB::transaction(function () use ($service, $data, $request) {
            $service->lock();
            $targets = $this->targets($data);
            if (! hash_equals($this->token($data, $targets), $request->input('preview_token'))) {
                throw ValidationException::withMessages(['preview' => 'Las programaciones cambiaron. Revise nuevamente la vista previa.']);
            }
            $operation = (string) Str::uuid();
            foreach ($targets as [$id, $date]) {
                $service->applyDay(ProgrammingSchedule::lockForUpdate()->findOrFail($id), $date, [$data], 'mass', $operation);
            }

            return count($targets);
        }, 3);

        return response()->json(['message' => 'Cambio masivo aplicado a '.$count.' programaciones. Cada una conserva su historial.', 'redirect' => route('admin.programming.changes.index')]);
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('America/Lima')->toDateString()],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:'.now('America/Lima')->addYear()->toDateString()],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'type' => ['required', Rule::in(array_keys(ScheduleChange::TYPES))],
            'old_id' => ['required', 'integer'], 'new_id' => ['required', 'integer', 'different:old_id'],
            'reason_id' => ['required', 'integer', Rule::exists('change_reasons', 'id')->whereNull('deleted_at')],
            'detail' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function targets(array $data): array
    {
        $targets = [];
        $schedules = ProgrammingSchedule::whereIn('status', ['Programada', 'Reprogramado'])
            ->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])
            ->when($data['zone_id'] ?? null, fn ($q, $id) => $q->where('zone_id', $id))->orderBy('id')->get();
        foreach ($schedules as $schedule) {
            $matches = $data['type'] === 'helper' ? in_array((int) $data['old_id'], array_map('intval', $schedule->helper_ids), true) : (int) $schedule->{$data['type'].'_id'} === (int) $data['old_id'];
            if (! $matches) {
                continue;
            }
            foreach (CarbonPeriod::create(max($data['start_date'], $schedule->start_date->toDateString()), min($data['end_date'], $schedule->end_date->toDateString())) as $date) {
                if ($schedule->runsOn($date->toDateString())) {
                    $targets[] = [$schedule->id, $date->toDateString()];
                }
            }
        }
        if ($targets === []) {
            throw ValidationException::withMessages(['range' => 'No hay programaciones que coincidan con los filtros y el valor anterior.']);
        }
        if (count($targets) > 1000) {
            throw ValidationException::withMessages(['range' => 'Seleccione un rango menor (máximo 1000 programaciones por operación).']);
        }

        return $targets;
    }

    private function token(array $data, array $targets): string
    {
        $state = ProgrammingSchedule::whereIn('id', array_column($targets, 0))->orderBy('id')->get()->toJson();

        return hash_hmac('sha256', auth()->id().'|'.json_encode($data).'|'.json_encode($targets).'|'.$state, config('app.key'));
    }

    public function show(ScheduleChange $change)
    {
        $change->load(['schedule', 'actor', 'reverter']);

        return view('admin.programming.changes.show', compact('change'));
    }

    public function destroy(ScheduleChange $change, ScheduleChangeService $service)
    {
        $service->revert($change);

        return back()->with('success', 'Cambio revertido. Se restauró el valor anterior y se registró la reversión en el historial.');
    }
}
