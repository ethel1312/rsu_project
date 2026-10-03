@extends('adminlte::page')
@section('title', 'Vehículos')
@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title">
            <span class="rsu-title-ico"><i class="bi bi-truck"></i></span>
            Lista de Vehículos
        </h1>

        <button type="button" id="btnNuevo" class="btn rsu-btn-new">
            <i class="bi bi-plus-circle"></i> Nuevo Vehículo
        </button>
    </div>
@stop
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.css">
@stop
@section('content')
    <div class="card">
        
        <div class="card-body">
            <table class="table table-striped" id="DataTable" style="width:100%">
                <thead>
                    <tr>
                        <th width="60">Imagen</th>
                        <th>Nombre</th>
                        <th>Código</th>
                        <th>Placa</th>
                        <th>Año</th>
                        <th>Capacidad</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Tipo</th>
                        <th class="text-center">Color</th>
                        <th width="30" class="text-center">Edit</th>
                        <th width="30" class="text-center">Img</th>
                        <th width="30" class="text-center">Elim</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <!-- Modal Formulario -->
    <div class="modal fade" id="formModal" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-xl"> <!-- modal-xl para que el grid se vea bien -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formModalLabel">Nuevo Vehículo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>
<script>
    $(document).ready(function() {
        var table = $('#DataTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('admin.vehicles.index') }}",
            columns: [
                { data: 'image', name: 'image', orderable: false, searchable: false, className: 'align-middle text-center' },
                { data: 'name', name: 'name', className: 'align-middle font-weight-bold' },
                { data: 'code', name: 'code', className: 'align-middle' },
                { data: 'plate', name: 'plate', className: 'align-middle' },
                { data: 'year', name: 'year', className: 'align-middle' },
                { data: 'load_capacity', name: 'load_capacity', className: 'align-middle' },
                { data: 'brand_name', name: 'brand_name', searchable: false, className: 'align-middle' },   // <-- searchable: false
                { data: 'model_name', name: 'model_name', searchable: false, className: 'align-middle' },   // <-- searchable: false
                { data: 'type_name', name: 'type_name', searchable: false, className: 'align-middle' },     // <-- searchable: false
                { data: 'color_box', name: 'color_box', orderable: false, searchable: false, className: 'text-center align-middle' },
                { data: 'edit', name: 'edit', orderable: false, searchable: false, className: 'text-center align-middle' },
                { data: 'gallery', name: 'gallery', orderable: false, searchable: false, className: 'text-center align-middle' },
                { data: 'delete', name: 'delete', orderable: false, searchable: false, className: 'text-center align-middle' }
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json' }
        });

        // Botón Nuevo
        $('#btnNuevo').click(function() {
            $.ajax({
                url: "{{ route('admin.vehicles.create') }}",
                type: "GET",
                success: function(response) {
                    $('#formModalLabel').html('Nuevo Vehículo');
                    $('#formModal .modal-body').html(response);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
                },
                error: function(xhr) {
                    console.error('No se pudo cargar el formulario del vehículo:', xhr.responseText);
                    Swal.fire('Error', 'No se pudo cargar el formulario del vehículo.', 'error');
                }
            });
        });

        // Botón Editar
        $(document).on('click', '.btnEditar', function() {
            var id = $(this).data('id');
            $.ajax({
                url: "{{ route('admin.vehicles.edit', ':id') }}".replace(':id', id),
                type: "GET",
                success: function(response) {
                    $('#formModalLabel').html('Editar Vehículo');
                    $('#formModal .modal-body').html(response);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
                }
            });
        });

        $(document).on('click', '.btnImages', function() {
            var id = $(this).data('id');
            var btn = $(this);
            var originalHtml = btn.html();
            
            // Animación de carga
            btn.html('<span class="spinner-border spinner-border-sm"></span>').prop('disabled', true);

            $.ajax({
                url: "{{ route('admin.vehicles.images', ':id') }}".replace(':id', id),
                type: "GET",
                success: function(response) {
                    btn.html(originalHtml).prop('disabled', false);
                    $('#formModalLabel').html('<i class="bi bi-images"></i> Galería del Vehículo');
                    $('#formModal .modal-body').html(response);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
                },
                error: function(xhr) {
                    btn.html(originalHtml).prop('disabled', false);
                    Swal.fire("Error", "No se pudo cargar la galería de imágenes.", "error");
                    console.error("Detalle del error: ", xhr.responseText);
                }
            });
        });

        // Guardar/Actualizar (Con captura de errores de validación de Placa/Código)
        $(document).on('submit', '#frmVehicle', function(e) {
            e.preventDefault();
            var form = $(this);
            form.find('button[type="submit"]').prop('disabled', true);
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    $('#formModal').modal('hide');
                    table.ajax.reload(null, false);
                    Swal.fire("Éxito", response.message, "success");
                },
                error: function(xhr) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    let errorMsg = xhr.responseJSON?.error || "Verifica los datos";
                    if(xhr.status === 422 && xhr.responseJSON.errors) {
                        errorMsg = '';
                        $.each(xhr.responseJSON.errors, (k, v) => errorMsg += v[0] + '<br>');
                    }
                    Swal.fire("Error de Validación", errorMsg, "error");
                }
            });
        });

        // Eliminar
        $(document).on('submit', '.frmEliminar', function(e) {
            e.preventDefault();
            var form = $(this);
            Swal.fire({
                title: "¿Eliminar Vehículo?",
                text: "Se eliminarán también sus imágenes asociadas.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, eliminar"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: form.attr('action'),
                        type: 'DELETE',
                        data: form.serialize(),
                        success: function(response) {
                            table.ajax.reload(null, false);
                            Swal.fire("Eliminado", response.message, "success");
                        }
                    });
                }
            });
        });
    });
</script>
@stop