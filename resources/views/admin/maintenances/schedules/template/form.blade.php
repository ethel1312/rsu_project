<div class="alert alert-danger d-none maintenance-errors" role="alert"></div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="vehicle_id">Vehículo *</label>
        <select id="vehicle_id" name="vehicle_id" class="form-select" required>
            <option value="">Seleccione un vehículo</option>
            @foreach($vehicles as $vehicle)
                <option value="{{ $vehicle->id }}" @selected(($schedule->vehicle_id ?? null) == $vehicle->id)>{{ $vehicle->plate }} · {{ $vehicle->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label for="employee_id">Responsable *</label>
        <select id="employee_id" name="employee_id" class="form-select" required>
            <option value="">Seleccione un responsable</option>
            @foreach($employees as $employee)
                <option value="{{ $employee->id }}" @selected(($schedule->employee_id ?? null) == $employee->id)>{{ $employee->dni }} · {{ $employee->full_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label for="type">Tipo de mantenimiento *</label>
        <select id="type" name="type" class="form-select" required>
            @foreach($types as $value => $label)<option value="{{ $value }}" @selected(($schedule->type ?? '') === $value)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label for="weekday">Día de la semana *</label>
        <select id="weekday" name="weekday" class="form-select" required>
            @foreach($weekdays as $value => $label)<option value="{{ $value }}" @selected(($schedule->weekday ?? null) == $value)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label for="start_time">Hora de inicio *</label>
        <input type="time" id="start_time" name="start_time" class="form-control" required value="{{ isset($schedule) ? substr($schedule->start_time, 0, 5) : '' }}">
    </div>
    <div class="col-md-6 mb-3">
        <label for="end_time">Hora de fin *</label>
        <input type="time" id="end_time" name="end_time" class="form-control" required value="{{ isset($schedule) ? substr($schedule->end_time, 0, 5) : '' }}">
    </div>
</div>
<div class="alert alert-info small">Se generará un registro por cada día seleccionado dentro del período. Las horas deben pertenecer al mismo día. Un vehículo no puede tener horarios superpuestos.</div>
<div class="mt-4 text-end">
    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
    <button type="submit" class="btn btn-primary">Guardar</button>
</div>
