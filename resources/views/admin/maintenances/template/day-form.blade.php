<form action="{{ route('admin.maintenances.days.update', $day) }}" method="POST" enctype="multipart/form-data" class="frmDiaMantenimiento">
    @csrf
    @method('PUT')

    <div class="mb-3">
        <label class="form-label">Fecha</label>
        <input type="text" class="form-control" value="{{ $day->maintenance_date->format('d/m/Y') }}" readonly>
    </div>

    <div class="mb-3">
        <label for="maintenance_day_status" class="form-label">Estado *</label>
        <select class="form-select" id="maintenance_day_status" name="status" required>
            <option value="Pendiente" @selected($day->status === 'Pendiente')>Pendiente</option>
            <option value="Realizado" @selected($day->status === 'Realizado')>Realizado</option>
            <option value="No realizado" @selected($day->status === 'No realizado')>No realizado</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="maintenance_day_observation" class="form-label">Observación</label>
        <textarea class="form-control" id="maintenance_day_observation" name="observation" rows="3" maxlength="2000" placeholder="Ingrese una observación (opcional)">{{ $day->observation }}</textarea>
    </div>

    @php($currentImage = $day->image_path ? asset('storage/'.$day->image_path) : '')
    <div class="mb-3">
        <label for="maintenance_day_image" class="form-label">{{ $day->image_path ? 'Cambiar imagen (opcional)' : 'Imagen (opcional)' }}</label>
        <div id="maintenanceImagePreviewWrap" class="mb-2" @if(!$day->image_path) hidden @endif>
            <img id="maintenanceImagePreview" src="{{ $currentImage }}" data-current-src="{{ $currentImage }}" alt="Vista previa del mantenimiento" class="img-thumbnail" style="max-width: 240px; max-height: 180px; object-fit: contain;">
        </div>
        @if($day->image_path)
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_maintenance_image">
                <label class="form-check-label" for="remove_maintenance_image">Quitar imagen actual</label>
            </div>
        @endif
        <input type="file" class="form-control" id="maintenance_day_image" name="image" accept="image/jpeg,image/png,image/webp">
        <small class="form-text text-muted">{{ $day->image_path ? 'Al seleccionar otra imagen, se reemplazará la actual. ' : '' }}JPG, PNG o WebP; máximo 5 MB.</small>
    </div>

    <div class="d-flex justify-content-end" style="gap:8px">
        <button type="button" class="btn maintenance-modal-cancel" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i>Cancelar</button>
        <button type="submit" class="btn maintenance-modal-save"><i class="bi bi-floppy-fill me-1"></i>Guardar cambios</button>
    </div>
</form>
