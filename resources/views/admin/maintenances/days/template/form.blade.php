<div class="alert alert-danger d-none maintenance-errors" role="alert"></div>
<p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($day->date)->format('d/m/Y') }}</p>
<div class="mb-3">
    <label for="observation">Observación</label>
    <textarea id="observation" name="observation" class="form-control" rows="3" maxlength="3000">{{ $day->observation }}</textarea>
</div>
<div class="mb-3">
    <label for="is_completed">Estado</label>
    <select id="is_completed" name="is_completed" class="form-select">
        <option value="" @selected($day->is_completed === null)>Pendiente</option>
        <option value="1" @selected($day->is_completed === true)>Realizado</option>
        <option value="0" @selected($day->is_completed === false)>No realizado</option>
    </select>
</div>
<div class="mb-3">
    <label for="image">Imagen (JPG, PNG o WebP; máximo 5 MB)</label>
    <input type="file" id="image" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
    @if($day->image_path)
        <p class="small text-muted mt-2">La imagen actual se conserva si no selecciona otra.</p>
        <a href="{{ asset('storage/'.$day->image_path) }}" target="_blank" rel="noopener">
            <img src="{{ asset('storage/'.$day->image_path) }}" alt="Evidencia del {{ $day->date }}" width="80" height="60" style="object-fit:cover;border-radius:6px" loading="lazy">
        </a>
    @endif
</div>
<div class="mt-4 text-end">
    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
    <button type="submit" class="btn btn-primary">Guardar</button>
</div>
