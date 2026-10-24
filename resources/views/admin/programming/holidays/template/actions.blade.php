<div class="d-flex justify-content-center gap-1">
    <button type="button" class="btn btn-sm btn-outline-primary btnEditar" data-id="{{ $holiday->id }}" title="Editar feriado" aria-label="Editar feriado">
        <i class="bi bi-pencil-square"></i>
    </button>
    <form action="{{ route('admin.programming.holidays.toggle-status', $holiday) }}" method="POST" class="frmEstado">
        @csrf
        @method('PATCH')
        <input type="hidden" name="is_active" value="{{ $holiday->is_active ? 0 : 1 }}">
        <button type="submit" class="btn btn-sm btn-outline-{{ $holiday->is_active ? 'secondary' : 'success' }}" title="{{ $holiday->is_active ? 'Desactivar' : 'Activar' }} feriado" aria-label="{{ $holiday->is_active ? 'Desactivar' : 'Activar' }} feriado">
            <i class="bi bi-{{ $holiday->is_active ? 'pause-circle' : 'play-circle' }}"></i>
        </button>
    </form>
    <form action="{{ route('admin.programming.holidays.destroy', $holiday) }}" method="POST" class="frmEliminar">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar feriado" aria-label="Eliminar feriado">
            <i class="bi bi-trash"></i>
        </button>
    </form>
</div>
