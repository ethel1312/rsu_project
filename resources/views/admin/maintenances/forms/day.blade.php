<form method="POST" enctype="multipart/form-data" action="{{ route('admin.maintenances.schedules.days.update', [$maintenance, $schedule, $day]) }}" data-maintenance-save>
    @csrf
    @method('PUT')
    <div class="alert alert-danger d-none" data-form-errors role="alert"></div>
    <div class="mb-3">
        <label class="form-label" for="maintenance-day-date">Fecha</label>
        <input type="date" class="form-control" id="maintenance-day-date" value="{{ $day->date->toDateString() }}" readonly>
    </div>
    <div class="mb-3">
        <label class="form-label" for="maintenance-observation">Observación</label>
        <textarea class="form-control" id="maintenance-observation" name="observation" rows="3" maxlength="2000">{{ $day->observation }}</textarea>
    </div>
    <div class="mb-3">
        <label class="form-label" for="maintenance-image">{{ $day->image_path ? 'Reemplazar imagen' : 'Imagen' }}</label>
        <input type="file" class="form-control" id="maintenance-image" name="image" accept="image/jpeg,image/png,image/webp" aria-describedby="maintenance-image-help">
        <div class="form-text" id="maintenance-image-help">JPG, PNG o WebP. Máximo 5 MB.</div>
    </div>
    <div>
        <label class="form-label" for="maintenance-status">Estado</label>
        <select class="form-select" id="maintenance-status" name="status" required>
            @foreach (\App\Models\MaintenanceDay::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected($day->status === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    @include('admin.maintenances.forms.footer', ['submitLabel' => 'Guardar cambios'])
</form>
