<div class="col-12 mb-3">
    <label class="font-weight-bold d-block mb-2">Filtrar grupos por turno</label>
    <div class="d-flex flex-wrap" style="gap:8px" role="group" aria-label="Filtrar grupos por turno">
        <button type="button" class="btn btn-sm btn-primary mass-shift-filter active" data-shift="" aria-pressed="true">Todos los turnos</button>
        @foreach ($shifts as $shift)
            <button type="button" class="btn btn-sm btn-outline-primary mass-shift-filter" data-shift="{{ $shift->id }}" aria-pressed="false">{{ $shift->name }}</button>
        @endforeach
    </div>
    <small class="text-muted">Se generarán los grupos marcados del turno seleccionado. Puede ajustar su personal antes de validar.</small>
</div>
<div class="col-12 mb-3">
    <h6 class="schedule-mass-section-title mb-2"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i> Días feriados en el rango seleccionado:</h6>
    <input type="hidden" name="holidays_configured" id="holidaysConfigured" value="0">
    <div id="massHolidays" class="border rounded p-3 mt-2" aria-live="polite">Seleccione las fechas para ver los feriados.</div>
    <small class="text-muted">Los feriados marcados se omiten. Desmarque un feriado solo si se trabajará ese día.</small>
</div>
<div class="col-12 mb-2"><h6 class="schedule-mass-section-title mb-0">Grupos de trabajo</h6></div>
@forelse ($groups as $group)
    <div class="col-md-6 col-xl-4 mb-3 mass-group" data-shift="{{ $group->shift_id }}">
        <div class="card h-100 mass-group-card">
            <div class="card-header mass-group-header">
                <h6 class="mass-group-title">
                    {{ $group->name }}
                </h6>
                <input type="hidden" class="mass-group-id" name="group_ids[]" value="{{ $group->id }}">
                <button type="button" class="btn btn-sm btn-outline-danger mass-group-remove" title="Quitar grupo" aria-label="Quitar {{ $group->name }}">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="mass-group-meta small mb-3">
                    <div><strong>Zona:</strong> {{ $group->zone?->name }}</div>
                    <div><strong>Turno:</strong> <span class="badge mass-group-badge mass-group-shift" style="color:#fff !important; background:#0d6efd !important;">{{ $group->shift?->name ?? 'Turno no asignado' }}</span></div>
                    <div><strong>Días:</strong> {{ collect($group->days_of_week)->map(fn ($day) => $weekdays[$day] ?? '')->implode(', ') }}</div>
                    <div><strong>Vehículo:</strong> <span class="badge mass-group-badge mass-group-vehicle" style="color:#154a83 !important; background:#e7f2ff !important; border:1px solid #cfe2ff;">{{ $group->vehicle ? $group->vehicle->code.' (Capacidad: '.$group->vehicle->occupant_capacity.')' : 'Vehículo no asignado' }}</span></div>
                </div>
                <label for="mass_driver_{{ $group->id }}">Conductor</label>
                <select id="mass_driver_{{ $group->id }}" name="groups[{{ $group->id }}][driver_id]" class="form-control schedule-select2 schedule-input mass-crew" required>
                    @foreach ($drivers as $driver)
                        <option value="{{ $driver->id }}" @selected($group->driver_id == $driver->id)>{{ $driver->full_name }} · {{ $driver->dni }}</option>
                    @endforeach
                </select>
                <div class="mass-crew-feedback d-none" aria-live="polite"></div>
                <label for="mass_helpers_{{ $group->id }}" class="mt-3">Ayudantes</label>
                <select id="mass_helpers_{{ $group->id }}" name="groups[{{ $group->id }}][helper_ids][]" class="form-control schedule-select2 schedule-input mass-crew" multiple required>
                    @foreach ($helpers as $helper)
                        <option value="{{ $helper->id }}" @selected($group->helpers->contains('id', $helper->id))>{{ $helper->full_name }} · {{ $helper->dni }}</option>
                    @endforeach
                </select>
                <div class="mass-crew-feedback d-none" aria-live="polite"></div>
            </div>
        </div>
    </div>
@empty
    <div class="col-12"><div class="alert alert-warning">No hay grupos activos para programar.</div></div>
@endforelse
