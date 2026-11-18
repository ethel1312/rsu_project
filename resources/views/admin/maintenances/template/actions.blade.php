<div class="d-inline-flex justify-content-center align-items-center flex-nowrap" style="gap:6px;white-space:nowrap">
    <a class="btn btn-sm btn-info text-white" href="{{ route('admin.maintenances.schedules', $maintenance) }}" title="Ver horarios"><i class="bi bi-calendar-week me-1"></i>Ver horarios</a>
    <button type="button" class="btn btn-sm btn-primary btnEditarMantenimiento" data-url="{{ route('admin.maintenances.edit', $maintenance) }}" title="Editar mantenimiento"><i class="bi bi-pencil-square"></i></button>
    <form action="{{ route('admin.maintenances.destroy', $maintenance) }}" method="POST" class="m-0 d-inline-flex frmEliminarMantenimiento">@csrf @method('DELETE')
        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar mantenimiento"><i class="bi bi-trash3-fill"></i></button>
    </form>
</div>
