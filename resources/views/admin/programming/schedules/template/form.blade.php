@php
    $schedule = $schedule ?? null;
    $mode = $mode ?? 'individual';
    $selectedDays = old('days_of_week', $schedule?->days_of_week ?? []);
    $selectedHelpers = old('helper_ids', $schedule?->helper_ids ?? []);
@endphp
<div class="alert alert-danger d-none" id="scheduleErrors" role="alert"></div>
@if ($schedule)
    <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
@endif
<input type="hidden" name="mode" value="{{ $mode }}">
<input type="hidden" name="availability_token" id="availabilityToken" value="">
<div class="row">
    @if ($mode === 'individual')
        <div class="col-md-6 mb-3">
            <label for="schedule_group_id" class="font-weight-bold">Grupo de personal *</label>
            <select class="form-control schedule-input schedule-select2" name="group_id" id="schedule_group_id" required>
                <option value="">Seleccione un grupo activo</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}" @selected(old('group_id', $schedule?->personnel_group_id) == $group->id)>
                        {{ $group->name }} · {{ $group->shift?->name }} · {{ $group->vehicle?->plate }}
                    </option>
                @endforeach
            </select>

        </div>
    @endif
    <div class="{{ $mode === 'mass' ? 'col-md-6' : 'col-md-3' }} mb-3">
        <label for="schedule_start_date" class="font-weight-bold">Fecha de inicio *</label>
        <input type="date" class="form-control schedule-input" name="start_date" id="schedule_start_date"
               value="{{ old('start_date', $schedule?->start_date?->format('Y-m-d')) }}" min="{{ now()->toDateString() }}" required>
    </div>
    <div class="{{ $mode === 'mass' ? 'col-md-6' : 'col-md-3' }} mb-3">
        <label for="schedule_end_date" class="font-weight-bold">Fecha de fin *</label>
        <input type="date" class="form-control schedule-input" name="end_date" id="schedule_end_date"
               value="{{ old('end_date', $schedule?->end_date?->format('Y-m-d')) }}" min="{{ now()->toDateString() }}" required>
    </div>

    @if ($mode === 'individual')
        <div class="col-12 mb-3">
            <div id="scheduleGroupSummary" class="schedule-group-summary {{ $schedule ? '' : 'd-none' }}" aria-live="polite">
                <div class="row">
                    <div class="col-md-3 col-6"><small>Grupo</small><strong id="scheduleSummaryGroup">{{ $schedule?->group_name ?? '—' }}</strong></div>
                    <div class="col-md-3 col-6"><small>Zona</small><strong id="scheduleSummaryZone">{{ $schedule?->zone_name ?? '—' }}</strong></div>
                    <div class="col-md-3 col-6"><small>Turno</small><strong id="scheduleSummaryShift">{{ $schedule ? $schedule->shift_name.' ('.substr($schedule->shift_start_time, 0, 5).'–'.substr($schedule->shift_end_time, 0, 5).')' : '—' }}</strong></div>
                    <div class="col-md-3 col-6"><small>Vehículo</small><strong id="scheduleSummaryVehicle">{{ $schedule?->vehicle_label ?? '—' }}</strong></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="schedule_driver_id" class="font-weight-bold">Conductor *</label>
            <select class="form-control schedule-input schedule-select2" name="driver_id" id="schedule_driver_id" required>
                <option value="">Seleccione un conductor</option>
                @foreach ($drivers as $driver)
                    <option value="{{ $driver->id }}" @selected(old('driver_id', $schedule?->driver_id) == $driver->id)>{{ $driver->full_name }} · DNI {{ $driver->dni }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label for="schedule_helper_ids" class="font-weight-bold">Ayudantes *</label>
            <select class="form-control schedule-input schedule-select2" name="helper_ids[]" id="schedule_helper_ids" multiple required>
                @foreach ($helpers as $helper)
                    <option value="{{ $helper->id }}" @selected(in_array($helper->id, $selectedHelpers))>{{ $helper->full_name }} · DNI {{ $helper->dni }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 mb-3">
            <fieldset>
                <legend class="font-weight-bold fs-6">Días de trabajo *</legend>
                <div class="row">
                    @foreach ($weekdays as $number => $label)
                        <div class="col-6 col-md-3 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input schedule-input" name="days_of_week[]" value="{{ $number }}" id="schedule_day_{{ $number }}" @checked(in_array($number, $selectedDays))>
                                <label class="form-check-label" for="schedule_day_{{ $number }}">{{ $label }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>
    @else
        @include('admin.programming.schedules.template.mass-groups')
    @endif
</div>

<div class="alert d-none mt-3" id="scheduleAvailability" role="status" aria-live="polite"></div>
<div class="d-flex flex-wrap justify-content-between align-items-center mt-4" style="gap:10px">
    <button type="button" class="btn btn-outline-primary" id="btnValidateAvailability">
        <i class="bi bi-check2-circle"></i> Validar disponibilidad
    </button>
    <div class="d-flex" style="gap:8px">
        <button type="button" class="btn btn-modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-modal-guardar" id="btnGuardarSchedule" disabled>
            <i class="bi bi-floppy"></i> {{ $schedule ? 'Actualizar programación' : 'Guardar programación' }}
        </button>
    </div>
</div>
