@extends('adminlte::page')

@section('title', 'Grupos de Personal')
@section('plugins.Select2', true)

@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title"><span class="rsu-title-ico"><i class="bi bi-people"></i></span> Lista de Grupos de Personal</h1>
        <button type="button" id="btnNuevo" class="btn rsu-btn-new"><i class="bi bi-plus-circle"></i> Nuevo Grupo</button>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/dist/css/adminlte-select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        .btn-modal-cancelar { background: #edf2f7; color: #2b549a; border-radius: 8px; padding: 8px 18px; }
        .btn-modal-guardar { background: #38bdf8; color: #fff; border-radius: 8px; padding: 8px 22px; }
        #formModal .modal-title,
        #frmPersonnelGroup label { color: var(--bs-body-color); }
        #frmPersonnelGroup .select2-container { width: 100% !important; }
        #formModal .select2-selection { min-height: 38px; font: inherit; }
        #formModal .select2-selection--multiple { align-items: center; gap: .375rem; }
        #formModal .select2-search--inline { flex: 1 1 12rem; min-width: 0; }
        #formModal .select2-search--inline .select2-search__field {
            width: 100% !important;
            min-height: 1.5rem;
            margin: 0;
            font: inherit;
            color: var(--bs-body-color);
        }
        #formModal .select2-selection__choice { max-width: 100%; }
        #formModal .select2-selection__choice__display { white-space: normal; overflow-wrap: anywhere; }
        #formModal .select2-search--dropdown .select2-search__field {
            padding: .5rem .75rem;
            font: inherit;
            color: var(--bs-body-color);
            background: var(--bs-body-bg);
        }
        #formModal .select2-dropdown { overflow: hidden; border-radius: .375rem; }
        #formModal .select2-results__option { padding: .5rem .75rem; overflow-wrap: anywhere; }
        #formModal .select2-container--focus .select2-selection,
        #formModal .select2-container--open .select2-selection {
            border-color: var(--rsu-sky);
            box-shadow: 0 0 0 .2rem rgba(91, 184, 245, .25);
        }
        #DataTable td { vertical-align: middle; }
    </style>
@stop

