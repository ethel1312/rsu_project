@extends('adminlte::page')

@section('title', 'Proyecto RSU')

@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title">
            <span class="rsu-title-ico"><i class="bi bi-c-circle"></i></span>
            Lista de Marcas
        </h1>

        <button type="button" id="btnNuevo" class="btn rsu-btn-new">
            <i class="bi bi-plus-circle"></i> Nueva Marca
        </button>
    </div>
@stop

@section('content')

    <div class="card">
        <div class="card-body">

            <table class="table table-striped" id="DataTable" style="width:100%">

                <thead>

                    <tr>
                        <th>Logo</th>
                        <th>Nombre</th>
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

            </div>

        </div>

    </div>

@stop

@section('css')

    <style>
        #DataTable img {
            object-fit: contain;
        }

        #formModal .brand-form-row {
            --bs-gutter-x: 1rem;
            --bs-gutter-y: .5rem;
        }

        #formModal .brand-image-picker {
            display: block;
            width: 100%;
            padding: 0;
            border: 0;
            background: transparent;
            color: inherit;
            cursor: pointer;
            text-align: center;
        }

        #formModal .brand-image-preview {
            display: block;
            width: 100%;
            height: 140px;
            object-fit: contain;
        }

        #formModal .brand-image-hint {
            display: block;
            margin-top: .35rem;
            font-size: .8125rem;
            line-height: 1.35;
        }

        #formModal .brand-form-actions {
            display: flex;
            gap: .5rem;
            margin-top: .25rem;
        }

        #formModal .brand-form-actions .btn + .btn {
            margin-left: 0;
        }
    </style>

@stop

