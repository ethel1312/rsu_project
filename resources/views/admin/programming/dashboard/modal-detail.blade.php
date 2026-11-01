<div class="modal-header dashboard-editor-header">
    <h5 class="modal-title" id="dashboardDetailsModalTitle"><i class="bi bi-pencil-square mr-2"></i>Editor de Programación</h5>
    <button type="button" class="close text-white dashboard-modal-dismiss" aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
</div>
<div class="modal-body dashboard-editor-body">
    <div class="dashboard-editor-context mb-3"><strong>{{ $schedule->zone_name }}</strong><span>{{ $schedule->group_name }} · {{ $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '' }}</span></div>

    @if(!$editable)
        <div class="alert alert-info">{{ $editBlockReason ?: 'Este equipo se muestra en modo consulta y no admite cambios desde el dashboard.' }}</div>
    @endif

    @if($editable)
        <form id="dashboardReplacementForm" data-candidates-url="{{ route('admin.programming.dashboard.show', ['schedule' => $schedule, 'date' => $date]) }}" method="post" action="{{ route('admin.programming.dashboard.update', $schedule) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="date" value="{{ $date }}">
    @endif

    <div class="row">
        <div class="col-lg-6 mb-3">
            <section class="dashboard-editor-panel h-100">
                <h6 class="dashboard-panel-title bg-primary"><i class="bi bi-clock mr-2"></i>Cambio de Turno</h6>
                <div class="dashboard-panel-content">
                    <div class="form-group mb-3">
                        <label for="dashboardCurrentShift">Turno Actual</label>
                        <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="bi bi-clock-history"></i></span></div><input id="dashboardCurrentShift" class="form-control" value="{{ $schedule->shift_name }} ({{ substr($schedule->shift_start_time,0,5) }}–{{ substr($schedule->shift_end_time,0,5) }})" readonly></div>
                    </div>
                    <div class="form-group mb-0 dashboard-edit-row">
                        <label for="dashboardNewShift">Nuevo Turno</label>
                        @if($editable)
                            <input type="hidden" name="changes[0][type]" value="shift"><input type="hidden" name="changes[0][old_id]" value="{{ $schedule->shift_id }}">
                        @endif
                        <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="bi bi-arrow-repeat"></i></span></div><select id="dashboardNewShift" class="form-control dashboard-change-select" name="changes[0][new_id]" data-old="{{ $schedule->shift_id }}" @disabled(!$editable)>
                            <option value="">Seleccione un nuevo turno</option>
                            @foreach($shifts->where('id', '!=', $schedule->shift_id) as $shift)<option value="{{ $shift->id }}">{{ $shift->name }} ({{ substr($shift->start_time,0,5) }}–{{ substr($shift->end_time,0,5) }})</option>@endforeach
                        </select></div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-lg-6 mb-3">
            <section class="dashboard-editor-panel h-100">
                <h6 class="dashboard-panel-title bg-success"><i class="bi bi-truck mr-2"></i>Cambio de Vehículo</h6>
                <div class="dashboard-panel-content">
                    <div class="form-group mb-3">
                        <label for="dashboardCurrentVehicle">Vehículo Actual</label>
                        <div class="input-group"><div class="input-group-prepend"><span class="input-group-text text-success"><i class="bi bi-clock-history"></i></span></div><input id="dashboardCurrentVehicle" class="form-control" value="{{ $schedule->vehicle_label }}" readonly></div>
                    </div>
                    <div class="form-group mb-0 dashboard-edit-row">
                        <label for="dashboardNewVehicle">Nuevo Vehículo</label>
                        @if($editable)
                            <input type="hidden" name="changes[1][type]" value="vehicle"><input type="hidden" name="changes[1][old_id]" value="{{ $schedule->vehicle_id }}">
                        @endif
                        <div class="input-group"><div class="input-group-prepend"><span class="input-group-text text-success"><i class="bi bi-arrow-repeat"></i></span></div><select id="dashboardNewVehicle" class="form-control dashboard-change-select" name="changes[1][new_id]" data-old="{{ $schedule->vehicle_id }}" @disabled(!$editable)>
                            <option value="">Seleccione un nuevo vehículo</option>
                            @foreach($vehicles->where('id', '!=', $schedule->vehicle_id) as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->plate }} · {{ $vehicle->name }} (Capacidad: {{ $vehicle->occupant_capacity }})</option>@endforeach
                        </select></div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="dashboard-editor-panel dashboard-personnel-panel mb-3">
        <h6 class="dashboard-panel-title bg-info"><i class="bi bi-people-fill mr-2"></i>Cambio de Personal</h6>
        <div class="dashboard-panel-content">
            @foreach($crew as $index => $member)
                @php($employeeName = $member['employee']?->full_name ?? 'Personal #'.$member['id'])
                @php($changeIndex = $index + 2)
                <div class="dashboard-personnel-row">
                    <div class="form-group mb-0">
                        <label for="dashboardCurrentPerson{{ $index }}">Personal Actual</label>
                        <div class="input-group"><div class="input-group-prepend"><span class="input-group-text text-primary"><i class="bi bi-person-fill"></i></span></div><input id="dashboardCurrentPerson{{ $index }}" class="form-control" value="{{ $employeeName }} ({{ $member['role'] === 'driver' ? 'conductor' : 'ayudante' }})" readonly></div>
                    </div>
                    <div class="form-group mb-0 dashboard-edit-row">
                        <label for="dashboardNewPerson{{ $index }}">Nuevo Personal</label>
                        @if($editable && !$member['present'])
                            <input type="hidden" name="changes[{{ $changeIndex }}][type]" value="{{ $member['role'] }}"><input type="hidden" name="changes[{{ $changeIndex }}][old_id]" value="{{ $member['id'] }}">
                            <div class="input-group"><div class="input-group-prepend"><span class="input-group-text text-primary"><i class="bi bi-person-plus-fill"></i></span></div><select id="dashboardNewPerson{{ $index }}" class="form-control dashboard-change-select dashboard-person-select" name="changes[{{ $changeIndex }}][new_id]" data-old="{{ $member['id'] }}">
                                <option value="">Seleccione personal con asistencia en este turno</option>
                                @foreach($candidates[$member['id']] ?? [] as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->full_name }} · DNI {{ $candidate->dni }}</option>@endforeach
                            </select></div>
                        @elseif($member['present'])
                            <div class="input-group"><div class="input-group-prepend"><span class="input-group-text text-success"><i class="bi bi-person-check-fill"></i></span></div><input id="dashboardNewPerson{{ $index }}" class="form-control" value="Personal asistió; no requiere reemplazo" readonly></div>
                        @elseif($editable)
                            <div class="input-group"><div class="input-group-prepend"><span class="input-group-text text-muted"><i class="bi bi-person-plus-fill"></i></span></div><input id="dashboardNewPerson{{ $index }}" class="form-control" value="No hay personal disponible que haya asistido" readonly></div>
                        @else
                            <div class="input-group"><div class="input-group-prepend"><span class="input-group-text text-muted"><i class="bi bi-person-plus-fill"></i></span></div><input id="dashboardNewPerson{{ $index }}" class="form-control" value="{{ $member['present'] ? 'Asistió' : 'Faltante' }}" readonly></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @if($editable)
        <section class="dashboard-reason-panel">
            <h6 class="font-weight-bold mb-3">Motivo del cambio</h6>
            <div class="form-group">
                <label for="dashboardReasonId">Motivo *</label>
                <select class="form-control" id="dashboardReasonId" name="reason_id" required>
                    <option value="">Seleccione un motivo</option>
                    @foreach($reasons as $reason)<option value="{{ $reason->id }}">{{ $reason->name }} · {{ $reason->description }}</option>@endforeach
                </select>
                @if($reasons->isEmpty())<small class="form-text text-danger">Registre primero un motivo en Gestión de Cambios.</small>@endif
            </div>
            <div class="form-group mb-0"><label for="dashboardReasonDetail">Detalle adicional</label><textarea class="form-control" id="dashboardReasonDetail" name="detail" maxlength="1000" rows="2" placeholder="Explique el motivo del cambio"></textarea></div>
        </section>
        <div class="alert alert-danger dashboard-form-alert d-none mt-3 mb-0" role="alert"></div>
        </form>
    @endif
    <section class="mt-3">
        <h6>Historial de cambios del día</h6>
        @forelse($schedule->changes()->with('actor')->whereDate('service_date', $date)->latest('id')->get() as $change)
            <div class="border rounded p-2 mb-2">
                <strong>{{ \App\Models\Programming\ScheduleChange::TYPES[$change->type] }}</strong>
                · {{ ['mass' => 'Masivo', 'individual' => 'Individual', 'dashboard' => 'Dashboard'][$change->source] ?? $change->source }}
                · {{ $change->reverted_at ? 'Revertido' : 'Aplicado' }}
                <div>{{ $change->reason_name }} — {{ $change->detail }}</div>
                <small class="text-muted">{{ $change->actor?->name }} · {{ $change->created_at?->timezone('America/Lima')->format('d/m/Y H:i') }}</small>
                @can('changes.view')
                    <a class="ml-2" href="{{ route('admin.programming.changes.show', $change) }}">Ver detalle</a>
                @endcan
            </div>
        @empty
            <p class="text-muted">No hay cambios registrados para este día.</p>
        @endforelse
    </section>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary dashboard-modal-dismiss">Cancelar</button>
    @if($editable)<button type="submit" form="dashboardReplacementForm" class="btn btn-primary" @disabled($reasons->isEmpty())><i class="bi bi-check-lg mr-1"></i> Guardar cambios</button>@endif
