<div class="d-flex justify-content-center gap-1">
    <button type="button" class="btn btn-sm btn-warning text-white btnEditarMotivo" data-id="{{ $reason->id }}" title="Editar motivo" aria-label="Editar motivo">
        <i class="bi bi-pencil-square"></i>
    </button>
    <form action="{{ route('admin.programming.reasons.destroy', $reason) }}" method="POST" class="frmEliminarMotivo reason-action-form">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar motivo" aria-label="Eliminar motivo">
            <i class="bi bi-trash3-fill"></i>
        </button>
    </form>
</div>
