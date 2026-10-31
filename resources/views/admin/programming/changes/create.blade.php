<form id="massChangeForm" method="post">
    @csrf
    <div id="massChangeError" class="alert alert-danger d-none" role="alert"></div>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-3">
            <label for="massStartDate">Fecha de Inicio *</label>
            <input class="form-control" type="date" id="massStartDate" name="start_date" min="{{ now('America/Lima')->toDateString() }}" required>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <label for="massEndDate">Fecha de Fin *</label>
            <input class="form-control" type="date" id="massEndDate" name="end_date" min="{{ now('America/Lima')->toDateString() }}" max="{{ now('America/Lima')->addYear()->toDateString() }}" required>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <label for="massZoneIds">Zonas (Opcional)</label>
            <select class="form-control" id="massZoneIds" name="zone_ids[]" multiple data-placeholder="Seleccione las zonas">
                @foreach($zones as $zone)<option value="{{ $zone->id }}">{{ $zone->name }}</option>@endforeach
            </select>
            <small class="mass-change-help">Déjelo vacío para aplicar a todas las zonas.</small>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <label for="massChangeType">Tipo de Cambio *</label>
            <select class="form-control" id="massChangeType" name="type" required>
                @foreach(\App\Models\Programming\ScheduleChange::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="massOldId">Recurso a Reemplazar *</label>
            <select class="form-control mass-resource-select" id="massOldId" name="old_id" required>
                <option value="">Seleccione un recurso</option>
                @foreach($shifts as $shift)<option data-kind="shift" value="{{ $shift->id }}">{{ $shift->name }} ({{ substr($shift->start_time,0,5) }}–{{ substr($shift->end_time,0,5) }})</option>@endforeach
                @foreach($vehicles as $vehicle)<option data-kind="vehicle" value="{{ $vehicle->id }}">{{ $vehicle->plate }} · {{ $vehicle->name }}</option>@endforeach
                @foreach($employees as $employee)
                    @if($employee->employeeType?->name === 'Conductor')<option data-kind="driver" value="{{ $employee->id }}">{{ $employee->full_name }} · DNI {{ $employee->dni }}</option>@endif
                    @if($employee->employeeType?->name === 'Ayudante')<option data-kind="helper" value="{{ $employee->id }}">{{ $employee->full_name }} · DNI {{ $employee->dni }}</option>@endif
                @endforeach
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label for="massNewId">Nuevo Recurso *</label>
            <select class="form-control mass-resource-select" id="massNewId" name="new_id" required>
                <option value="">Seleccione un nuevo recurso</option>
                @foreach($shifts as $shift)<option data-kind="shift" value="{{ $shift->id }}">{{ $shift->name }} ({{ substr($shift->start_time,0,5) }}–{{ substr($shift->end_time,0,5) }})</option>@endforeach
                @foreach($vehicles as $vehicle)<option data-kind="vehicle" value="{{ $vehicle->id }}">{{ $vehicle->plate }} · {{ $vehicle->name }}</option>@endforeach
                @foreach($employees as $employee)
                    @if($employee->employeeType?->name === 'Conductor')<option data-kind="driver" value="{{ $employee->id }}">{{ $employee->full_name }} · DNI {{ $employee->dni }}</option>@endif
                    @if($employee->employeeType?->name === 'Ayudante')<option data-kind="helper" value="{{ $employee->id }}">{{ $employee->full_name }} · DNI {{ $employee->dni }}</option>@endif
                @endforeach
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="massReasonId">Motivo Predefinido *</label>
            <select class="form-control" id="massReasonId" name="reason_id" required>
                <option value="">Seleccione un motivo predefinido</option>
                @foreach($reasons as $reason)
                    <option value="{{ $reason->id }}" data-name="{{ $reason->name }}">{{ $reason->name }}{{ $reason->description ? ' · '.$reason->description : '' }}</option>
                @endforeach
            </select>
            <small class="mass-change-help">Seleccione el motivo que se registrará en el historial.</small>
        </div>
        <div class="col-md-6 mb-3">
            <label for="massAdditionalDetail">Descripción Adicional (Opcional)</label>
            <textarea class="form-control" id="massAdditionalDetail" maxlength="1000" rows="2" placeholder="Agregue detalles adicionales"></textarea>
            <small class="mass-change-help">Complemento al motivo predefinido.</small>
        </div>
    </div>

    <div class="form-group mb-3">
        <label for="massCompleteDetail">Descripción Completa del Cambio *</label>
        <textarea class="form-control mass-change-complete" id="massCompleteDetail" name="detail" rows="3" maxlength="2000" placeholder="Se completará al seleccionar un motivo" readonly required></textarea>
        <small class="mass-change-help">Se completa con el motivo seleccionado y los detalles adicionales.</small>
    </div>

    <div class="alert alert-info mb-3"><i class="bi bi-info-circle-fill me-1"></i> Se revisarán todas las programaciones afectadas antes de aplicar el cambio. La operación se guardará solo si todas las fechas cumplen las reglas de disponibilidad.</div>
    <div class="d-flex justify-content-end flex-wrap gap-2">
        <button type="button" class="btn btn-modal-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cancelar</button>
        <button type="submit" class="btn btn-modal-guardar" id="massPreviewButton"><i class="bi bi-search me-1"></i> Revisar cambios</button>
    </div>
</form>

<section id="massPreview" class="card operation-card mt-4 d-none">
    <div class="card-header bg-primary text-white font-weight-bold">Confirmar operación masiva</div>
    <div class="card-body">
        <p id="massPreviewSummary" class="font-weight-bold"></p>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead><tr><th>Programación</th><th>Fecha</th><th>Zona</th><th>Antes</th><th>Después</th></tr></thead>
                <tbody id="massPreviewRows"></tbody>
            </table>
        </div>
        <p class="text-muted">Cada programación conservará el detalle del cambio en su historial. Si una fecha ya no está disponible, deberá revisar el cambio antes de confirmarlo.</p>
        <div class="d-flex justify-content-end flex-wrap gap-2">
            <button type="button" id="massCancelPreview" class="btn btn-outline-secondary">Volver a editar</button>
            <button type="button" id="massConfirmButton" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Confirmar y aplicar</button>
        </div>
    </div>
</section>
