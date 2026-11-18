<div class="alert alert-danger d-none maintenance-errors" role="alert"></div>
<div class="row">
    <div class="col-md-12 mb-3">
        <label for="name">Nombre del mantenimiento *</label>
        <input id="name" name="name" class="form-control" value="{{ $maintenance->name ?? '' }}" required maxlength="150" placeholder="Ej: MANT. DICIEMBRE 2025">
    </div>
    <div class="col-md-6 mb-3">
        <label for="start_date">Fecha de inicio *</label>
        <input type="date" id="start_date" name="start_date" class="form-control" value="{{ $maintenance->start_date ?? '' }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label for="end_date">Fecha de fin *</label>
        <input type="date" id="end_date" name="end_date" class="form-control" value="{{ $maintenance->end_date ?? '' }}" required>
    </div>
</div>
<div class="mt-4 text-end">
    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
    <button type="submit" class="btn btn-primary">Guardar</button>
</div>
