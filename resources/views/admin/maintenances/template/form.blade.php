<form action="{{ $maintenance->exists ? route('admin.maintenances.update', $maintenance) : route('admin.maintenances.store') }}" method="POST" class="frmMantenimiento">
    @csrf
    @if($maintenance->exists) @method('PUT') @endif
    <div class="mb-3"><label for="maintenance_name" class="form-label">Nombre *</label><input id="maintenance_name" name="name" class="form-control" maxlength="150" placeholder="Ej. MANT. DICIEMBRE 2026" value="{{ old('name', $maintenance->name) }}" required></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label for="maintenance_start" class="form-label">Fecha de inicio *</label><input type="date" id="maintenance_start" @unless($hasGeneratedDays) name="start_date" @endunless class="form-control" value="{{ old('start_date', $maintenance->exists ? $maintenance->start_date->format('Y-m-d') : '') }}" @disabled($hasGeneratedDays) required>@if($hasGeneratedDays)<input type="hidden" name="start_date" value="{{ $maintenance->start_date->format('Y-m-d') }}">@endif</div>
        <div class="col-md-6 mb-3"><label for="maintenance_end" class="form-label">Fecha de fin *</label><input type="date" id="maintenance_end" @unless($hasGeneratedDays) name="end_date" @endunless class="form-control" min="{{ old('start_date', $maintenance->exists ? $maintenance->start_date->format('Y-m-d') : '') }}" value="{{ old('end_date', $maintenance->exists ? $maintenance->end_date->format('Y-m-d') : '') }}" @disabled($hasGeneratedDays) required>@if($hasGeneratedDays)<input type="hidden" name="end_date" value="{{ $maintenance->end_date->format('Y-m-d') }}">@endif</div>
    </div>
    @if($hasGeneratedDays)<div class="alert alert-info py-2"><i class="bi bi-info-circle me-1"></i>Este mantenimiento ya tiene horarios y días generados. Las fechas están bloqueadas; puede editar el nombre.</div>@endif
    <div class="d-flex justify-content-end" style="gap:8px">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i>Cancelar</button>
        <button type="submit" class="btn btn-success"><i class="bi bi-floppy-fill me-1"></i>{{ $maintenance->exists ? 'Guardar cambios' : 'Guardar' }}</button>
    </div>
</form>