@section('js')

    <script>
        



        /* |--------------------------------------------------------------------------
| GUARDAR O ACTUALIZAR MARCA POR AJAX
|-------------------------------------------------------------------------- */
$(document).on('submit', '#frmBrand', function(e) {
    e.preventDefault(); // Evita que la página se recargue

    var form = $(this);
    var formData = new FormData(this); // Empaqueta los datos, incluyendo la imagen
    
    // Deshabilitar el botón para evitar múltiples clics
    form.find('button[type="submit"]').prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Guardando...');

    $.ajax({
        url: form.attr('action'),
        type: 'POST', // Usamos POST. Si es edición, Laravel leerá el campo oculto _method="PUT"
        data: formData,
        processData: false, // Necesario para enviar archivos
        contentType: false, // Necesario para enviar archivos
        success: function(response) {
            $('#formModal').modal('hide'); // Cierra el modal
            refreshTable(); // Refresca tu DataTable
            
            Swal.fire({
                title: "Proceso exitoso",
                text: response.message,
                icon: "success",
                draggable: true
            });
        },
        error: function(xhr) {
            // Rehabilitar el botón
            form.find('button[type="submit"]').prop('disabled', false).html('<i class="bi bi-floppy-fill"></i> Reintentar');

            if (xhr.status === 422) {
                // Errores de validación (ej. nombre duplicado)
                let errors = xhr.responseJSON.errors;
                let errorMensaje = '';
                $.each(errors, function(key, value) {
                    errorMensaje += value[0] + '<br>';
                });
                Swal.fire({
                    title: "Error de validación",
                    html: errorMensaje,
                    icon: "warning"
                });
            } else {
                // Otros errores (ej. error 500 del servidor)
                Swal.fire({
                    title: "Ocurrió un error",
                    text: xhr.responseJSON.error || "Error interno del servidor",
                    icon: "error"
                });
            }
        }
    });
});
        $(document).ready(function() {
            /* |-------------------------------------------------------------------------- | DATATABLE |-------------------------------------------------------------------------- */
            var table = $('#DataTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('admin.brands.index') }}",
                columns: [{
                    data: 'logo',
                    name: 'logo',
                    orderable: false,
                    searchable: false
                }, {
                    data: 'name',
                    name: 'name'
                }, {
                    data: 'description',
                    name: 'description'
                }, {
                    data: 'created_at',
                    name: 'created_at'
                }, {
                    data: 'updated_at',
                    name: 'updated_at'
                }, {
                    data: 'edit',
                    name: 'edit',
                    orderable: false,
                    searchable: false
                }, {
                    data: 'delete',
                    name: 'delete',
                    orderable: false,
                    searchable: false
                }],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'
                }
            });
            /* |-------------------------------------------------------------------------- | NUEVA MARCA |-------------------------------------------------------------------------- */
            $('#btnNuevo').click(function() {
                $.ajax({
                    url: "{{ route('admin.brands.create') }}",
                    type: "GET",
                    success: function(response) {
                        $('#formModalLabel').html('Nueva Marca');
                        $('#formModal .modal-body').html(response);
                        var modal = new bootstrap.Modal(
                            document.getElementById('formModal'));
                        modal.show();
                    },
                    error: function(xhr) {
                        console.log('Error al cargar el formulario de nueva marca:');
                        console.log(xhr.status);
                        console.log(xhr.responseText);
                    }
                });
            });


            /* |-------------------------------------------------------------------------- | EDITAR MARCA |-------------------------------------------------------------------------- */

            $(document).on('click', '.btnEditar', function() {
                var id = $(this).data('id');
                console.log('ID de la marca:', id);
                $.ajax({
                    url: "{{ route('admin.brands.edit', ':id') }}".replace(':id', id),
                    type: "GET",
                    success: function(response) {
                        $('#formModalLabel').html('Editar Marca');
                        $('#formModal .modal-body').html(response);
                        var modal = new bootstrap.Modal(document.getElementById('formModal'));
                        modal.show();
                    },
                    error: function(xhr) {
                        console.log('Error al cargar la marca:');
                        console.log('Status:', xhr.status);
                        console.log(xhr.responseText);
                    }
                });
            }); /* |-------------------------------------------------------------------------- | ELIMINAR MARCA |-------------------------------------------------------------------------- */
            /* |--------------------------------------------------------------------------
| ELIMINAR MARCA POR AJAX
|-------------------------------------------------------------------------- */
$(document).on('submit', '.frmEliminar', function(e) {
    e.preventDefault(); // Evita que la página recargue
    var form = $(this);
    
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
            
            // Petición AJAX en segundo plano
            $.ajax({
                url: form.attr('action'),
                type: 'POST', // Laravel leerá el @method('DELETE') que viene oculto en el formulario
                data: form.serialize(),
                success: function(response) {
                    refreshTable(); // Refresca tu DataTable automáticamente
                    
                    Swal.fire({
                        title: "¡Eliminado!",
                        text: response.message, // Mensaje que envías desde el controlador
                        icon: "success"
                    });
                },
                error: function(xhr) {
                    Swal.fire({
                        title: "Ocurrió un error",
                        text: xhr.responseJSON ? xhr.responseJSON.error : "No se pudo eliminar el registro.",
                        icon: "error"
                    });
                }
            });
            
        }
    });
}); /* |-------------------------------------------------------------------------- | REFRESCAR DATATABLE |-------------------------------------------------------------------------- */
            window.refreshTable = function() {
                table.ajax.reload(null, false);
            };
        }); /* |-------------------------------------------------------------------------- | MENSAJE DE ÉXITO |-------------------------------------------------------------------------- */
        @if (session('success') != null)
            Swal.fire({
                title: "Proceso exitoso",
                text: "{{ session('success') }}",
                icon: "success",
                draggable: true
            });
        @endif /* |-------------------------------------------------------------------------- | MENSAJE DE ERROR |-------------------------------------------------------------------------- */
        @if (session('error') != null)
            Swal.fire({
                title: "Ocurrió un error",
                text: "{{ session('error') }}",
                icon: "error",
                draggable: true
            });
        @endif
    </script>

@stop
