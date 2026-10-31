@php
    $typeMeta = [
        'shift' => ['label' => 'Turno', 'icon' => 'bi-clock-fill', 'badge' => 'warning'],
        'vehicle' => ['label' => 'Vehículo', 'icon' => 'bi-truck', 'badge' => 'primary'],
        'driver' => ['label' => 'Conductor', 'icon' => 'bi-person-fill', 'badge' => 'info'],
        'helper' => ['label' => 'Ayudante', 'icon' => 'bi-people-fill', 'badge' => 'success'],
    ][$change->type] ?? ['label' => 'Cambio', 'icon' => 'bi-arrow-left-right', 'badge' => 'secondary'];
    $rowsFor = function (array $values) use ($change) {
        return match ($change->type) {
            'shift' => [
                ['label' => 'Turno', 'value' => $values['shift_name'] ?? '—', 'icon' => 'bi-clock-fill', 'tone' => 'danger'],
                ['label' => 'Hora de entrada', 'value' => $values['shift_start_time'] ?? '—', 'icon' => 'bi-box-arrow-in-right', 'tone' => 'info'],
                ['label' => 'Hora de salida', 'value' => $values['shift_end_time'] ?? '—', 'icon' => 'bi-box-arrow-right', 'tone' => 'success'],
            ],
            'vehicle' => [
                ['label' => 'Vehículo', 'value' => $values['vehicle_label'] ?? '—', 'icon' => 'bi-truck', 'tone' => 'info'],
            ],
            'driver' => [
                ['label' => 'Conductor', 'value' => $values['driver_name'] ?? '—', 'icon' => 'bi-person-fill', 'tone' => 'info'],
            ],
            'helper' => [
                ['label' => 'Ayudantes', 'value' => implode(', ', $values['helper_names'] ?? []) ?: '—', 'icon' => 'bi-people-fill', 'tone' => 'success'],
            ],
            default => [['label' => 'Valor', 'value' => '—', 'icon' => 'bi-info-circle', 'tone' => 'info']],
        };
    };
    $beforeRows = $rowsFor($change->before_data ?? []);
    $afterRows = $rowsFor($change->after_data ?? []);
    $sourceLabel = ['mass' => 'Masivo', 'individual' => 'Individual', 'dashboard' => 'Dashboard'][$change->source] ?? $change->source;
@endphp

