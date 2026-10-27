<div class="d-flex justify-content-center gap-1">
    <a href="{{ route('admin.programming.schedules.groups.details', $group) }}" class="btn btn-sm btn-outline-info" title="Ver detalle e historial" aria-label="Ver detalle e historial"><i class="bi bi-info-circle"></i></a>
    <button type="button" class="btn btn-sm btn-outline-primary btnEditar" data-id="{{ $group->id }}" title="Editar grupo" aria-label="Editar grupo">
        <i class="bi bi-pencil-square"></i>
    </button>
    <form action="{{ route('admin.programming.personnel-groups.destroy', $group) }}" method="POST" class="frmEliminar">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar grupo" aria-label="Eliminar grupo">
            <i class="bi bi-trash3-fill"></i>
        </button>
    </form>
</div>
