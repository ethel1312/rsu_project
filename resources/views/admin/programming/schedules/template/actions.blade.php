<div class="d-flex justify-content-center flex-nowrap schedule-actions-row" style="gap:6px">
    <button type="button" class="btn btn-sm schedule-action schedule-action-history btnScheduleHistory"
            data-url="{{ route('admin.programming.schedules.history', $schedule) }}"
            title="Ver historial" aria-label="Ver historial">
        <i class="bi bi-clock-history"></i>
    </button>
    @if (in_array($schedule->status, ['Programada', 'Reprogramado'], true))
        <button type="button" class="btn btn-sm schedule-action schedule-action-edit btnEditSchedule"
                data-url="{{ route('admin.programming.schedules.edit', $schedule) }}?date={{ $serviceDate }}"
                title="Modificar este día" aria-label="Modificar este día">
            <i class="bi bi-pencil-square"></i>
        </button>
        <button type="button" class="btn btn-sm schedule-action schedule-action-finish btnFinishSchedule"
                data-url="{{ route('admin.programming.schedules.finish', $schedule) }}"
                title="Finalizar" aria-label="Finalizar">
            <i class="bi bi-check2-circle"></i>
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
