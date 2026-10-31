<div class="d-flex justify-content-center align-items-center gap-1">
    <button type="button" class="btn btn-sm btn-primary btnVerDetalleCambio" data-id="{{ $change->id }}" title="Ver detalle" aria-label="Ver detalle">
        <i class="bi bi-eye-fill"></i>
    </button>
    @if(!$change->reverted_at)
        <form action="{{ route('admin.programming.changes.destroy', $change) }}" method="POST" class="change-action-form frmRevertirCambio">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger btn-revert-change" title="Eliminar y revertir" aria-label="Eliminar y revertir">
                <i class="bi bi-trash3-fill"></i>
            </button>
        </form>
    @endif
</div>
