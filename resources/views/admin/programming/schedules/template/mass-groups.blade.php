<div class="col-12 mb-3">
    <label for="massShiftFilter">Filtrar grupos por turno</label>
    <select id="massShiftFilter" class="form-control schedule-input">
        <option value="">Todos los turnos</option>
        @foreach ($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }}</option>@endforeach
    </select>
    <small class="text-muted">Se generarán los grupos marcados del turno seleccionado. Puede ajustar su personal antes de validar.</small>
</div>
<div class="col-12 mb-3">
    <strong>Feriados en el rango seleccionado</strong>
    <input type="hidden" name="holidays_configured" id="holidaysConfigured" value="0">
    <div id="massHolidays" class="border rounded p-3 mt-2" aria-live="polite">Seleccione las fechas para ver los feriados.</div>
    <small class="text-muted">Los feriados marcados se omiten. Desmarque un feriado solo si se trabajará ese día.</small>
</div>
<div class="col-12"><h6>Grupos de trabajo</h6></div>
@forelse ($groups as $group)
    <div class="col-md-6 col-xl-4 mb-3 mass-group" data-shift="{{ $group->shift_id }}">
        <div class="card h-100">
            <div class="card-header">
                <label class="mb-0">
                    <input type="checkbox" class="mass-group-check schedule-input" name="group_ids[]" value="{{ $group->id }}" checked>
                    {{ $group->name }}
                </label>
            </div>
            <div class="card-body">
                <p class="small mb-2">
                    <strong>Zona:</strong> {{ $group->zone?->name }}<br>
                    <strong>Turno:</strong> {{ $group->shift?->name }}<br>
                    <strong>Vehículo:</strong> {{ $group->vehicle?->plate }} · {{ $group->vehicle?->name }}<br>
                    <strong>Días:</strong> {{ collect($group->days_of_week)->map(fn ($day) => $weekdays[$day] ?? '')->implode(', ') }}
                </p>
                <label for="mass_driver_{{ $group->id }}">Conductor</label>
                <select id="mass_driver_{{ $group->id }}" name="groups[{{ $group->id }}][driver_id]" class="form-control schedule-select2 schedule-input mass-crew" required>
                    @foreach ($drivers as $driver)
                        <option value="{{ $driver->id }}" @selected($group->driver_id == $driver->id)>{{ $driver->full_name }} · {{ $driver->dni }}</option>
                    @endforeach
                </select>
                <label for="mass_helpers_{{ $group->id }}" class="mt-3">Ayudantes</label>
                <select id="mass_helpers_{{ $group->id }}" name="groups[{{ $group->id }}][helper_ids][]" class="form-control schedule-select2 schedule-input mass-crew" multiple required>
                    @foreach ($helpers as $helper)
                        <option value="{{ $helper->id }}" @selected($group->helpers->contains('id', $helper->id))>{{ $helper->full_name }} · {{ $helper->dni }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
@empty
    <div class="col-12"><div class="alert alert-warning">No hay grupos activos para programar.</div></div>
@endforelse
