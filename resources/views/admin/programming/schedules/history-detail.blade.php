<div class="card history-detail-card mb-3">
    <div class="card-header history-personnel-header"><i class="bi bi-people-fill mr-1" aria-hidden="true"></i> Personal asignado</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Rol</th><th>Nombre</th></tr></thead>
                <tbody>
                    @if ($schedule->driver_name)
                        <tr>
                            <td><span class="history-role-badge history-role-driver"><i class="bi bi-person-fill mr-1" aria-hidden="true"></i>Conductor</span></td>
                            <td><span class="history-person-icon"><i class="bi bi-person-fill" aria-hidden="true"></i></span><strong>{{ $schedule->driver_name }}</strong></td>
                        </tr>
                    @endif
                    @foreach ($schedule->helper_names ?? [] as $helperName)
                        <tr>
                            <td><span class="history-role-badge history-role-helper"><i class="bi bi-people-fill mr-1" aria-hidden="true"></i>Ayudante</span></td>
                            <td><span class="history-person-icon is-helper"><i class="bi bi-person-fill" aria-hidden="true"></i></span><strong>{{ $helperName }}</strong></td>
                        </tr>
                    @endforeach
                    @if (! $schedule->driver_name && empty($schedule->helper_names))
                        <tr><td colspan="2" class="text-center text-muted py-3">Esta programación no tiene personal registrado.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card history-detail-card">
    <div class="card-header history-changes-header"><i class="bi bi-clock-history mr-1" aria-hidden="true"></i> Historial de cambios</div>
    <div class="card-body p-0">
        @if ($historyRows->isEmpty())
            <div class="alert alert-info m-3 mb-0">Aún no hay cambios registrados para esta programación.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Registro</th><th>Día / Origen</th><th>Tipo</th><th>Anterior</th><th>Nuevo</th><th>Motivo</th></tr></thead>
                    <tbody>
                        @foreach ($historyRows as $row)
                            <tr>
                                <td class="text-nowrap"><strong>{{ $row['date']?->format('d/m/Y') }}</strong><br><small class="text-muted">{{ $row['date']?->format('H:i') }}</small></td>
                                <td class="text-nowrap">
                                    {{ ($row['service_date'] ?? null)?->format('d/m/Y') ?? '—' }}<br>
                                    <span class="badge bg-secondary">{{ ['mass' => 'Masivo', 'individual' => 'Individual', 'dashboard' => 'Dashboard'][$row['source'] ?? ''] ?? 'Anterior' }}</span>
                                    @if(($row['action'] ?? '') === 'reverted')<span class="badge bg-warning">Reversión</span>@endif
                                    @if($row['operation_uuid'] ?? null)<small class="d-block text-muted" title="{{ $row['operation_uuid'] }}">Operación {{ substr($row['operation_uuid'], 0, 8) }}</small>@endif
                                </td>
                                <td><span class="history-type-badge"><i class="bi bi-arrow-left-right mr-1" aria-hidden="true"></i>{{ $row['type'] }}</span></td>
                                <td>{{ $row['before'] }}</td>
                                <td>{{ $row['after'] }}</td>
                                <td class="history-reason">{{ $row['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
