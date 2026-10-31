<div class="alert alert-danger d-none" id="reasonErrors" role="alert"></div>
<form id="frmMotivo" method="post" action="{{ $reason->exists ? route('admin.programming.reasons.update', $reason) : route('admin.programming.reasons.store') }}">
    @csrf
    @if($reason->exists) @method('PUT') @endif
    <div class="form-group mb-3">
        <label for="reasonName">Nombre *</label>
        <input class="form-control" id="reasonName" name="name" required maxlength="150" value="{{ old('name', $reason->name) }}" placeholder="Nombre del motivo">
    </div>
    <div class="form-group mb-3">
        <label for="reasonDescription">Descripción</label>
        <textarea class="form-control" id="reasonDescription" name="description" maxlength="2000" rows="3" placeholder="Agregue una descripción (opcional)">{{ old('description', $reason->description) }}</textarea>
    </div>
    <div class="d-flex justify-content-end flex-wrap gap-2 mt-4">
        <button type="button" class="btn reason-modal-cancel" data-bs-dismiss="modal"><i class="bi bi-x-circle mr-1"></i> Cancelar</button>
        <button type="submit" class="btn reason-modal-save"><i class="bi bi-floppy mr-1"></i> Guardar</button>
    </div>
</form>
