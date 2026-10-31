<div class="d-flex justify-content-center flex-nowrap schedule-actions-row" style="gap:6px">
    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.programming.schedules.route', ['schedule'=>$schedule,'date'=>$serviceDate]) }}" title="{{ $run?->finished_at ? 'Ver recorrido seguido' : 'Registrar recorrido' }}"><i class="bi bi-map"></i> {{ $run?->finished_at ? 'Ver recorrido' : 'Recorrido' }}</a>
    <button type="button" class="btn btn-sm schedule-action schedule-action-history btnScheduleHistory"
            data-url="{{ route('admin.programming.schedules.history', $schedule) }}"
            data-title="{{ $schedule->group_name }} · {{ \Carbon\Carbon::parse($serviceDate)->format('d/m/Y') }}"
            title="Ver historial" aria-label="Ver historial">
        <i class="bi bi-clock-history"></i>
    </button>
    @if (in_array($schedule->status, ['Programada', 'Reprogramado'], true) && !$run?->started_at && \Carbon\Carbon::parse($serviceDate.' '.$schedule->shift_start_time, 'America/Lima')->isFuture())
        <button type="button" class="btn btn-sm schedule-action schedule-action-edit btnEditSchedule"
                data-url="{{ route('admin.programming.schedules.edit', $schedule) }}?date={{ $serviceDate }}"
                title="Modificar este día" aria-label="Modificar este día">
            <i class="bi bi-pencil-square"></i>
        </button>
        <form action="{{ route('admin.programming.schedules.destroy', $schedule) }}" method="POST" class="d-inline frmDeleteSchedule">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm schedule-action schedule-action-delete" title="Eliminar" aria-label="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </form>
    @endif
</div>
