<form method="POST" action="{{ $schedule ? route('admin.maintenances.schedules.update', [$maintenance, $schedule]) : route('admin.maintenances.schedules.store', $maintenance) }}" data-maintenance-save>
    @csrf
    @if ($schedule)
        @method('PUT')
    @endif
    <div class="alert alert-danger d-none" data-form-errors role="alert"></div>
    <div class="mb-3">
        <label class="form-label" for="maintenance-vehicle">Vehículo</label>
        <select class="form-select" id="maintenance-vehicle" name="vehicle_id" required>
            <option value="">{{ $vehicles->isEmpty() ? 'No hay vehículos registrados' : 'Seleccione un vehículo' }}</option>
            @foreach ($vehicles as $vehicle)
                <option value="{{ $vehicle->id }}" @selected($schedule?->vehicle_id == $vehicle->id)>{{ $vehicle->plate }} · {{ $vehicle->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label" for="maintenance-type">Tipo de mantenimiento</label>
        <select class="form-select" id="maintenance-type" name="type" required>
            <option value="">Seleccione un tipo</option>
            @foreach (\App\Models\MaintenanceSchedule::TYPES as $value => $label)
                <option value="{{ $value }}" @selected($schedule?->type === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label" for="maintenance-weekday">Día de la semana</label>
        <select class="form-select" id="maintenance-weekday" name="weekday" required>
            <option value="">Seleccione un día</option>
            @foreach (\App\Models\MaintenanceSchedule::WEEKDAYS as $value => $label)
                <option value="{{ $value }}" @selected($schedule?->weekday === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <label class="form-label" for="maintenance-start-time">Hora de inicio</label>
            <input type="time" class="form-control" id="maintenance-start-time" name="start_time"
                value="{{ substr($schedule?->start_time ?? '', 0, 5) }}" required>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="maintenance-end-time">Hora de fin</label>
            <input type="time" class="form-control" id="maintenance-end-time" name="end_time"
                value="{{ substr($schedule?->end_time ?? '', 0, 5) }}" required>
        </div>
    </div>
    <div>
        <label class="form-label" for="maintenance-employee">Responsable</label>
        <select class="form-select" id="maintenance-employee" name="employee_id" required>
            <option value="">{{ $employees->isEmpty() ? 'No hay responsables activos' : 'Seleccione un responsable' }}</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" @selected($schedule?->employee_id == $employee->id)>
                    {{ $employee->full_name }} · {{ $employee->dni }}{{ $employee->status ? '' : ' (inactivo)' }}
                </option>
            @endforeach
        </select>
    </div>
    @include('admin.maintenances.forms.footer', ['submitLabel' => 'Guardar horario'])
</form>
