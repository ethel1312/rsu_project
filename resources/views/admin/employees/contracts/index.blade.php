@extends('adminlte::page')

@section('title', 'Contratos')

@section('plugins.Select2', true)

@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title">
            <span class="rsu-title-ico">
                <i class="bi bi-file-earmark-text"></i>
            </span>
            Lista de Contratos
        </h1>

        <button type="button" id="btnNuevo" class="btn rsu-btn-new">
            <i class="bi bi-plus-circle"></i> Nuevo Contrato
        </button>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .badge-active {
            background-color: #d4edda; color: #155724;
            padding: 5px 9px; border-radius: 4px; font-size: 12px;
        }
        .badge-inactive {
            background-color: #f8d7da; color: #721c24;
            padding: 5px 9px; border-radius: 4px; font-size: 12px;
        }
        .badge-type {
            background-color: #e1f2fa; color: #28627a;
            padding: 5px 9px; border-radius: 4px; font-size: 12px;
        }

        /* Estilo idéntico a las capturas para el modal */
        .modal-header {
            background-color: #fff !important;
            border-bottom: 1px solid #edf2f7;
            padding: 18px 24px;
        }
        .modal-title {
            color: #0c2340 !important;
            font-weight: 700;
            font-size: 1.25rem;
        }
        .btn-modal-cancelar {
            background-color: #edf2f7 !important;
            color: #2b549a !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 600;
            padding: 8px 18px;
        }
        .btn-modal-cancelar:hover { background-color: #e2e8f0 !important; }
        .btn-modal-guardar {
            background-color: #38bdf8 !important;
            color: #fff !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 600;
            padding: 8px 22px;
        }
        .btn-modal-guardar:hover { background-color: #0284c7 !important; }

        #formModal .select2-container { width: 100% !important; }
        #formModal .select2-selection--single { min-height: 38px; border-radius: .375rem; }
        #formModal .select2-container--focus .select2-selection--single,
        #formModal .select2-container--open .select2-selection--single,
        #formModal .select2-search__field:focus {
            border-color: var(--rsu-sky);
            box-shadow: 0 0 0 .2rem rgba(91, 184, 245, .25);
        }
        #formModal .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: var(--rsu-navy);
        }
        
    </style>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <table class="table table-striped" id="DataTable" style="width:100%">
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Empleado</th>
                        <th>Tipo de contrato</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Salario</th>
                        <th>Posición</th>
                        <th>Estado</th>
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
                    <h5 class="modal-title" id="formModalLabel">Nuevo Contrato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
    $(document).ready(function() {
        var table = $('#DataTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('admin.contracts.index') }}",
            columns: [
                { data: 'dni', name: 'employee.dni', className: 'align-middle font-weight-bold' },
                { data: 'employee_name', name: 'employee.first_name', className: 'align-middle' },
                { data: 'contract_type_badge', name: 'contract_type', className: 'align-middle text-center' },
                { data: 'start_date_formatted', name: 'start_date', className: 'align-middle text-center' },
                { data: 'end_date_formatted', name: 'end_date', className: 'align-middle text-center' },
                { data: 'salary_formatted', name: 'salary', className: 'align-middle text-number' },
                { data: 'position', name: 'employee.employeeType.name', className: 'align-middle text-center' },
                { data: 'status_badge', name: 'is_active', className: 'align-middle text-center' },
                { data: 'edit', name: 'edit', orderable: false, searchable: false, className: 'text-center align-middle' },
                { data: 'delete', name: 'delete', orderable: false, searchable: false, className: 'text-center align-middle' }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'
            }
        });

        function initializeEmployeeSearch() {
            $('#employee_id').select2({
                dropdownParent: $('#formModal'),
                width: '100%',
                placeholder: 'Buscar por DNI, nombres o apellidos',
                allowClear: true,
                minimumInputLength: 2,
                maximumInputLength: 100,
                language: {
                    inputTooShort: function () { return 'Escriba al menos 2 caracteres para buscar.'; },
                    inputTooLong: function () { return 'La búsqueda no debe superar los 100 caracteres.'; },
                    searching: function () { return 'Buscando personal...'; },
                    noResults: function () { return 'No se encontró personal con esos datos.'; },
                    loadingMore: function () { return 'Cargando más resultados...'; },
                    errorLoading: function () { return 'No se pudo buscar personal. Intente nuevamente o recargue la página.'; },
                    removeAllItems: function () { return 'Quitar selección'; }
                },
                ajax: {
                    url: @json(route('admin.contracts.employees')),
                    dataType: 'json',
                    delay: 300,
                    data: function (params) { return {q: (params.term || '').trim(), page: params.page || 1}; }
                }
            }).on('select2:open', function () {
                $('#formModal .select2-search__field')
                    .attr('aria-label', 'Buscar personal por DNI, nombres o apellidos')
                    .attr('placeholder', 'DNI, nombres o apellidos')
                    .trigger('focus');
            });
        }

        function destroyEmployeeSearch() {
            if ($('#employee_id').hasClass('select2-hidden-accessible')) {
                $('#employee_id').select2('destroy');
            }
        }

        $('#formModal').on('hidden.bs.modal', function () {
            destroyEmployeeSearch();
            $('#formModal .modal-body').empty();
        });

        // nuevesito
        $('#btnNuevo').click(function() {
            $.ajax({
                url: "{{ route('admin.contracts.create') }}",
                type: "GET",
                success: function(response) {
                    destroyEmployeeSearch();
                    $('#formModalLabel').html('Nuevo Contrato');
                    $('#formModal .modal-body').html(response);
                    initializeEmployeeSearch();
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
                },
                error: function(xhr) {
                    Swal.fire('Error', 'No se pudo cargar el formulario de contrato.', 'error');
                }
            });
        });

        // editar
        $(document).on('click', '.btnEditar', function() {
            var id = $(this).data('id');
            $.ajax({
                url: "{{ route('admin.contracts.edit', ':id') }}".replace(':id', id),
                type: "GET",
                success: function(response) {
                    destroyEmployeeSearch();
                    $('#formModalLabel').html('Editar Contrato');
                    $('#formModal .modal-body').html(response);
                    initializeEmployeeSearch();
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
                },
                error: function(xhr) {
                    Swal.fire('Error', 'No se pudo cargar el contrato.', 'error');
                }
            });
        });

        // guardarr y actualizar
        $(document).on('submit', '#frmContract', function(e) {
            e.preventDefault();
            var form = $(this);
            form.find('button[type="submit"]').prop('disabled', true);
            var formData = new FormData(form[0]);

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).hide();
                    table.ajax.reload(null, false);
                    Swal.fire("Éxito", response.message, "success");
                },
                error: function(xhr) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    let errorMsg = xhr.responseJSON?.error || "Verifica los datos";
                    if (xhr.status === 422 && xhr.responseJSON.errors) {
                        errorMsg = '';
                        $.each(xhr.responseJSON.errors, function(k, v) {
                            errorMsg += v[0] + '<br>';
                        });
                    }
                    Swal.fire("Error de Validación", errorMsg, "error");
                }
            });
        });

        // eliminar

        $(document).on('submit', '.frmEliminar', function(e) {
            e.preventDefault();
            var form = $(this);
            Swal.fire({
                title: "¿Eliminar contrato?",
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
                                text: xhr.responseJSON?.error || "Ocurrió un error al eliminar.",
                                icon: "error",
                                confirmButtonColor: "#12206b"
                            });
                        }
                    });
                }
            });
        });
    });
</script>
@stop