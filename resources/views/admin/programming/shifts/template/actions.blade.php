<div class="d-flex justify-content-center gap-1">
    <button type="button" class="btn btn-sm btn-outline-primary btnEditar" data-id="{{ $shift->id }}" title="Editar turno" aria-label="Editar turno">
        <i class="bi bi-pencil-square"></i>
    </button>
    <form action="{{ route('admin.programming.shifts.destroy', $shift) }}" method="POST" class="frmEliminar">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar turno" aria-label="Eliminar turno">
            <i class="bi bi-trash"></i>
        </button>
    </form>
</div>
