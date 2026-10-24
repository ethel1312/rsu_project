<div class="d-flex justify-content-center flex-wrap" style="gap:4px">
    <button type="button" class="btn btn-sm btn-outline-info btnScheduleHistory"
            data-url="{{ route('admin.programming.schedules.history', $schedule) }}"
            title="Ver historial" aria-label="Ver historial">
        <i class="bi bi-clock-history"></i>
    </button>
    @if ($schedule->status === 'Programada')
        <button type="button" class="btn btn-sm btn-outline-primary btnEditSchedule"
                data-url="{{ route('admin.programming.schedules.edit', $schedule) }}"
                title="Editar" aria-label="Editar">
            <i class="bi bi-pencil-square"></i>
        </button>
        <button type="button" class="btn btn-sm btn-outline-success btnFinishSchedule"
                data-url="{{ route('admin.programming.schedules.finish', $schedule) }}"
                title="Finalizar" aria-label="Finalizar">
            <i class="bi bi-check2-circle"></i>
        </button>
        <form action="{{ route('admin.programming.schedules.destroy', $schedule) }}" method="POST" class="d-inline frmDeleteSchedule">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar" aria-label="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </form>
    @endif
</div>
