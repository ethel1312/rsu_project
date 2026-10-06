@extends('adminlte::page')

@section('title', 'Personal')

@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title">
            <span class="rsu-title-ico">
                <i class="bi bi-person"></i>
            </span>
            Lista de Personal
        </h1>

        <button type="button" id="btnNuevo" class="btn rsu-btn-new">
            <i class="bi bi-plus-circle"></i> Nuevo Personal
        </button>
    </div>
@stop

@section('css')
    <style>
        .employee-thumbnail {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            border: 1px solid #dfe6f5;
        }

        .employee-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f1f3f5;
            border: 1px solid #dfe6f5;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
        }

        .badge-active {
            background-color: #d4edda;
            color: #155724;
            padding: 5px 9px;
            border-radius: 4px;
            font-size: 12px;
        }

        .badge-inactive {
            background-color: #f8d7da;
            color: #721c24;
            padding: 5px 9px;
            border-radius: 4px;
            font-size: 12px;
        }

        .badge-type {
            background-color: #e1f2fa;
            color: #28627a;
            padding: 5px 9px;
            border-radius: 4px;
            font-size: 12px;
        }

        .employee-thumbnail {
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .employee-thumbnail:hover {
            transform: scale(1.05);
        }
    </style>
@stop

@section('content')

    <div class="card">
        <div class="card-body">

            <table class="table table-striped" id="DataTable" style="width:100%">
                <thead>
                    <tr>
                        <th width="60">Foto</th>
                        <th>DNI</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Creación</th>
                        <th width="40" class="text-center">Edit</th>
                        <th width="40" class="text-center">Elim</th>
                    </tr>
                </thead>
            </table>

        </div>
    </div>


    {{-- Modal Formulario --}}
    <div class="modal fade" id="formModal" data-bs-backdrop="static" tabindex="-1">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title" id="formModalLabel">
                        Nuevo Personal
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body"></div>

            </div>

        </div>

    </div>

    {{-- Modal para visualizar foto del personal --}}
    <div class="modal fade" id="employeeImageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="employeeImageModalLabel">
                        Foto del personal
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Cerrar">
                    </button>
                </div>

                <div class="modal-body text-center">
                    <img id="employeeImagePreview"
                        src=""
                        alt=""
                        style="max-width: 100%; max-height: 70vh; object-fit: contain;">
                </div>

            </div>
        </div>
    </div>

@stop


@section('js')

<script>

    $(document).ready(function() {

        /*
        |--------------------------------------------------------------------------
        | DataTable
        |--------------------------------------------------------------------------
        */

        var table = $('#DataTable').DataTable({

            processing: true,
            serverSide: true,

            ajax: "{{ route('admin.employees.index') }}",

            columns: [

                {
                    data: 'image',
                    name: 'image',
                    orderable: false,
                    searchable: false,
                    className: 'align-middle text-center'
                },

                {
                    data: 'dni',
                    name: 'dni',
                    className: 'align-middle'
                },

                {
                    data: 'first_name',
                    name: 'first_name',
                    className: 'align-middle'
                },

                {
                    data: 'last_name',
                    name: 'last_name',
                    className: 'align-middle'
                },

                {
                    data: 'email',
                    name: 'email',
                    className: 'align-middle'
                },

                {
                    data: 'employee_type',
                    name: 'employee_type',
                    searchable: false,
                    className: 'align-middle'
                },

                {
                    data: 'status',
                    name: 'status',
                    orderable: false,
                    searchable: false,
                    className: 'align-middle text-center'
                },

                {
                    data: 'created_at',
                    name: 'created_at',
                    className: 'align-middle text-center'
                },

                {
                    data: 'edit',
                    name: 'edit',
                    orderable: false,
                    searchable: false,
                    className: 'text-center align-middle'
                },

                {
                    data: 'delete',
                    name: 'delete',
                    orderable: false,
                    searchable: false,
                    className: 'text-center align-middle'
                }

            ],

            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'
            }

        });


        /*
        |--------------------------------------------------------------------------
        | Nuevo Personal
        |--------------------------------------------------------------------------
        */

        $('#btnNuevo').click(function() {

            $.ajax({

                url: "{{ route('admin.employees.create') }}",

                type: "GET",

                success: function(response) {

                    $('#formModalLabel').html('Nuevo Personal');

                    $('#formModal .modal-body').html(response);

                    bootstrap.Modal
                        .getOrCreateInstance(
                            document.getElementById('formModal')
                        )
                        .show();
                },

                error: function(xhr) {

                    console.error(
                        'No se pudo cargar el formulario:',
                        xhr.responseText
                    );

                    Swal.fire(
                        'Error',
                        'No se pudo cargar el formulario del personal.',
                        'error'
                    );
                }

            });

        });


        /*
        |--------------------------------------------------------------------------
        | Editar Personal
        |--------------------------------------------------------------------------
        */

        $(document).on('click', '.btnEditar', function() {

            var id = $(this).data('id');

            $.ajax({

                url: "{{ route('admin.employees.edit', ':id') }}"
                    .replace(':id', id),

                type: "GET",

                success: function(response) {

                    $('#formModalLabel').html('Editar Personal');

                    $('#formModal .modal-body').html(response);

                    bootstrap.Modal
                        .getOrCreateInstance(
                            document.getElementById('formModal')
                        )
                        .show();
                },

                error: function(xhr) {

                    console.error(
                        'No se pudo cargar el formulario:',
                        xhr.responseText
                    );

                    Swal.fire(
                        'Error',
                        'No se pudo cargar el formulario.',
                        'error'
                    );
                }

            });

        });


        /*
        |--------------------------------------------------------------------------
        | Guardar / Actualizar
        |--------------------------------------------------------------------------
        */

        $(document).on('submit', '#frmEmployee', function(e) {

            e.preventDefault();

            var form = $(this);

            form.find('button[type="submit"]')
                .prop('disabled', true);

            var formData = new FormData(form[0]);

            $.ajax({

                url: form.attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,

                success: function(response) {

                    $('#formModal').modal('hide');

                    table.ajax.reload(null, false);

                    Swal.fire(
                        "Éxito",
                        response.message,
                        "success"
                    );

                },

                error: function(xhr) {

                    form.find('button[type="submit"]')
                        .prop('disabled', false);

                    let errorMsg =
                        xhr.responseJSON?.error ||
                        "Verifica los datos";

                    if (
                        xhr.status === 422 &&
                        xhr.responseJSON.errors
                    ) {

                        errorMsg = '';

                        $.each(
                            xhr.responseJSON.errors,
                            function(k, v) {

                                errorMsg +=
                                    v[0] + '<br>';

                            }
                        );

                    }

                    Swal.fire(
                        "Error de Validación",
                        errorMsg,
                        "error"
                    );

                }

            });

        });


        /*
        |--------------------------------------------------------------------------
        | Eliminar
        |--------------------------------------------------------------------------
        */

        $(document).on('submit', '.frmEliminar', function(e) {

            e.preventDefault();

            var form = $(this);

            Swal.fire({

                title: "¿Eliminar personal?",

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

                            Swal.fire({

                                title: "¡Eliminado!",

                                text: response.message,

                                icon: "success",

                                confirmButtonColor: "#12206b"

                            });

                        },

                        error: function(xhr) {

                            Swal.fire({

                                title: "No se pudo eliminar",

                                text:
                                    xhr.responseJSON?.error ||
                                    "Ocurrió un error al eliminar.",

                                icon: "error",

                                confirmButtonColor: "#12206b"

                            });

                        }

                    });

                }

            });

        });

        $(document).on('click', '.btnVerImagenPersonal', function() {

            var imageUrl = $(this).data('image');
            var employeeName = $(this).data('name');

            $('#employeeImageModalLabel').text(employeeName);

            $('#employeeImagePreview').attr({
                src: imageUrl,
                alt: employeeName
            });

            bootstrap.Modal
                .getOrCreateInstance(document.getElementById('employeeImageModal'))
                .show();
        });

        $('#employeeImageModal').on('hidden.bs.modal', function() {
            $('#employeeImagePreview').attr('src', '').attr('alt', '');
        });

    });

</script>

@stop