<div class="modal-header change-detail-header">
    <div class="change-detail-heading">
        <h5 class="modal-title" id="changeDetailModalLabel"><i class="bi bi-arrow-left-right me-2"></i>Detalles del Cambio #{{ $change->id }}</h5>
        <div class="change-detail-timestamp"><i class="bi bi-calendar3 me-1"></i>{{ $change->created_at?->timezone('America/Lima')->format('d/m/Y H:i') }}</div>
    </div>
    <div class="change-detail-header-right">
        <span class="badge bg-{{ $typeMeta['badge'] }}"><i class="bi {{ $typeMeta['icon'] }} me-1"></i>{{ $typeMeta['label'] }}</span>
        @if($change->reverted_at)<span class="badge bg-secondary">Revertido</span>@endif
    </div>
    <button type="button" class="btn-close btn-close-white change-detail-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body change-detail-body">
    @if($change->reverted_at)
        <div class="alert alert-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i> Revertido el {{ $change->reverted_at->timezone('America/Lima')->format('d/m/Y H:i') }} por {{ $change->reverter?->name ?? 'Usuario eliminado' }}.</div>
    @endif

    <div class="row change-detail-values">
        <div class="col-md-6 mb-3">
            <section class="change-detail-value-card h-100">
                <header class="change-detail-value-heading previous"><span class="change-detail-direction"><i class="bi bi-arrow-left"></i></span><div><strong>Valores Anteriores</strong><small>Estado previo al cambio</small></div></header>
                <div class="change-detail-value-body">
                    @foreach($beforeRows as $row)
                        <div class="change-detail-field">
                            <span class="change-detail-field-icon {{ $row['tone'] }}"><i class="bi {{ $row['icon'] }}"></i></span>
                            <div><span class="change-detail-field-label">{{ $row['label'] }}</span><strong>{{ $row['value'] }}</strong></div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
        <div class="col-md-6 mb-3">
            <section class="change-detail-value-card h-100">
                <header class="change-detail-value-heading next"><span class="change-detail-direction"><i class="bi bi-arrow-right"></i></span><div><strong>Valores Nuevos</strong><small>Estado después del cambio</small></div></header>
                <div class="change-detail-value-body">
                    @foreach($afterRows as $row)
                        <div class="change-detail-field">
                            <span class="change-detail-field-icon {{ $row['tone'] }}"><i class="bi {{ $row['icon'] }}"></i></span>
                            <div><span class="change-detail-field-label">{{ $row['label'] }}</span><strong>{{ $row['value'] }}</strong></div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    <section class="change-detail-info">
        <header class="change-detail-info-heading"><span class="change-detail-info-icon"><i class="bi bi-info-circle-fill"></i></span><div><strong>Información del Cambio</strong><small>Detalles adicionales del proceso</small></div></header>
        <div class="change-detail-info-body">
            <div class="row">
                <div class="col-xl-3 col-6 mb-3"><div class="change-detail-info-tile"><span class="change-detail-info-tile-icon blue"><i class="bi bi-arrow-left-right"></i></span><span class="change-detail-field-label">Tipo de cambio</span><strong><span class="badge bg-{{ $typeMeta['badge'] }}">{{ $typeMeta['label'] }}</span></strong></div></div>
                <div class="col-xl-3 col-6 mb-3"><div class="change-detail-info-tile"><span class="change-detail-info-tile-icon navy"><i class="bi bi-person-gear"></i></span><span class="change-detail-field-label">Realizado por</span><strong>{{ $change->actor?->name ?? 'Usuario eliminado' }}</strong>@if($change->actor?->email)<small>{{ $change->actor->email }}</small>@endif</div></div>
                <div class="col-xl-3 col-6 mb-3"><div class="change-detail-info-tile"><span class="change-detail-info-tile-icon yellow"><i class="bi bi-calendar-event-fill"></i></span><span class="change-detail-field-label">Fecha del cambio</span><strong>{{ $change->created_at?->timezone('America/Lima')->format('d/m/Y H:i') }}</strong></div></div>
                <div class="col-xl-3 col-6 mb-3"><div class="change-detail-info-tile"><span class="change-detail-info-tile-icon green"><i class="bi bi-card-checklist"></i></span><span class="change-detail-field-label">Programación</span><strong>#{{ $change->programming_schedule_id }}</strong><small>{{ $change->schedule?->zone_name ?? 'Zona no disponible' }} · {{ $change->service_date->format('d/m/Y') }}</small></div></div>
            </div>
            <div class="change-detail-reason"><h6><i class="bi bi-chat-dots-fill me-1"></i> Motivo del Cambio</h6><strong>{{ $change->reason_name }}</strong><p class="mb-0">{{ $change->detail ?: 'Sin descripción adicional.' }}</p></div>
            <div class="change-detail-source"><span>Origen:</span> {{ $sourceLabel }}</div>
        </div>
    </section>
</div>

<div class="modal-footer change-detail-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cerrar</button></div>

