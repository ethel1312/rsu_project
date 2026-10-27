@extends('adminlte::page')

@section('title', 'Zonas')

@section('content_header')
    <div class="rsu-title-bar d-flex justify-content-between align-items-center">
        <h1 class="rsu-title m-0">
            <span class="rsu-title-ico">
                <i class="bi bi-geo-alt"></i>
            </span>
            Lista de Zonas
        </h1>

        <div class="d-flex flex-wrap" style="gap: 8px;">
            <a href="{{ route('admin.programming.zones.map') }}" class="btn btn-zone-map">
                <i class="bi bi-map"></i> Ver mapa de zonas
            </a>
            <button type="button" id="btnNuevo" class="btn rsu-btn-new">
                <i class="bi bi-plus-circle"></i> Nueva Zona
            </button>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">

    {{-- Librerías OpenStreetMap Leaflet y Leaflet.draw --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />

    <style>
        .btn-zone-map {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            background: #fff3cd !important;
            border: 1px solid #ffe69c !important;
            color: #805b00 !important;
            transition: background-color .2s ease, color .2s ease, border-color .2s ease;
        }
        .btn-zone-map:hover {
            background: #ffe08a !important;
            border-color: #f2c94c !important;
            color: #634600 !important;
        }
        #DataTable .btn-zone-map { width: 40px; height: 40px; padding: 0; border-radius: .375rem; }
        #zoneMapModalCanvas { height: min(65vh, 620px); min-height: 360px; border-radius: .5rem; background: #f1f5f9; }
        .badge-active { background-color: #d4edda; color: #155724; padding: 5px 9px; border-radius: 4px; font-size: 12px; }
        .badge-inactive { background-color: #f8d7da; color: #721c24; padding: 5px 9px; border-radius: 4px; font-size: 12px; }
        
        .modal-header { background-color: #fff !important; border-bottom: 1px solid #edf2f7; padding: 18px 24px; }
        .modal-title { color: #0c2340 !important; font-weight: 700; font-size: 1.25rem; }
        .btn-modal-cancelar { background-color: #edf2f7 !important; color: #2b549a !important; border: none !important; border-radius: 8px !important; font-weight: 600; padding: 8px 18px; }
        .btn-modal-guardar { background-color: #38bdf8 !important; color: #fff !important; border: none !important; border-radius: 8px !important; font-weight: 600; padding: 8px 22px; }
    </style>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <table class="table table-striped" id="DataTable" style="width:100%">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Distrito</th>
                        <th>Provincia</th>
                        <th>Departamento</th>
                        <th>Descripción</th>
                        <th>Coordenadas</th>
                        <th>Estado</th>
                        <th>Fecha Creación</th>
                        <th width="80" class="text-center">Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <div class="modal fade" id="zoneMapModal" tabindex="-1" aria-labelledby="zoneMapModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="zoneMapModalLabel">Mapa de zona</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="zoneMapModalMessage" class="alert alert-info d-none" role="status"></div>
                    <div id="zoneMapModalCanvas" aria-label="Mapa del perímetro de la zona"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="formModal" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formModalLabel">Nueva Zona</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
@stop

@section('js')
    {{-- Leaflet JS y Leaflet.draw JS para dibujar polígonos sobre OpenStreetMap --}}
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>

    <script>
        $(document).ready(function() {
            var table = $('#DataTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('admin.programming.zones.index') }}",
                columns: [
                    { data: 'name_formatted', name: 'name', className: 'align-middle font-weight-bold' },
                    { data: 'district_formatted', name: 'district', className: 'align-middle' },
                    { data: 'province_formatted', name: 'province', className: 'align-middle' },
                    { data: 'department_formatted', name: 'department', className: 'align-middle' },
                    { data: 'description_formatted', name: 'description', className: 'align-middle' },
                    { data: 'coordinates_count', name: 'coordinates', orderable: false, searchable: false, className: 'align-middle text-center' },
                    { data: 'status_badge', name: 'status', className: 'align-middle text-center' },
                    { data: 'created_at_formatted', name: 'created_at', className: 'align-middle text-center' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'align-middle text-center' }
                ],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'
                }
            });

            let zonePreviewMap = null;
            let zonePreviewPolygon = null;
            const zoneMapModal = document.getElementById('zoneMapModal');

            $(document).on('click', '.btnVerMapaZona', function () {
                const button = $(this);
                const modal = bootstrap.Modal.getOrCreateInstance(zoneMapModal);
                const mapContainer = document.getElementById('zoneMapModalCanvas');
                const message = $('#zoneMapModalMessage');
                $('#zoneMapModalLabel').text('Mapa: ' + button.data('zone-name'));
                message.addClass('d-none').removeClass('alert-warning alert-danger').text('');
                mapContainer.classList.remove('d-none');
                modal.show();
                button.prop('disabled', true);

                $.get(button.data('map-url')).done(function (zone) {
                    if (!zone.has_valid_coordinates) {
                        mapContainer.classList.add('d-none');
                        message.removeClass('d-none').addClass('alert-warning').text('Esta zona no tiene un perímetro válido para mostrar. Edite sus coordenadas y vuelva a guardarlas.');
                        return;
                    }

                    if (!zonePreviewMap) {
                        zonePreviewMap = L.map(mapContainer).setView(zone.coordinates[0], 14);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(zonePreviewMap);
                    }
                    if (zonePreviewPolygon) zonePreviewMap.removeLayer(zonePreviewPolygon);
                    zonePreviewPolygon = L.polygon(zone.coordinates, {
                        color: '#2563eb',
                        fillColor: '#38bdf8',
                        fillOpacity: .28,
                        weight: 3
                    }).addTo(zonePreviewMap);
                    zonePreviewPolygon.bindPopup('<strong>' + $('<div>').text(zone.name).html() + '</strong>').openPopup();
                    window.setTimeout(function () {
                        zonePreviewMap.invalidateSize();
                        zonePreviewMap.fitBounds(zonePreviewPolygon.getBounds(), {padding:[30,30], maxZoom:16});
                    }, 250);
                }).fail(function (xhr) {
                    mapContainer.classList.add('d-none');
                    message.removeClass('d-none').addClass('alert-danger').text(xhr.responseJSON?.message || 'No se pudo cargar el mapa de esta zona.');
                }).always(function () {
                    button.prop('disabled', false);
                });
            });

            // Solucion al error de mapa al editar
            $('#formModal').on('shown.bs.modal', function () {
                if (window.leafletMapInstance) {
                    window.leafletMapInstance.invalidateSize();
                }
            });

            $('#btnNuevo').click(function() {
                $.ajax({
                    url: "{{ route('admin.programming.zones.create') }}",
                    type: "GET",
                    success: function(response) {
                        $('#formModalLabel').html('Nueva Zona');
                        $('#formModal .modal-body').html(response);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudo cargar el formulario de zonas.', 'error');
                    }
                });
            });

            $(document).on('click', '.btnEditar', function() {
                var id = $(this).data('id');
                $.ajax({
                    url: "{{ route('admin.programming.zones.edit', ':id') }}".replace(':id', id),
                    type: "GET",
                    success: function(response) {
                        $('#formModalLabel').html('Editar Zona');
                        $('#formModal .modal-body').html(response);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudo cargar la zona seleccionada.', 'error');
                    }
                });
            });

            $(document).on('submit', '#frmZona', function(e) {
                e.preventDefault();
                var form = $(this);
                form.find('button[type="submit"]').prop('disabled', true);

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).hide();
                        table.ajax.reload(null, false);
                        Swal.fire("Éxito", response.message, "success");
                    },
                    error: function(xhr) {
                        form.find('button[type="submit"]').prop('disabled', false);
                        let errorMsg = xhr.responseJSON?.error || "Verifica los datos ingresados.";
                        if (xhr.status === 422 && xhr.responseJSON.errors) {
                            errorMsg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                        }
                        Swal.fire("Error de Validación", errorMsg, "error");
                    }
                });
            });

            $(document).on('submit', '.frmEliminar', function(e) {
                e.preventDefault();
                var form = $(this);
                Swal.fire({
                    title: "¿Eliminar zona?",
                    text: "Esta acción no se puede deshacer.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Sí, eliminar",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: form.attr('action'),
                            type: 'DELETE',
                            data: form.serialize(),
                            success: function(response) {
                                table.ajax.reload(null, false);
                                Swal.fire("¡Eliminado!", response.message, "success");
                            },
                            error: function(xhr) {
                                Swal.fire("Error", xhr.responseJSON?.error || "No se pudo eliminar la zona.", "error");
                            }
                        });
                    }
                });
            });
        });
    </script>
@stop
