@extends('adminlte::page')

@section('title', 'Mapa de zonas')

@section('content_header')
    <div class="rsu-title-bar d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px;">
        <h1 class="rsu-title m-0">
            <span class="rsu-title-ico"><i class="bi bi-map"></i></span>
            Mapa de zonas
        </h1>
        <a href="{{ route('admin.programming.zones.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver a zonas
        </a>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        #zonesMap { height: 580px; width: 100%; background: #f1f5f9; border-radius: 8px; z-index: 1; }
        .zone-legend { max-height: 530px; overflow-y: auto; }
        .zone-legend-item { width: 100%; padding: 12px; text-align: left; color: var(--bs-body-color); background: var(--bs-body-bg); border: 0; border-bottom: 1px solid var(--bs-border-color); }
        .zone-legend-item:hover:not(:disabled), .zone-legend-item:focus-visible { background: var(--bs-tertiary-bg); }
        .zone-legend-item:disabled { opacity: .75; }
        .zone-legend-dot { display: inline-block; width: 14px; height: 14px; margin-right: 8px; border: 2px solid; border-radius: 3px; flex-shrink: 0; }
        .zone-map-popup p { margin: 5px 0 0; }
        @media (max-width: 767px) { #zonesMap { height: 430px; } .zone-legend { max-height: 240px; } }
    </style>
@stop

@section('content')
    @if ($mapZones->isEmpty())
        <div class="alert alert-info" role="status">
            Todavía no hay zonas registradas. Registre una zona y su perímetro para verla en el mapa.
        </div>
    @elseif ($mapZones->contains('has_valid_coordinates', false))
        <div class="alert alert-warning" role="status">
            Algunas zonas no tienen un perímetro válido. Edite sus coordenadas para mostrarlas en el mapa.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-9 order-lg-2">
            <div class="card">
                <div class="card-header d-flex align-items-center flex-wrap" style="gap: 8px;">
                    <p class="mb-0 mr-auto">Todas las zonas registradas, activas e inactivas.</p>
                    <button type="button" id="fitAllZones" class="btn btn-sm btn-outline-primary" @disabled(!$mapZones->contains('has_valid_coordinates', true))>
                        <i class="bi bi-arrows-fullscreen"></i> Ver todas
                    </button>
                </div>
                <div class="card-body p-2">
                    <div id="zonesMap" aria-label="Mapa de los perímetros de todas las zonas"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 order-lg-1">
            <div class="card">
                <div class="card-header"><strong><i class="bi bi-funnel"></i> Filtros de ubicación</strong></div>
                <div class="card-body">
                    @foreach (['department' => 'Departamento', 'province' => 'Provincia', 'district' => 'Distrito'] as $field => $label)
                        <div class="form-group mb-2">
                            <label for="filter-{{ $field }}">{{ $label }}</label>
                            <select id="filter-{{ $field }}" class="form-control zone-location-filter">
                                <option value="">Todos</option>
                                @foreach ($mapZones->pluck($field)->unique()->sort()->values() as $location)
                                    <option value="{{ $location }}">{{ $location }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <button type="button" id="clearZoneFilters" class="btn btn-sm btn-outline-secondary mt-1">Limpiar filtros</button>
                    <div class="row text-center mt-3" aria-live="polite">
                        <div class="col-4 px-1"><strong id="foundZones" class="d-block text-primary h4 mb-0">{{ $mapZones->count() }}</strong><small>Encontradas</small></div>
                        <div class="col-4 px-1"><strong id="activeZones" class="d-block text-success h4 mb-0">{{ $mapZones->where('status', true)->count() }}</strong><small>Activas</small></div>
                        <div class="col-4 px-1"><strong id="totalZonePoints" class="d-block text-info h4 mb-0">{{ $mapZones->sum(fn ($zone) => count($zone['coordinates'])) }}</strong><small>Puntos</small></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><strong>Zonas registradas ({{ $mapZones->count() }})</strong></div>
                <div id="zonesLegend" class="zone-legend">
                    @forelse ($mapZones as $mapZone)
                        <button type="button" class="zone-legend-item" data-zone-id="{{ $mapZone['id'] }}" @disabled(!$mapZone['has_valid_coordinates']) aria-label="Ver zona {{ $mapZone['name'] }} en el mapa">
                            <span class="d-flex align-items-center">
                                <span class="zone-legend-dot" aria-hidden="true"></span>
                                <strong>{{ $mapZone['name'] }}</strong>
                            </span>
                            <span class="d-block small text-muted mt-1">{{ $mapZone['district'] }}</span>
                            <span class="badge {{ $mapZone['status'] ? 'bg-success' : 'bg-secondary' }} mt-1">{{ $mapZone['status'] ? 'Activo' : 'Inactivo' }}</span>
                            @unless ($mapZone['has_valid_coordinates'])
                                <span class="badge bg-warning text-dark mt-1">Perímetro inválido</span>
                            @endunless
                        </button>
                    @empty
                        <p class="text-muted p-3 mb-0">Aún no hay zonas.</p>
                    @endforelse
                </div>
                <p id="noMatchingZones" class="text-muted p-3 mb-0" hidden>No se encontraron zonas con estos filtros.</p>
                <div class="card-footer small text-muted">Seleccione una zona para acercarse. Las zonas inactivas tienen borde discontinuo.</div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const zones = {{ Illuminate\Support\Js::from($mapZones) }};
            const map = L.map('zonesMap').setView([-6.7714, -79.8409], 13);
            const allZones = L.featureGroup().addTo(map);
            const colors = ['#2563eb', '#9333ea', '#059669', '#ea580c', '#db2777', '#0891b2', '#a16207', '#4f46e5'];
            const polygons = new Map();

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            function popupFor(zone) {
                const popup = document.createElement('div');
                popup.className = 'zone-map-popup';
                const name = document.createElement('strong');
                name.textContent = zone.name;
                popup.appendChild(name);

                const lines = [
                    [zone.district, zone.province, zone.department].filter(Boolean).join(', '),
                    'Estado: ' + (zone.status ? 'Activo' : 'Inactivo'),
                    zone.description
                ];
                if (zone.average_waste_kg !== null) {
                    lines.push('Residuos promedio: ' + Number(zone.average_waste_kg).toLocaleString('es-PE', { maximumFractionDigits: 2 }) + ' kg/día');
                }
                lines.filter(Boolean).forEach(function (text) {
                    const line = document.createElement('p');
                    line.textContent = text;
                    popup.appendChild(line);
                });
                return popup;
            }

            zones.forEach(function (zone, index) {
                const color = colors[index % colors.length];
                const entry = document.querySelector('[data-zone-id="' + zone.id + '"]');
                if (entry) {
                    const dot = entry.querySelector('.zone-legend-dot');
                    dot.style.borderColor = color;
                    dot.style.backgroundColor = color + '33';
                    if (!zone.status) dot.style.borderStyle = 'dashed';
                }
                if (!zone.has_valid_coordinates) return;

                const label = document.createElement('span');
                label.textContent = zone.name;
                const polygon = L.polygon(zone.coordinates, {
                    color: color,
                    fillColor: color,
                    fillOpacity: zone.status ? .22 : .08,
                    weight: 3,
                    dashArray: zone.status ? null : '7 5'
                }).bindPopup(popupFor(zone)).bindTooltip(label, { sticky: true });
                polygons.set(zone.id, polygon);
            });

            function fitAll() {
                const bounds = allZones.getBounds();
                if (bounds.isValid()) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
            }

            document.getElementById('fitAllZones').addEventListener('click', fitAll);
            const locationFields = ['department', 'province', 'district'];

            function selectedLocations() {
                return Object.fromEntries(locationFields.map(field => [field, document.getElementById('filter-' + field).value]));
            }

            function matchesLocation(zone, filters) {
                return locationFields.every(field => !filters[field] || zone[field] === filters[field]);
            }

            function refreshMap() {
                const filters = selectedLocations();
                const matching = zones.filter(zone => matchesLocation(zone, filters));
                allZones.clearLayers();
                let pointCount = 0;
                zones.forEach(function (zone) {
                    const visible = matchesLocation(zone, filters);
                    const entry = document.querySelector('[data-zone-id="' + zone.id + '"]');
                    if (entry) entry.hidden = !visible;
                    if (visible && polygons.has(zone.id)) {
                        allZones.addLayer(polygons.get(zone.id));
                        pointCount += zone.coordinates.length;
                    }
                });
                document.getElementById('foundZones').textContent = matching.length;
                document.getElementById('activeZones').textContent = matching.filter(zone => zone.status).length;
                document.getElementById('totalZonePoints').textContent = pointCount;
                document.getElementById('noMatchingZones').hidden = matching.length > 0 || zones.length === 0;
                document.getElementById('fitAllZones').disabled = allZones.getLayers().length === 0;
                if (allZones.getLayers().length === 0) map.setView([-6.7714, -79.8409], 13);
                else fitAll();
            }

            function updateDependentFilters(changedIndex) {
                for (let index = changedIndex + 1; index < locationFields.length; index++) {
                    const field = locationFields[index];
                    const select = document.getElementById('filter-' + field);
                    const previous = select.value;
                    const filters = selectedLocations();
                    const options = [...new Set(zones.filter(zone => locationFields.slice(0, index).every(parent => !filters[parent] || zone[parent] === filters[parent])).map(zone => zone[field]))].sort();
                    select.replaceChildren(new Option('Todos', ''));
                    options.forEach(location => select.add(new Option(location, location)));
                    if (options.includes(previous)) select.value = previous;
                }
            }

            locationFields.forEach(function (field, index) {
                document.getElementById('filter-' + field).addEventListener('change', function () {
                    updateDependentFilters(index);
                    refreshMap();
                });
            });
            document.getElementById('clearZoneFilters').addEventListener('click', function () {
                locationFields.forEach(field => { document.getElementById('filter-' + field).value = ''; });
                updateDependentFilters(0);
                refreshMap();
            });
            document.querySelectorAll('.zone-legend-item:not(:disabled)').forEach(function (entry) {
                entry.addEventListener('click', function () {
                    const polygon = polygons.get(Number(entry.dataset.zoneId));
                    if (!polygon) return;
                    map.fitBounds(polygon.getBounds(), { padding: [30, 30], maxZoom: 16 });
                    polygon.openPopup();
                });
            });
            refreshMap();
        });
    </script>
@stop