<style>
    #changeDetailContent { overflow:hidden; border:0; border-radius:12px; box-shadow:0 18px 45px #11182735; }
    .change-detail-header { position:relative; display:flex; align-items:center; justify-content:space-between; gap:18px; min-height:100px; padding:20px 62px 20px 24px; color:#fff; background:#075985; }
    .change-detail-heading .modal-title { color:#fff; font-size:1.35rem; font-weight:700; }
    .change-detail-timestamp { margin-top:5px; color:#d3e6f5; }
    .change-detail-header-right { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .change-detail-header .badge { padding:9px 13px; border-radius:20px; font-size:.9rem; }
    .change-detail-close { position:absolute; top:16px; right:16px; }
    .change-detail-body { padding:22px; }
    .change-detail-value-card { overflow:hidden; border:1px solid #e3e8ef; border-radius:10px; background:#fff; box-shadow:0 5px 16px #172b4d14; }
    .change-detail-value-heading { display:flex; align-items:center; gap:12px; min-height:70px; padding:13px 17px; color:#fff; }
    .change-detail-value-heading.previous { background:#dc3545; }
    .change-detail-value-heading.next { background:#28a745; }
    .change-detail-value-heading strong,.change-detail-value-heading small { display:block; }
    .change-detail-value-heading strong { font-size:1rem; }
    .change-detail-value-heading small { margin-top:2px; opacity:.9; }
    .change-detail-direction { display:flex; width:40px; height:40px; flex:0 0 40px; align-items:center; justify-content:center; border-radius:10px; background:#ffffff35; font-size:1.2rem; }
    .change-detail-value-body { padding:13px 18px; }
    .change-detail-field { display:flex; align-items:center; gap:12px; min-height:62px; padding:10px 0; }
    .change-detail-field + .change-detail-field { border-top:1px solid #e7ebf0; }
    .change-detail-field-icon { display:flex; width:40px; height:40px; flex:0 0 40px; align-items:center; justify-content:center; border-radius:10px; font-size:1.1rem; }
    .change-detail-field-icon.danger { color:#dc3545; background:#fde8eb; }
    .change-detail-field-icon.info { color:#0786c4; background:#e3f6fb; }
    .change-detail-field-icon.success { color:#28a745; background:#e5f5e9; }
    .change-detail-field-label { display:block; color:#718096; font-size:.76rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .change-detail-field strong { display:block; margin-top:2px; color:#34445b; overflow-wrap:anywhere; }
    .change-detail-info { overflow:hidden; border:1px solid #dce4eb; border-radius:10px; background:#fff; box-shadow:0 5px 16px #172b4d14; }
    .change-detail-info-heading { display:flex; align-items:center; gap:12px; padding:13px 17px; color:#fff; background:#27aabd; }
    .change-detail-info-heading strong,.change-detail-info-heading small { display:block; }
    .change-detail-info-heading strong { font-size:1rem; }
    .change-detail-info-heading small { margin-top:2px; opacity:.92; }
    .change-detail-info-icon { display:flex; width:40px; height:40px; flex:0 0 40px; align-items:center; justify-content:center; border-radius:10px; background:#ffffff35; font-size:1.1rem; }
    .change-detail-info-body { padding:18px; }
    .change-detail-info-tile { display:flex; min-height:155px; flex-direction:column; align-items:center; justify-content:center; gap:7px; padding:14px 10px; border-radius:12px; background:#f5f7fa; text-align:center; overflow-wrap:anywhere; }
    .change-detail-info-tile-icon { display:flex; width:54px; height:54px; align-items:center; justify-content:center; border-radius:14px; color:#fff; font-size:1.4rem; }
    .change-detail-info-tile-icon.blue { background:#0786c4; }
    .change-detail-info-tile-icon.navy { background:#083b66; }
    .change-detail-info-tile-icon.yellow { color:#182235; background:#ffbe0b; }
    .change-detail-info-tile-icon.green { background:#28a745; }
    .change-detail-info-tile strong { color:#34445b; }
    .change-detail-info-tile small { display:block; color:#718096; }
    .change-detail-reason { padding:14px 16px; border-radius:10px; color:#126b7b; background:#e5f5f8; }
    .change-detail-reason h6 { margin-bottom:8px; font-weight:700; }
    .change-detail-reason strong { display:block; margin-bottom:4px; }
    .change-detail-source { margin-top:12px; color:#718096; font-size:.85rem; }
    .change-detail-source span { font-weight:700; }
    .change-detail-footer { padding:12px 20px; }
    @media(max-width:575.98px) { .change-detail-header { align-items:flex-start; flex-direction:column; padding-left:18px; } .change-detail-body { padding:14px; } .change-detail-info-body { padding:12px; } .change-detail-info-tile { min-height:135px; } }
</style>
