@php
    $selectedDays = old('days_of_week', $group?->days_of_week ?? []);
    $selectedHelpers = old('helpers', $group?->helpers->pluck('id')->all() ?? []);
@endphp
<div class="alert alert-danger d-none" id="personnelGroupErrors" role="alert" tabindex="-1"></div>
@if ($group)
    <input type="hidden" id="groupCurrentId" value="{{ $group->id }}">
@endif
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="group_name" class="font-weight-bold">Nombre del grupo *</label>
        <input type="text" class="form-control" name="name" id="group_name" value="{{ old('name', $group?->name) }}" maxlength="150" placeholder="Ej.: Grupo Centro de Chiclayo" required>
    </div>
    <div class="col-md-6 mb-3">
        <label for="group_shift_id" class="font-weight-bold">Turno *</label>
        <select class="form-control" name="shift_id" id="group_shift_id" required>
            <option value="">Seleccione un turno</option>
            @foreach ($shifts as $shift)
                <option value="{{ $shift->id }}" @selected(old('shift_id', $group?->shift_id) == $shift->id)>{{ $shift->name }} · {{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }}{{ $shift->end_time < $shift->start_time ? ' (día siguiente)' : '' }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label for="group_zone_id" class="font-weight-bold">Zona *</label>
        <select class="form-control" name="zone_id" id="group_zone_id" required>
            <option value="">Seleccione una zona activa</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected(old('zone_id', $group?->zone_id) == $zone->id)>{{ $zone->name }} · {{ $zone->district }}{{ ! $zone->status ? ' (inactiva: seleccione otra zona)' : '' }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label for="group_vehicle_id" class="font-weight-bold">Vehículo *</label>
        <select class="form-control" name="vehicle_id" id="group_vehicle_id" required>
            <option value="">Seleccione un vehículo</option>
            @foreach ($vehicles as $vehicle)
                <option value="{{ $vehicle->id }}" data-capacity="{{ $vehicle->occupant_capacity }}" @selected(old('vehicle_id', $group?->vehicle_id) == $vehicle->id)>{{ $vehicle->plate }} · {{ $vehicle->name }} ({{ $vehicle->occupant_capacity }} personas)</option>
            @endforeach
        </select>
        <small class="text-info d-block mt-1" id="groupCapacity" aria-live="polite">La capacidad incluye al conductor y a los ayudantes.</small>
    </div>
    <div class="col-12 mb-3">
        <fieldset>
            <legend class="font-weight-bold fs-6">Días de trabajo *</legend>
            <div class="row">
                @foreach ($weekdays as $number => $label)
                    <div class="col-6 col-md-4 mb-2">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="days_of_week[]" value="{{ $number }}" id="group_day_{{ $number }}" @checked(in_array($number, $selectedDays))>
                            <label class="form-check-label" for="group_day_{{ $number }}">{{ $label }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
            <small class="text-muted">En turnos nocturnos, cada día marcado corresponde al día de inicio del turno.</small>
        </fieldset>
    </div>
    <div class="col-md-6 mb-3">
        <label for="group_driver_id" id="groupDriverLabel" class="font-weight-bold">Conductor *</label>
        <select class="form-control group-search" name="driver_id" id="group_driver_id" aria-describedby="groupDriverAvailability" required>
            <option value="">Busque por nombre, apellido o DNI</option>
            @foreach ($drivers as $driver)
                <option value="{{ $driver->id }}" data-name="{{ $driver->full_name }}" data-dni="{{ $driver->dni }}" data-type="Conductor" @selected(old('driver_id', $group?->driver_id) == $driver->id)>{{ $driver->full_name }} · DNI {{ $driver->dni }}{{ ! $driver->status || $driver->employeeType?->name !== 'Conductor' ? ' (no disponible: reemplazar)' : '' }}</option>
            @endforeach
        </select>
        <div id="groupDriverAvailability" class="mt-2" aria-live="polite"></div>
    </div>
    <div class="col-md-6 mb-3">
        <label for="group_helpers" id="groupHelpersLabel" class="font-weight-bold">Ayudantes *</label>
        <select class="form-control group-search" name="helpers[]" id="group_helpers" aria-describedby="groupHelpersHint groupHelpersError" multiple required>
            @foreach ($helpers as $helper)
                <option value="{{ $helper->id }}" data-name="{{ $helper->full_name }}" data-dni="{{ $helper->dni }}" data-type="Ayudante" @selected(in_array($helper->id, $selectedHelpers))>{{ $helper->full_name }} · DNI {{ $helper->dni }}{{ ! $helper->status || $helper->employeeType?->name !== 'Ayudante' ? ' (no disponible: reemplazar)' : '' }}</option>
            @endforeach
        </select>
        <small class="text-muted d-block mt-1" id="groupHelpersHint">Seleccione uno o varios ayudantes. Puede buscarlos por nombre o DNI.</small>
        <small class="text-danger d-block mt-1 d-none" id="groupHelpersError" role="alert" aria-live="assertive"></small>
        <div id="groupHelpersAvailability" class="row mt-2" aria-live="polite"></div>
    </div>
    <div class="col-md-6 mb-3">
        <label for="group_status" class="font-weight-bold">Estado *</label>
        <select class="form-control" id="group_status" name="status" required>
            <option value="1" @selected(old('status', $group?->status ?? true))>Activo</option>
            <option value="0" @selected(! old('status', $group?->status ?? true))>Inactivo</option>
        </select>
    </div>
</div>
<div class="alert alert-info small">
    Esta plantilla precarga el turno, la zona, el vehículo y el personal para futuras programaciones. Puede editar estos datos cuando cambie el equipo.
</div>
<div class="mt-4 text-right">
    <button type="button" class="btn btn-modal-cancelar mr-2" data-bs-dismiss="modal">Cancelar</button>
    <button type="submit" class="btn btn-modal-guardar">{{ $group ? 'Actualizar grupo' : 'Guardar grupo' }}</button>
</div>
