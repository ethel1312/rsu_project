<div class="row">
    <div class="col-md-12 mb-3">
        {!! Form::label('name', 'Nombre de la Zona *', ['class' => 'font-weight-bold']) !!}
        {!! Form::text('name', null, [
            'class' => 'form-control',
            'placeholder' => 'Nombre de la zona',
            'required'
        ]) !!}
    </div>

    <div class="col-md-4 mb-3">
        {!! Form::label('department', 'Departamento *', ['class' => 'font-weight-bold']) !!}
        {!! Form::select('department', ['Lambayeque' => 'Lambayeque'], null, [
            'class' => 'form-select', 'required'
        ]) !!}
    </div>

    <div class="col-md-4 mb-3">
        {!! Form::label('province', 'Provincia *', ['class' => 'font-weight-bold']) !!}
        {!! Form::select('province', ['Chiclayo' => 'Chiclayo'], null, [
            'class' => 'form-select', 'required'
        ]) !!}
    </div>

    <div class="col-md-4 mb-3">
        {!! Form::label('district', 'Distrito *', ['class' => 'font-weight-bold']) !!}
        {!! Form::select('district', ['Jose Leonardo Ortiz' => 'José Leonardo Ortiz', 'Chiclayo' => 'Chiclayo', 'La Victoria' => 'La Victoria', 'Pimentel' => 'Pimentel'] + (isset($zone) ? [$zone->district => $zone->district] : []), null, [
            'class' => 'form-select', 'required'
        ]) !!}
    </div>

    <div class="col-md-12 mb-3">
        {!! Form::label('description', 'Descripción', ['class' => 'font-weight-bold']) !!}
        {!! Form::textarea('description', null, [
            'class' => 'form-control',
            'rows' => 2,
            'placeholder' => 'Agregue una descripción de la zona'
        ]) !!}
    </div>

    <div class="col-md-6 mb-3">
        {!! Form::label('average_waste_kg', 'Residuos Promedio (kg)', ['class' => 'font-weight-bold']) !!}
        {!! Form::number('average_waste_kg', null, [
            'class' => 'form-control',
            'step' => '0.01',
            'placeholder' => 'Ej: 1200.50'
        ]) !!}
        <small class="text-muted">Cantidad promedio de residuos en kilogramos por día</small>
    </div>

    <div class="col-md-6 mb-3">
        {!! Form::label('status', 'Estado *', ['class' => 'font-weight-bold']) !!}
        {!! Form::select('status', [1 => 'Activo', 0 => 'Inactivo'], null, [
            'class' => 'form-select', 'required'
        ]) !!}
    </div>
</div>

<hr class="my-3">

{{-- Sección del Perímetro --}}
<h6 class="font-weight-bold mb-2 text-dark">
    <i class="bi bi-geo-alt-fill text-primary mr-1"></i> Coordenadas del Perímetro *
</h6>

{{-- Búsqueda de dirección con Nominatim (OSM) --}}
<div class="row mb-2">
    <div class="col-md-12">
        <label class="small text-muted mb-1">Buscar dirección dentro del distrito</label>
        <div class="input-group">
            <input type="text" id="inputBuscarDireccion" class="form-control" placeholder="Escribe una dirección o institución (ej: Mercado Moshoqueque)">
            <button class="btn btn-outline-secondary" type="button" id="btnBuscarDireccion">
                <i class="bi bi-search"></i> Buscar
            </button>
        </div>
    </div>
</div>

<div id="contenedorCoordenadas" class="mb-3" style="max-height: 180px; overflow-y: auto;"></div>

<div class="d-flex mb-3">
    <button type="button" class="btn btn-primary btn-sm mr-2" id="btnAgregarCoordManual">
        <i class="bi bi-plus-lg"></i> Agregar Coordenada
    </button>
    <button type="button" class="btn btn-warning btn-sm text-dark font-weight-bold" id="btnLimpiarMapa">
        <i class="bi bi-arrow-counterclockwise"></i> Limpiar Mapa y Coordenadas
    </button>
