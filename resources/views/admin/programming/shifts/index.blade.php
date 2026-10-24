@extends('adminlte::page')

@section('title', 'Turnos')

@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title"><span class="rsu-title-ico"><i class="bi bi-clock"></i></span> Lista de Turnos</h1>
        <button type="button" id="btnNuevo" class="btn rsu-btn-new"><i class="bi bi-plus-circle"></i> Nuevo Turno</button>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        .btn-modal-cancelar { background: #edf2f7; color: #2b549a; border-radius: 8px; padding: 8px 18px; }
        .btn-modal-guardar { background: #38bdf8; color: #fff; border-radius: 8px; padding: 8px 22px; }
    </style>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="DataTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nombre</th><th>Descripción</th><th>Hora de inicio</th>
                            <th>Hora de término</th><th>Fecha de creación</th><th>Acciones</th>
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
                    <h5 class="modal-title" id="formModalLabel">Nuevo Turno</h5>
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
                ajax: @json(route('admin.programming.shifts.index')),
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'description', name: 'description', defaultContent: '-' },
                    { data: 'start_time', name: 'start_time' },
                    { data: 'end_time', name: 'end_time' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false }
                ],
                order: [[2, 'asc']],
                language: { url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json' }
            });
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal'));

            function openForm(url, title, button) {
                button.prop('disabled', true);
                $.get(url).done(function (html) {
                    $('#formModalLabel').text(title);
                    $('#formModal .modal-body').html(html);
                    modal.show();
                }).fail(function () {
                    Swal.fire('Error', 'No se pudo cargar el formulario de turnos.', 'error');
                }).always(function () {
                    button.prop('disabled', false);
                });
            }

            $('#btnNuevo').on('click', function () {
                openForm(@json(route('admin.programming.shifts.create')), 'Nuevo Turno', $(this));
            });
            $(document).on('click', '.btnEditar', function () {
                const url = @json(route('admin.programming.shifts.edit', ':id'));
                openForm(url.replace(':id', $(this).data('id')), 'Editar Turno', $(this));
            });
            $(document).on('submit', '#frmTurno', function (event) {
                event.preventDefault();
                const form = $(this);
                const button = form.find('[type="submit"]');
                if (button.prop('disabled')) return;
                button.prop('disabled', true);
                const errors = form.find('#shiftErrors').empty().addClass('d-none');
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
                        : [xhr.responseJSON?.message || 'No se pudo guardar el turno. Intente nuevamente.'];
                    messages.forEach(message => $('<div>').text(message).appendTo(errors));
                    errors.removeClass('d-none');
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
                    title: '¿Eliminar turno?',
                    text: 'Esta acción no se puede deshacer.',
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
                        url: form.attr('action'),
                        type: 'POST',
                        data: form.serialize(),
                        headers: { Accept: 'application/json' }
                    }).done(function (response) {
                        table.ajax.reload(null, false);
                        Swal.fire('Éxito', response.message, 'success');
                    }).fail(function (xhr) {
                        Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar el turno.', 'error');
                    }).always(function () {
                        button.prop('disabled', false);
                    });
                });
            });
        });
    </script>
@stop
