{!! Form::open(['route' => ['admin.programming.schedules.update', $schedule], 'method' => 'put', 'id' => 'frmSchedule', 'class' => 'day-schedule-editor']) !!}
    <input type="hidden" name="mode" value="individual">
    <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
    <input type="hidden" name="group_id" value="{{ $schedule->personnel_group_id }}">
    <input type="hidden" name="service_date" value="{{ $singleDayDate }}">
    <input type="hidden" name="start_date" value="{{ $singleDayDate }}">
    <input type="hidden" name="end_date" value="{{ $singleDayDate }}">
    <input type="hidden" name="days_of_week[]" value="{{ \Carbon\Carbon::parse($singleDayDate)->dayOfWeekIso }}">
    <input type="hidden" name="shift_id" id="dayShiftValue" class="schedule-input" value="{{ $schedule->shift_id }}">
    <input type="hidden" name="vehicle_id" id="dayVehicleValue" class="schedule-input" value="{{ $schedule->vehicle_id }}">
    <input type="hidden" name="driver_id" id="dayDriverValue" class="schedule-input" value="{{ $schedule->driver_id }}">
    @foreach ($schedule->helper_ids ?? [] as $helperId)
        <input type="hidden" name="helper_ids[]" class="day-helper-value schedule-input" value="{{ $helperId }}">
    @endforeach
    <input type="hidden" name="change_reason" id="dayChangeReason" value="">
    <input type="hidden" name="availability_token" id="availabilityToken" value="">
    <input type="hidden" id="dayChangesPayload" value="[]">

    <div id="scheduleErrors" class="alert alert-danger d-none" role="alert"></div>
    <div class="alert alert-info small mb-3">
        <strong>{{ $schedule->group_name }}</strong> · {{ $schedule->zone_name }} · {{ $schedule->shift_name }} · {{ \Carbon\Carbon::parse($singleDayDate)->format('d/m/Y') }}.
        Los cambios se aplicarán únicamente a esta fecha y la programación quedará como <strong>Reprogramado</strong>.
    </div>

    <div class="row">
        <div class="col-xl-4 col-md-6 mb-3">
            <section class="card day-change-card h-100">
                <div class="card-header bg-primary text-white"><i class="bi bi-clock-fill mr-1" aria-hidden="true"></i> Cambio de turno</div>
                <div class="card-body">
                    <label for="dayShiftCurrent">Turno actual</label>
                    <input id="dayShiftCurrent" class="form-control mb-3" value="{{ $schedule->shift_name }} ({{ substr($schedule->shift_start_time, 0, 5) }}–{{ substr($schedule->shift_end_time, 0, 5) }})" readonly>
                    <label for="dayShiftNew">Nuevo turno</label>
                    <select id="dayShiftNew" class="form-control schedule-select2 day-change-input" data-placeholder="Seleccione un nuevo turno">
                        <option value="">Seleccione un nuevo turno</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }})</option>
                        @endforeach
                    </select>
                    @include('admin.programming.schedules.template.change-reason-fields', ['prefix' => 'shift', 'label' => 'turno'])
                    <button type="button" class="btn btn-primary btn-sm day-add-change mt-2" data-type="shift"><i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>Agregar cambio</button>
                    <div class="day-change-availability d-none mt-2"></div>
                </div>
            </section>
        </div>

        <div class="col-xl-4 col-md-6 mb-3">
            <section class="card day-change-card h-100">
                <div class="card-header bg-warning"><i class="bi bi-truck mr-1" aria-hidden="true"></i> Cambio de vehículo</div>
                <div class="card-body">
                    <label for="dayVehicleCurrent">Vehículo actual</label>
                    <input id="dayVehicleCurrent" class="form-control mb-3" value="{{ $schedule->vehicle_label }}" readonly>
                    <label for="dayVehicleNew">Nuevo vehículo</label>
                    <select id="dayVehicleNew" class="form-control schedule-select2 day-change-input" data-placeholder="Seleccione un nuevo vehículo">
                        <option value="">Seleccione un nuevo vehículo</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->plate }} · {{ $vehicle->name }} ({{ $vehicle->occupant_capacity }} personas)</option>
                        @endforeach
                    </select>
                    @include('admin.programming.schedules.template.change-reason-fields', ['prefix' => 'vehicle', 'label' => 'vehículo'])
                    <button type="button" class="btn btn-primary btn-sm day-add-change mt-2" data-type="vehicle"><i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>Agregar cambio</button>
                    <div class="day-change-availability d-none mt-2"></div>
                </div>
            </section>
        </div>

        <div class="col-xl-4 col-md-12 mb-3">
            <section class="card day-change-card h-100">
                <div class="card-header bg-success text-white"><i class="bi bi-people-fill mr-1" aria-hidden="true"></i> Cambio de personal</div>
                <div class="card-body">
                    <label for="dayPersonnelCurrent">Personal actual</label>
                    <select id="dayPersonnelCurrent" class="form-control schedule-select2 day-change-input" data-placeholder="Seleccione el personal actual"></select>
                    <label for="dayPersonnelNew" class="mt-3">Nuevo personal</label>
                    <select id="dayPersonnelNew" class="form-control schedule-select2 day-change-input" data-placeholder="Seleccione el nuevo personal">
                        <option value="">Buscar empleado disponible...</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}" data-role="driver">{{ $driver->full_name }} · {{ $driver->dni }}</option>
                        @endforeach
                        @foreach ($helpers as $helper)
                            <option value="{{ $helper->id }}" data-role="helper">{{ $helper->full_name }} · {{ $helper->dni }}</option>
                        @endforeach
                    </select>
                    @include('admin.programming.schedules.template.change-reason-fields', ['prefix' => 'personnel', 'label' => 'personal'])
                    <button type="button" class="btn btn-primary btn-sm day-add-change mt-2" data-type="personnel"><i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>Agregar cambio</button>
                    <div class="day-change-availability d-none mt-2"></div>
                </div>
            </section>
        </div>
    </div>

    <section class="card day-registered-changes mb-3">
        <div class="card-header bg-primary text-white"><i class="bi bi-list-check mr-1" aria-hidden="true"></i> Cambios registrados</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="thead-light"><tr><th>Tipo de cambio</th><th>Valor anterior</th><th>Valor nuevo</th><th>Motivo</th><th>Descripción</th><th>Acción</th></tr></thead>
                    <tbody id="dayChanges"><tr><td colspan="6" class="text-center text-muted py-3">No hay cambios registrados. Agregue cambios usando los botones superiores.</td></tr></tbody>
                </table>
            </div>
        </div>
    </section>

    <div id="scheduleAvailability" class="alert d-none mt-3" role="status" aria-live="polite"></div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3" style="gap:10px">
        <button type="button" class="btn btn-outline-primary" id="btnValidateAvailability"><i class="bi bi-check2-circle mr-1" aria-hidden="true"></i>Validar disponibilidad</button>
        <div class="d-flex" style="gap:8px">
            <button type="button" class="btn btn-modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-modal-guardar" id="btnGuardarSchedule" disabled><i class="bi bi-floppy mr-1" aria-hidden="true"></i>Guardar reprogramación</button>
        </div>
    </div>

    <select id="dayPersonnelCatalog" class="d-none" aria-hidden="true" tabindex="-1">
        @foreach ($drivers as $driver)<option value="{{ $driver->id }}" data-role="driver">{{ $driver->full_name }} · {{ $driver->dni }}</option>@endforeach
        @foreach ($helpers as $helper)<option value="{{ $helper->id }}" data-role="helper">{{ $helper->full_name }} · {{ $helper->dni }}</option>@endforeach
    </select>
{!! Form::close() !!}
