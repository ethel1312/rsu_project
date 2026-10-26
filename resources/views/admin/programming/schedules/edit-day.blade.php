{!! Form::open(['route' => ['admin.programming.schedules.update', $schedule], 'method' => 'put', 'id' => 'frmSchedule']) !!}
    <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
    <input type="hidden" name="date" value="{{ $serviceDate }}">
    <input type="hidden" name="availability_token" id="availabilityToken" value="">
    <div id="scheduleErrors" class="alert alert-danger d-none"></div>
    <div class="alert alert-info">
        <strong>{{ $schedule->group_name }}</strong> · {{ \Carbon\Carbon::parse($serviceDate)->format('d/m/Y') }} · {{ $schedule->zone_name }}.
        Los cambios se aplican únicamente a este día. Indique un motivo para cada campo modificado.
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="dayShift">Turno</label>
            <select name="shift_id" id="dayShift" data-change-key="turno" class="form-control schedule-input schedule-select2" required>
                @foreach ($shifts as $shift)
                    <option value="{{ $shift->id }}" @selected($schedule->shift_id == $shift->id)>{{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }})</option>
                @endforeach
            </select>
            <label for="reasonShift" class="mt-2">Motivo del cambio de turno</label>
            <textarea name="reasons[turno]" id="reasonShift" maxlength="1000" class="form-control schedule-input" rows="2"></textarea>
        </div>
        <div class="col-md-6 mb-3">
            <label for="dayVehicle">Vehículo</label>
            <select name="vehicle_id" id="dayVehicle" data-change-key="vehiculo" class="form-control schedule-input schedule-select2" required>
                @foreach ($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}" @selected($schedule->vehicle_id == $vehicle->id)>{{ $vehicle->plate }} · {{ $vehicle->name }} ({{ $vehicle->occupant_capacity }} ocupantes)</option>
                @endforeach
            </select>
            <label for="reasonVehicle" class="mt-2">Motivo del cambio de vehículo</label>
            <textarea name="reasons[vehiculo]" id="reasonVehicle" maxlength="1000" class="form-control schedule-input" rows="2"></textarea>
        </div>
        <div class="col-md-6 mb-3">
            <label for="dayDriver">Conductor</label>
            <select name="driver_id" id="dayDriver" data-change-key="conductor" class="form-control schedule-input schedule-select2" required>
                @foreach ($drivers as $driver)
                    <option value="{{ $driver->id }}" @selected($schedule->driver_id == $driver->id)>{{ $driver->full_name }} · {{ $driver->dni }}</option>
                @endforeach
            </select>
            <label for="reasonDriver" class="mt-2">Motivo del cambio de conductor</label>
            <textarea name="reasons[conductor]" id="reasonDriver" maxlength="1000" class="form-control schedule-input" rows="2"></textarea>
        </div>
        <div class="col-md-6 mb-3">
            <label for="dayHelpers">Ayudantes</label>
            <select name="helper_ids[]" id="dayHelpers" data-change-key="ayudantes" class="form-control schedule-input schedule-select2" multiple required>
                @foreach ($helpers as $helper)
                    <option value="{{ $helper->id }}" @selected(in_array($helper->id, $schedule->helper_ids ?? []))>{{ $helper->full_name }} · {{ $helper->dni }}</option>
                @endforeach
            </select>
            <label for="reasonHelpers" class="mt-2">Motivo del cambio de ayudantes</label>
            <textarea name="reasons[ayudantes]" id="reasonHelpers" maxlength="1000" class="form-control schedule-input" rows="2"></textarea>
        </div>
    </div>
    <h6>Cambios a guardar</h6>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Campo</th><th>Valor anterior</th><th>Valor nuevo</th><th>Motivo</th></tr></thead>
            <tbody id="dayChanges"><tr><td colspan="4" class="text-muted">Todavía no hay cambios.</td></tr></tbody>
        </table>
    </div>
    <div id="scheduleAvailability" class="alert d-none mt-3" role="status" aria-live="polite"></div>
    <div class="d-flex flex-wrap justify-content-between mt-3" style="gap:10px">
        <button type="button" class="btn btn-outline-primary" id="btnValidateAvailability">Validar cambios</button>
        <div>
            <button type="button" class="btn btn-modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-modal-guardar" id="btnGuardarSchedule" disabled>Guardar reprogramación</button>
        </div>
    </div>
{!! Form::close() !!}
