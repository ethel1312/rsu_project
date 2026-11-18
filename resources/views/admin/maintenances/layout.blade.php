@extends('adminlte::page')

@section('title', 'Mantenimiento de vehículos')

@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title"><span class="rsu-title-ico"><i class="bi bi-tools"></i></span> @yield('maintenance_heading')</h1>
        @hasSection('maintenance_create')
            <button type="button" id="btnNuevo" class="btn rsu-btn-new"><i class="bi bi-plus-circle"></i> @yield('maintenance_new_label')</button>
        @endif
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom-modales.css') }}">
@stop

@section('content')
    @yield('maintenance_context')
    <div class="card"><div class="card-body"><div class="table-responsive">
        <table class="table table-striped" id="DataTable" style="width:100%">
            <thead><tr>@yield('maintenance_columns')</tr></thead>
        </table>
    </div></div></div>
    <div class="modal fade" id="formModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="formModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="formModalLabel">Mantenimiento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"></div>
        </div></div>
    </div>
@stop

@section('js')
    <script>
        $(function () {
            const textColumn = $.fn.dataTable.render.text();
            const dateColumn = function (value, type) {
                return type === 'display' && value ? value.split('-').reverse().join('/') : value;
            };
            @yield('maintenance_config')
            const table = $('#DataTable').DataTable({
                processing: true, serverSide: true, ajax: @json(url()->current()),
                columns, order: [[0, 'asc']],
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
                    Swal.fire('Error', 'No se pudo cargar el formulario. Actualice el listado e intente nuevamente.', 'error');
                }).always(() => button.prop('disabled', false));
            }
            @hasSection('maintenance_create')
                $('#btnNuevo').on('click', function () {
                    openForm(@yield('maintenance_create'), $(this).text().trim(), $(this));
                });
            @endif
            $(document).on('click', '.btnEditar', function () {
                openForm($(this).data('url'), $(this).attr('title'), $(this));
            });
            $(document).on('submit', '.frmMaintenance', function (event) {
                event.preventDefault();
                const form = $(this), button = form.find('[type="submit"]');
                if (button.prop('disabled')) return;
                button.prop('disabled', true);
                const errors = form.find('.maintenance-errors').empty().addClass('d-none');
                $.ajax({
                    url: form.attr('action'), type: 'POST', data: new FormData(this),
                    processData: false, contentType: false, headers: { Accept: 'application/json' }
                }).done(function (response) {
                    modal.hide(); table.ajax.reload(null, false);
                    Swal.fire('Éxito', response.message, 'success');
                }).fail(function (xhr) {
                    const messages = xhr.responseJSON?.errors ? Object.values(xhr.responseJSON.errors).flat()
                        : [xhr.responseJSON?.message || 'No se pudo guardar. Revise su conexión e intente nuevamente.'];
                    messages.forEach(message => $('<div>').text(message).appendTo(errors));
                    errors.removeClass('d-none');
                }).always(() => button.prop('disabled', false));
            });
            $(document).on('submit', '.frmEliminar', function (event) {
                event.preventDefault();
                const form = $(this), button = form.find('[type="submit"]');
                if (button.prop('disabled')) return;
                button.prop('disabled', true);
                Swal.fire({
                    title: '¿Confirmar eliminación?', text: form.data('confirmation'), icon: 'warning',
                    showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
                }).then(function (result) {
                    if (!result.isConfirmed) { button.prop('disabled', false); return; }
                    $.ajax({ url: form.attr('action'), type: 'POST', data: form.serialize(), headers: { Accept: 'application/json' } })
                        .done(function (response) { table.ajax.reload(null, false); Swal.fire('Éxito', response.message, 'success'); })
                        .fail(function (xhr) { Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar el registro.', 'error'); })
                        .always(() => button.prop('disabled', false));
                });
            });
        });
    </script>
@stop