</div>
<small class="text-muted d-block mb-2">Mínimo 3 coordenadas para definir un perímetro.</small>

{{-- Input oculto con el JSON que se enviará al backend --}}
{!! Form::hidden('coordinates', isset($zone) && $zone->coordinates ? (is_string($zone->coordinates) ? $zone->coordinates : json_encode($zone->coordinates)) : '[]', ['id' => 'inputCoordinatesJson']) !!}

<label class="font-weight-bold text-dark mb-1">Mapa interactivo de la zona</label>
<div id="mapZona" style="height: 380px; width: 100%; border-radius: 8px; border: 1px solid #ced4da; background-color: #f8f9fa;"></div>
<small class="text-muted mt-1 d-block">Dibuje o ajuste el perímetro directamente en el mapa usando el botón de polígono.</small>

<script>
    (function () {
        const existingZones = {{ Illuminate\Support\Js::from($existingZones ?? []) }};
        let currentCoords = [];
        
        try {
            const raw = $('#inputCoordinatesJson').val();
            currentCoords = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(currentCoords)) currentCoords = [];
        } catch(e) {
            currentCoords = [];
        }

        if (window.leafletMapInstance) {
            window.leafletMapInstance.off();
            window.leafletMapInstance.remove();
            window.leafletMapInstance = null;
        }

        // 1. Inicializar mapa
        const map = L.map('mapZona', {
            center: [-6.7628, -79.8375],
            zoom: 14,
            zoomAnimation: false
        });
        window.leafletMapInstance = map;

        // Capa base de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Capa para zonas existentes de referencia (color rojo)
        if (Array.isArray(existingZones)) {
            existingZones.forEach(z => {
                let coords = z.coordinates;
                if (typeof coords === 'string') {
                    try { coords = JSON.parse(coords); } catch(e) { coords = []; }
                }
                if (Array.isArray(coords) && coords.length >= 3) {
                    const latlngs = coords.map(c => [parseFloat(c.lat), parseFloat(c.lng)]);
                    L.polygon(latlngs, {
                        color: '#e63946',
                        fillColor: '#f1aeb5',
                        fillOpacity: 0.35,
                        weight: 2
                    }).addTo(map).bindPopup(function () {
                        const popup = document.createElement('span');
                        popup.textContent = 'Zona: ' + (z.name || '');
                        return popup;
                    });
                }
            });
        }

        // Capa donde vive el polígono editable activo
        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);
        let activePolygon = null;

        // 2. Si venimos de edición y hay coordenadas, recrear el polígono
        if (currentCoords.length >= 3) {
            const latlngs = currentCoords.map(c => [parseFloat(c.lat), parseFloat(c.lng)]);
            activePolygon = L.polygon(latlngs, {
                color: '#6f42c1',
                fillColor: '#b197fc',
                fillOpacity: 0.45,
                weight: 3
            });
            drawnItems.addLayer(activePolygon);
            renderCoordinatesInputs(currentCoords);
        }

        // 3. Controles de dibujo Leaflet.Draw
        const drawControl = new L.Control.Draw({
            draw: {
                polygon: {
                    allowIntersection: false,
                    showArea: true,
                    shapeOptions: { color: '#6f42c1', fillColor: '#b197fc', fillOpacity: 0.45 }
                },
                polyline: false,
                circle: false,
                circlemarker: false,
                rectangle: false,
                marker: false
            },
            edit: {
                featureGroup: drawnItems,
                remove: true
            }
        });
        map.addControl(drawControl);

        map.on(L.Draw.Event.CREATED, function (e) {
            drawnItems.clearLayers();
            activePolygon = e.layer;
            drawnItems.addLayer(activePolygon);

            const latlngs = activePolygon.getLatLngs()[0];
            currentCoords = latlngs.map(pt => ({ lat: pt.lat, lng: pt.lng }));
            syncCoordinates();
        });

        map.on(L.Draw.Event.EDITED, function (e) {
            e.layers.eachLayer(function (layer) {
                const latlngs = layer.getLatLngs()[0];
                currentCoords = latlngs.map(pt => ({ lat: pt.lat, lng: pt.lng }));
                syncCoordinates();
            });
        });

        map.on(L.Draw.Event.DELETED, function () {
            currentCoords = [];
            syncCoordinates();
        });

        function syncCoordinates() {
            $('#inputCoordinatesJson').val(JSON.stringify(currentCoords));
            renderCoordinatesInputs(currentCoords);
        }

        function renderCoordinatesInputs(coords) {
            const container = $('#contenedorCoordenadas');
            container.empty();

            coords.forEach((c, idx) => {
                const row = $(`
                    <div class="row g-2 mb-2 align-items-center coord-row" data-index="${idx}">
                        <div class="col">
                            <input type="number" step="any" class="form-control coord-lat" value="${c.lat}" placeholder="Latitud">
                        </div>
                        <div class="col">
                            <input type="number" step="any" class="form-control coord-lng" value="${c.lng}" placeholder="Longitud">
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-danger btn-sm btnEliminarCoord" data-index="${idx}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `);
                container.append(row);
            });
        }

        $(document).off('click', '.btnEliminarCoord').on('click', '.btnEliminarCoord', function () {
            const idx = $(this).data('index');
            currentCoords.splice(idx, 1);
            redrawPolygonFromCoords();
            syncCoordinates();
        });

        $(document).off('change', '.coord-lat, .coord-lng').on('change', '.coord-lat, .coord-lng', function () {
            const row = $(this).closest('.coord-row');
            const idx = row.data('index');
            const lat = parseFloat(row.find('.coord-lat').val());
            const lng = parseFloat(row.find('.coord-lng').val());

            if (!isNaN(lat) && !isNaN(lng)) {
                currentCoords[idx] = { lat: lat, lng: lng };
                redrawPolygonFromCoords();
                $('#inputCoordinatesJson').val(JSON.stringify(currentCoords));
            }
        });

        $('#btnAgregarCoordManual').off('click').on('click', function () {
            const center = map.getCenter();
            currentCoords.push({ lat: center.lat, lng: center.lng });
            redrawPolygonFromCoords();
            syncCoordinates();
        });

        $('#btnLimpiarMapa').off('click').on('click', function () {
            drawnItems.clearLayers();
            activePolygon = null;
            currentCoords = [];
            syncCoordinates();
        });

        function redrawPolygonFromCoords() {
            drawnItems.clearLayers();
            if (currentCoords.length >= 3) {
                const latlngs = currentCoords.map(c => [c.lat, c.lng]);
                activePolygon = L.polygon(latlngs, {
                    color: '#6f42c1',
                    fillColor: '#b197fc',
                    fillOpacity: 0.45,
                    weight: 3
                }).addTo(drawnItems);
            }
        }

        $('#btnBuscarDireccion').off('click').on('click', function () {
            const query = $('#inputBuscarDireccion').val();
            if (!query.trim()) return;

            const fullQuery = [query, $('#district').val(), $('#province').val(), $('#department').val(), 'Perú'].filter(Boolean).join(', ');
            $.getJSON(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(fullQuery)}`, function (data) {
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lon = parseFloat(data[0].lon);
                    map.setView([lat, lon], 16);
                    const address = document.createElement('strong');
                    address.textContent = data[0].display_name;
                    L.popup().setLatLng([lat, lon]).setContent(address).openOn(map);
                } else {
                    Swal.fire('No encontrado', 'No se encontró la dirección dentro del distrito.', 'info');
                }
            });
        });

        // 4. Corrección de renderizado para Bootstrap Modal:
        // Se ejecuta invalidateSize una vez que el modal completó su transición visual
        setTimeout(() => {
            map.invalidateSize();
            if (activePolygon) {
                map.fitBounds(activePolygon.getBounds(), { padding: [30, 30] });
            } else {
                map.setView([-6.7628, -79.8375], 14);
            }
        }, 350);
    })();
</script>
