<form action="{{ $url }}" method="POST" data-maintenance-delete data-title="{{ $label }}" data-confirmation="{{ $confirmation }}">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-danger" aria-label="{{ $label }}" title="{{ $label }}">
        <i class="bi bi-trash3-fill"></i>
    </button>
</form>
