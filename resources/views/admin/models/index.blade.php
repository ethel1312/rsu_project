@extends('adminlte::page')

@section('title', 'Proyecto RSU')

@section('content')

    <div class="card">
        <div class="card-header d-flex">

            <div class="w-50">
                <h4>Modelos</h4>
            </div>

            <div class="w-50 d-flex justify-content-end">

                <button type="button" id="btnNuevo" class="btn btn-success">
                    <i class="bi bi-cloud-plus"></i>
                    Nueva Modelo
                </button>

            </div>

        </div>


        <div class="card-body">

            <table class="table table-striped" id="DataTable" style="width:100%">

                <thead>

                    <tr>
                        <th>Modelo</th>
                        <th>Codigo</th>
                        <th>Marca</th>
                        <th>Descripción</th>
                        <th>Creación</th>
                        <th>Actualización</th>
                        <th width="20">Edit</th>
                        <th width="20">Elim</th>
                    </tr>

                </thead>

                <tbody>
                    {{-- DataTables cargará los datos mediante AJAX --}}
                </tbody>

            </table>

        </div>

    </div>

    {{-- ========================================================= --}}
    {{-- MODAL --}}
    {{-- ========================================================= --}}

    <div class="modal fade" id="formModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="formModalLabel" aria-hidden="true">
        <div class="modal-dialog">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title" id="formModalLabel">
                        Modal title
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                </div>


                <div class="modal-body">

                    {{-- Aquí se cargará el formulario mediante AJAX --}}

                </div>


                <div class="modal-footer">

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cerrar
                    </button>

                </div>

            </div>

        </div>

    </div>

@stop

@section('css')

    <style>
        #DataTable img {
            object-fit: contain;
        }
    </style>

@stop

@section('js')

    <script>
    $(document).ready(function() {
        /* |-------------------------------------------------------------------------- | DATATABLE |-------------------------------------------------------------------------- */
        var table = $('#DataTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('admin.models.index') }}",
            columns: [
                { data: 'model', name: 'model' },
                { data: 'code', name: 'code' },
                { data: 'brand', name: 'brand' }, // Corresponde a la marca (join)
                { data: 'description', name: 'description' },
                { data: 'created_at', name: 'created_at' },
                { data: 'updated_at', name: 'updated_at' },
                { data: 'edit', name: 'edit', orderable: false, searchable: false },
                { data: 'delete', name: 'delete', orderable: false, searchable: false }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'
            }
        });

        /* |-------------------------------------------------------------------------- | NUEVO MODELO |-------------------------------------------------------------------------- */
        $('#btnNuevo').click(function() {
            $.ajax({
                url: "{{ route('admin.models.create') }}",
                type: "GET",
                success: function(response) {
                    $('#formModalLabel').html('Nuevo Modelo');
                    $('#formModal .modal-body').html(response);
                    var modal = new bootstrap.Modal(document.getElementById('formModal'));
                    modal.show();
                },
                error: function(xhr) {
                    console.log('Error al cargar el formulario:', xhr.status, xhr.responseText);
                }
            });
        });

        /* |-------------------------------------------------------------------------- | EDITAR MODELO |-------------------------------------------------------------------------- */
        $(document).on('click', '.btnEditar', function() {
            var id = $(this).data('id');
            $.ajax({
                url: "{{ route('admin.models.edit', ':id') }}".replace(':id', id),
                type: "GET",
                success: function(response) {
                    $('#formModalLabel').html('Editar Modelo');
                    $('#formModal .modal-body').html(response);
                    var modal = new bootstrap.Modal(document.getElementById('formModal'));
                    modal.show();
                },
                error: function(xhr) {
                    console.log('Error al cargar el modelo:', xhr.status, xhr.responseText);
                }
            });
        });

        /* |-------------------------------------------------------------------------- | GUARDAR O ACTUALIZAR POR AJAX |-------------------------------------------------------------------------- */
        $(document).on('submit', '#frmModel', function(e) {
            e.preventDefault();
            var form = $(this);
            
            // Deshabilitamos el botón mientras guarda
            form.find('button[type="submit"]').prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Guardando...');

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    $('#formModal').modal('hide'); // Cerramos el modal
                    table.ajax.reload(null, false); // Refrescamos la tabla
                    
                    Swal.fire({
                        title: "Proceso exitoso",
                        text: response.message,
                        icon: "success",
                        draggable: true
                    });
                },
                error: function(xhr) {
                    // Volvemos a habilitar el botón si hay error
                    form.find('button[type="submit"]').prop('disabled', false).html('<i class="bi bi-floppy-fill"></i> Reintentar');
                    
                    // Manejo de errores de validación
                    let errorMsg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "Verifica los datos ingresados.";
                    if (xhr.status === 422 && xhr.responseJSON.errors) {
                        errorMsg = '';
                        $.each(xhr.responseJSON.errors, function(key, value) {
                            errorMsg += value[0] + '<br>';
                        });
                    }

                    Swal.fire({
                        title: "Error",
                        html: errorMsg,
                        icon: "error"
                    });
                }
            });
        });

        /* |-------------------------------------------------------------------------- | ELIMINAR MODELO POR AJAX |-------------------------------------------------------------------------- */
        $(document).on('submit', '.frmEliminar', function(e) {
            e.preventDefault();
            var form = $(this);
            
            // Obtenemos el token de seguridad del formulario
            var csrfToken = form.find('input[name="_token"]').val(); 

            Swal.fire({
                title: "¿Está seguro de eliminar?",
                text: "¡Esta acción no se puede revertir!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, eliminar!",
                cancelButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: form.attr('action'),
                        type: 'DELETE',
                        data: form.serialize(),
                        headers: {
                            'X-CSRF-TOKEN': csrfToken // Enviamos el token en la cabecera para evitar el error 419
                        },
                        success: function(response) {
                            table.ajax.reload(null, false);
                            Swal.fire({
                                title: "¡Eliminado!",
                                text: response.message,
                                icon: "success"
                            });
                        },
                        error: function(xhr) {
                            Swal.fire({
                                title: "Ocurrió un error",
                                text: xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo eliminar el registro.",
                                icon: "error"
                            });
                        }
                    });
                }
            });
        });

        /* |-------------------------------------------------------------------------- | REFRESCAR DATATABLE |-------------------------------------------------------------------------- */
        window.refreshTable = function() {
            table.ajax.reload(null, false);
        };
    });
</script>

@stop