</div>
<style>
    .dashboard-editor-header { position:relative; display:flex; align-items:center; justify-content:space-between; padding-right:1rem; background:#082f59; color:#fff; }
    .dashboard-editor-header .close { position:absolute; top:50%; right:.8rem; display:flex; align-items:center; justify-content:center; width:36px; height:36px; margin:0; padding:0; transform:translateY(-50%); color:#fff; background:transparent; border:0; font-size:1.2rem; line-height:1; opacity:.9; }
    .dashboard-editor-context { display:flex; justify-content:space-between; flex-wrap:wrap; gap:6px 16px; color:#526277; }
    .dashboard-editor-context strong { color:#173a70; }
    .dashboard-editor-panel { overflow:hidden; border:1px solid #e0e6ed; border-radius:7px; background:#fff; box-shadow:0 2px 6px #173a7010; }
    .dashboard-panel-title { margin:0; padding:10px 14px; color:#fff; font-size:1rem; font-weight:600; }
    .dashboard-panel-content { padding:16px 18px; }
    .dashboard-editor-panel label { display:block; color:#34445b; font-weight:600; margin-bottom:6px; }
    .dashboard-editor-panel .form-control[readonly] { background:#e9ecef; color:#65717e; }
    .dashboard-editor-panel .input-group-text { min-width:40px; justify-content:center; }
    .dashboard-editor-panel .input-group-text i { font-size:1rem; }
    .dashboard-personnel-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; padding:12px; border:1px solid #dce3eb; border-radius:6px; }
    .dashboard-personnel-row + .dashboard-personnel-row { margin-top:12px; }
    .dashboard-reason-panel { padding:16px; border:1px solid #dce3eb; border-radius:7px; background:#f8fafc; }
    @media(max-width:767.98px) { .dashboard-personnel-row { grid-template-columns:1fr; } .dashboard-panel-content { padding:13px; } }
</style>