@section('content')
    <div class="alert alert-info small">Guarde equipos habituales por zona y días de la semana para reutilizarlos en futuras programaciones.</div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="DataTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nombre</th><th>Turno</th><th>Zona</th><th>Vehículo</th><th>Conductor</th>
                            <th>Ayudantes</th><th>Días</th><th>Estado</th><th>Fecha de creación</th><th>Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
    <div class="modal fade" id="formModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="formModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formModalLabel">Nuevo Grupo de Personal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(function () {
            const table = $('#DataTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: @json(route('admin.programming.personnel-groups.index')),
                columns: [
                    { data: 'name', name: 'personnel_groups.name' },
                    { data: 'shift_label', name: 'shift.name' },
                    { data: 'zone.name', name: 'zone.name' },
                    { data: 'vehicle_label', name: 'vehicle.plate' },
                    { data: 'driver_label', name: 'driver.last_name' },
                    { data: 'helpers_label', name: 'helpers.last_name', orderable: false },
                    { data: 'days_label', name: 'days_label', orderable: false, searchable: false },
                    { data: 'status_badge', name: 'personnel_groups.status', searchable: false },
                    { data: 'created_at', name: 'personnel_groups.created_at', searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false }
                ],
                order: [[8, 'desc']],
                language: { url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json' }
            });
            const modalElement = document.getElementById('formModal');
            const modalBody = $('#formModal .modal-body');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

            function destroyPersonnelSearches() {
                $('#formModal .group-search.select2-hidden-accessible').each(function () {
                    $(this).off('.personnelSearch').select2('destroy');
                });
            }

            $(modalElement).on('shown.bs.modal', function () {
                $('#formModal .group-search').each(function () {
                    const select = $(this);
                    const multiple = select.prop('multiple');
                    select.select2({
                        // El cuerpo se desplaza junto al campo; el modal exterior es el contenedor con scroll.
                        dropdownParent: modalBody,
                        width: '100%',
                        placeholder: multiple ? 'Buscar y seleccionar ayudantes' : 'Buscar conductor',
                        closeOnSelect: !multiple,
                        language: {
                            noResults: () => 'No se encontraron empleados activos de este tipo',
                            removeItem: () => 'Quitar ayudante'
                        }
                    }).on('select2:open.personnelSearch', function () {
                        const search = multiple
                            ? select.next('.select2').find('.select2-search__field')
                            : modalBody.find('.select2-dropdown .select2-search__field');
                        search.attr('aria-label', multiple ? 'Buscar ayudantes por nombre o DNI' : 'Buscar conductor por nombre o DNI')
                            .attr('placeholder', 'Nombre, apellido o DNI').trigger('focus');
                    });
                    select.next('.select2').find('.select2-selection')
                        .attr('aria-labelledby', multiple ? 'groupHelpersLabel' : 'groupDriverLabel');
                    if (multiple) {
                        select.next('.select2').find('.select2-search__field')
                            .attr('aria-label', 'Buscar ayudantes por nombre o DNI');
                    }
                });
                updateCapacity();
            }).on('hide.bs.modal', function () {
                $('#formModal .group-search.select2-hidden-accessible').select2('close');
            }).on('hidden.bs.modal', function () {
                destroyPersonnelSearches();
                modalBody.empty();
            });

            function updateCapacity() {
                const capacity = Number($('#group_vehicle_id option:selected').data('capacity'));
                const helpers = $('#group_helpers').val()?.length || 0;
                const label = $('#groupCapacity');
                if (!capacity) {
                    label.text('La capacidad incluye al conductor y a los ayudantes.').removeClass('text-danger').addClass('text-info');
                    return;
                }
                const tooMany = helpers + 1 > capacity;
                label.text(`Capacidad: ${capacity} personas (1 conductor y hasta ${Math.max(0, capacity - 1)} ayudantes). Seleccionadas: ${helpers + 1}.`)
                    .toggleClass('text-danger', tooMany).toggleClass('text-info', !tooMany);
            }

            function openForm(url, title, button) {
                button.prop('disabled', true);
                $.get(url).done(function (html) {
                    $('#formModalLabel').text(title);
                    destroyPersonnelSearches();
                    modalBody.html(html);
                    modal.show();
                }).fail(function () {
                    Swal.fire('Error', 'No se pudo cargar el formulario de grupos de personal.', 'error');
                }).always(function () {
                    button.prop('disabled', false);
                });
            }

            $('#btnNuevo').on('click', function () {
                openForm(@json(route('admin.programming.personnel-groups.create')), 'Nuevo Grupo de Personal', $(this));
            });
            $(document).on('click', '.btnEditar', function () {
                const url = @json(route('admin.programming.personnel-groups.edit', ':id'));
                openForm(url.replace(':id', $(this).data('id')), 'Editar Grupo de Personal', $(this));
            });
            $(document).on('change', '#group_vehicle_id, #group_helpers', updateCapacity);
            $(document).on('submit', '#frmPersonnelGroup', function (event) {
                event.preventDefault();
                const form = $(this);
                const button = form.find('[type="submit"]');
                if (button.prop('disabled')) return;
                button.prop('disabled', true);
                const errors = form.find('#personnelGroupErrors').empty().addClass('d-none');
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    headers: { Accept: 'application/json' }
                }).done(function (response) {
                    modal.hide();
                    table.ajax.reload(null, false);
                    Swal.fire('Éxito', response.message, 'success');
                }).fail(function (xhr) {
                    const messages = xhr.responseJSON?.errors
                        ? Object.values(xhr.responseJSON.errors).flat()
                        : [xhr.responseJSON?.message || 'No se pudo guardar el grupo. Intente nuevamente.'];
                    messages.forEach(message => $('<div>').text(message).appendTo(errors));
                    errors.removeClass('d-none').trigger('focus');
                }).always(function () {
                    button.prop('disabled', false);
                });
            });
            $(document).on('submit', '.frmEliminar', function (event) {
                event.preventDefault();
                const form = $(this);
                const button = form.find('[type="submit"]');
                if (button.prop('disabled')) return;
                button.prop('disabled', true);
                Swal.fire({
                    title: '¿Eliminar grupo de personal?',
                    text: 'Se eliminará esta plantilla de personal.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        button.prop('disabled', false);
                        return;
                    }
                    $.ajax({
                        url: form.attr('action'), type: 'POST', data: form.serialize(),
                        headers: { Accept: 'application/json' }
                    }).done(function (response) {
                        table.ajax.reload(null, false);
                        Swal.fire('Éxito', response.message, 'success');
                    }).fail(function (xhr) {
                        Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar el grupo.', 'error');
                    }).always(function () {
                        button.prop('disabled', false);
                    });
                });
            });
        });
    </script>
@stop
