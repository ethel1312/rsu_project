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
use Yajra\DataTables\Facades\DataTables;

class ScheduleChangeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $filters = $request->validate([
                'date_from' => ['nullable', 'date_format:Y-m-d'],
                'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
                'type' => ['nullable', Rule::in(array_keys(ScheduleChange::TYPES))],
            ]);
            $changes = ScheduleChange::with(['schedule', 'actor'])
                ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
                ->latest('id');

            return DataTables::of($changes)
                ->addColumn('change_type', fn ($change) => $this->typeDisplay($change))
                ->addColumn('change_date', fn ($change) => $this->dateDisplay($change))
                ->addColumn('before_display', fn ($change) => $this->valueDisplay($change, $change->before_data ?? []))
                ->addColumn('after_display', fn ($change) => $this->valueDisplay($change, $change->after_data ?? []))
                ->addColumn('actor_display', fn ($change) => $this->actorDisplay($change))
                ->addColumn('actions', fn ($change) => view('admin.programming.changes.template.actions', compact('change'))->render())
                ->rawColumns(['change_type', 'change_date', 'before_display', 'after_display', 'actor_display', 'actions'])
                ->make(true);
        }

        return view('admin.programming.changes.index');
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
            'zone_ids' => ['nullable', 'array', 'max:100'],
            'zone_ids.*' => ['integer', 'distinct', 'exists:zones,id'],
            'type' => ['required', Rule::in(array_keys(ScheduleChange::TYPES))],
            'old_id' => ['required', 'integer'], 'new_id' => ['required', 'integer', 'different:old_id'],
            'reason_id' => ['required', 'integer', Rule::exists('change_reasons', 'id')->whereNull('deleted_at')],
            'detail' => ['required', 'string', 'max:2000'],
        ]);
    }

    private function targets(array $data): array
    {
        $targets = [];
        $zoneIds = array_values(array_unique(array_map('intval', array_filter(array_merge(
            $data['zone_ids'] ?? [],
            isset($data['zone_id']) ? [$data['zone_id']] : [],
        )))));
        $schedules = ProgrammingSchedule::whereIn('status', ['Programada', 'Reprogramado'])
            ->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])
            ->when($zoneIds !== [], fn ($query) => $query->whereIn('zone_id', $zoneIds))
            ->orderBy('id')->get();
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

    public function show(Request $request, ScheduleChange $change)
    {
        $change->load(['schedule', 'actor', 'reverter']);

        if ($request->ajax()) {
            return view('admin.programming.changes.detail-modal', compact('change'));
        }

        return view('admin.programming.changes.show', compact('change'));
    }

    public function destroy(ScheduleChange $change, ScheduleChangeService $service)
    {
        $service->revert($change);

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Cambio revertido. Se restauró el valor anterior y se registró la reversión en el historial.']);
        }

        return back()->with('success', 'Cambio revertido. Se restauró el valor anterior y se registró la reversión en el historial.');
    }

    private function typeDisplay(ScheduleChange $change): string
    {
        $appearance = [
            'shift' => ['warning', 'bi-clock-fill'],
            'vehicle' => ['primary', 'bi-truck'],
            'driver' => ['info', 'bi-person-fill'],
            'helper' => ['success', 'bi-people-fill'],
        ][$change->type] ?? ['secondary', 'bi-arrow-left-right'];
        $label = e(ScheduleChange::TYPES[$change->type] ?? $change->type);
        $source = e(['mass' => 'Masivo', 'individual' => 'Individual', 'dashboard' => 'Dashboard'][$change->source] ?? $change->source);
        $reverted = $change->reverted_at ? '<span class="badge bg-secondary ml-1">Revertido</span>' : '';

        return '<span class="badge bg-'.$appearance[0].'"><i class="bi '.$appearance[1].' me-1"></i>'.$label.'</span>'.$reverted.'<div class="small text-muted mt-1">'.$source.'</div>';
    }

    private function dateDisplay(ScheduleChange $change): string
    {
        $created = $change->created_at?->timezone('America/Lima');
        $schedule = $change->schedule;
        $context = trim(collect(['#'.$change->programming_schedule_id, $schedule?->zone_name, 'Servicio '.$change->service_date?->format('d/m/Y')])->filter()->implode(' · '));

        return '<div class="change-date"><strong><i class="bi bi-calendar3 me-1"></i>'.e($created?->format('d/m/Y') ?? '—').'</strong><div class="small text-muted"><i class="bi bi-clock me-1"></i>'.e($created?->format('H:i') ?? '').'</div><div class="small text-muted">'.e($context).'</div></div>';
    }

    private function valueDisplay(ScheduleChange $change, array $values): string
    {
        $main = match ($change->type) {
            'shift' => $values['shift_name'] ?? '—',
            'vehicle' => $values['vehicle_label'] ?? '—',
            'driver' => $values['driver_name'] ?? '—',
            'helper' => implode(', ', $values['helper_names'] ?? []),
            default => '—',
        };
        $detail = $change->type === 'shift'
            ? trim(substr((string) ($values['shift_start_time'] ?? ''), 0, 5).'–'.substr((string) ($values['shift_end_time'] ?? ''), 0, 5), '–')
            : '';
        $marker = $change->type === 'shift' ? 'bi-clock-fill' : ($change->type === 'vehicle' ? 'bi-truck' : 'bi-person-fill');

        return '<div class="change-value"><strong><i class="bi '.$marker.' me-1"></i>'.e($main ?: '—').'</strong>'.($detail ? '<small class="d-block text-muted">'.e($detail).'</small>' : '').'</div>';
    }

    private function actorDisplay(ScheduleChange $change): string
    {
        $name = e($change->actor?->name ?? 'Usuario eliminado');
        $email = $change->actor?->email ? '<small class="d-block text-muted">'.e($change->actor->email).'</small>' : '';

        return '<div class="change-actor"><strong><i class="bi bi-person-fill me-1"></i>'.$name.'</strong>'.$email.'</div>';
    }
}
