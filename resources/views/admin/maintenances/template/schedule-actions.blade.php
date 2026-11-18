<div class="d-flex justify-content-center" style="gap:6px">
    <button type="button" class="btn btn-sm btn-primary btnEditarHorario" data-url="{{ route('admin.maintenances.schedules.edit', $schedule) }}" title="Editar horario"><i class="bi bi-pencil-square"></i></button>
    <form action="{{ route('admin.maintenances.schedules.destroy', $schedule) }}" method="POST" class="m-0 frmEliminarHorario">@csrf @method('DELETE')
        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar horario"><i class="bi bi-trash3-fill"></i></button>
    </form>
</div>
