@extends('adminlte::page')

@section('title', 'Feriados')

@section('content_header')
    <div class="rsu-title-bar flex-wrap gap-2">
        <h1 class="rsu-title"><span class="rsu-title-ico"><i class="bi bi-calendar-event"></i></span> Lista de Feriados</h1>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" id="btnCargar" class="btn btn-success"><i class="bi bi-cloud-download"></i> Cargar feriados de Perú</button>
            <button type="button" id="btnNuevo" class="btn rsu-btn-new"><i class="bi bi-plus-circle"></i> Nuevo Feriado</button>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        .btn-modal-cancelar { background: #edf2f7; color: #2b549a; border-radius: 8px; padding: 8px 18px; }
        .btn-modal-guardar { background: #38bdf8; color: #fff; border-radius: 8px; padding: 8px 22px; }
        .holiday-stat { min-height: 95px; border-radius: 10px; padding: 16px; color: #fff; margin-bottom: 16px; }
        .holiday-stat strong { display: block; font-size: 1.7rem; line-height: 1.2; }
        .holiday-stat small { display: block; margin-top: 5px; }
    </style>
@stop

@section('content')
    <div class="row" aria-label="Resumen de feriados">
        <div class="col-md-3 col-6"><div class="holiday-stat bg-primary"><strong id="statTotal">{{ $stats['total'] }}</strong><small>Total de feriados</small></div></div>
        <div class="col-md-3 col-6"><div class="holiday-stat bg-success"><strong id="statActive">{{ $stats['active'] }}</strong><small>Feriados activos</small></div></div>
        <div class="col-md-3 col-6"><div class="holiday-stat bg-warning text-dark"><strong id="statUpcoming">{{ $stats['upcoming'] }}</strong><small>Próximos feriados activos</small></div></div>
        <div class="col-md-3 col-6"><div class="holiday-stat bg-info"><strong>{{ $stats['year'] }}</strong><small>Año actual</small></div></div>
    </div>
    <div class="card">
        <div class="card-body">
            <form id="frmFiltros" class="row align-items-end mb-3">
                <div class="col-md-3 mb-2"><label for="dateFrom">Fecha de inicio</label><input type="date" id="dateFrom" class="form-control"></div>
                <div class="col-md-3 mb-2"><label for="dateTo">Fecha de fin</label><input type="date" id="dateTo" class="form-control"></div>
                <div class="col-md-3 mb-2"><label for="filterStatus">Estado</label><select id="filterStatus" class="form-control"><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select></div>
                <div class="col-md-3 mb-2 d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filtrar</button><button type="reset" class="btn btn-outline-secondary">Limpiar</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-striped" id="DataTable" style="width:100%">
                    <thead><tr><th>Fecha</th><th>Descripción</th><th>Estado</th><th>Día</th><th>Creación</th><th>Acciones</th></tr></thead>
                </table>
            </div>
        </div>
    </div>
    <div class="modal fade" id="formModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="formModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="formModalLabel">Nuevo Feriado</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"></div>
        </div></div>
    </div>
@stop

@section('js')
    <script>
        $(function () {
            const textRenderer = $.fn.dataTable.render.text();
            function errorMessage(xhr, fallback) {
                return xhr.responseJSON?.errors ? Object.values(xhr.responseJSON.errors).flat().join('\n') : xhr.responseJSON?.message || fallback;
            }
            const table = $('#DataTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: @json(route('admin.programming.holidays.index')),
                    data: data => { data.date_from = $('#dateFrom').val(); data.date_to = $('#dateTo').val(); data.status = $('#filterStatus').val(); },
                    error: xhr => Swal.fire('No se pudo cargar el listado', errorMessage(xhr, 'Intente nuevamente.'), 'error')
                },
                columns: [
                    { data: 'date', name: 'date' },
                    { data: 'description', name: 'description', render: textRenderer },
                    { data: 'is_active', name: 'is_active', render: value => value ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' },
                    { data: 'day_name', orderable: false, searchable: false, render: textRenderer },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false }
                ],
                order: [[0, 'asc']],
                language: { url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json' }
            });
            table.on('xhr', function (event, settings, json) {
                if (!json?.stats) return;
                $('#statTotal').text(json.stats.total);
                $('#statActive').text(json.stats.active);
                $('#statUpcoming').text(json.stats.upcoming);
            });
            $('#frmFiltros').on('submit', function (event) {
                event.preventDefault();
                if ($('#dateFrom').val() && $('#dateTo').val() && $('#dateTo').val() < $('#dateFrom').val()) {
                    Swal.fire('Revise las fechas', 'La fecha de fin debe ser igual o posterior a la fecha de inicio.', 'warning');
                    return;
                }
                table.ajax.reload();
            }).on('reset', function () { setTimeout(() => table.ajax.reload(), 0); });
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal'));
            function updateDay() {
                const value = $('#frmFeriado [name="date"]').val();
                $('#holidayDay').text(value ? 'Día: ' + new Intl.DateTimeFormat('es-PE', { weekday: 'long', timeZone: 'UTC' }).format(new Date(value + 'T12:00:00Z')) : 'Seleccione una fecha.');
            }
            function openForm(url, title, button) {
                button.prop('disabled', true);
                $.get(url).done(function (html) {
                    $('#formModalLabel').text(title);
                    $('#formModal .modal-body').html(html);
                    updateDay();
                    modal.show();
                }).fail(xhr => Swal.fire('Error', errorMessage(xhr, 'No se pudo cargar el formulario de feriados.'), 'error'))
                    .always(() => button.prop('disabled', false));
            }
            $('#btnNuevo').on('click', function () { openForm(@json(route('admin.programming.holidays.create')), 'Nuevo Feriado', $(this)); });
            $(document).on('click', '.btnEditar', function () {
                openForm(@json(route('admin.programming.holidays.edit', ':id')).replace(':id', $(this).data('id')), 'Editar Feriado', $(this));
            });
            $(document).on('change', '#frmFeriado [name="date"]', updateDay);
            $(document).on('submit', '#frmFeriado', function (event) {
                event.preventDefault();
                const form = $(this), button = form.find('[type="submit"]');
                if (button.prop('disabled')) return;
                button.prop('disabled', true);
                const errors = form.find('#holidayErrors').empty().addClass('d-none');
                $.ajax({ url: form.attr('action'), type: 'POST', data: form.serialize(), headers: { Accept: 'application/json' } })
                    .done(function (response) { modal.hide(); table.ajax.reload(null, false); Swal.fire('Éxito', response.message, 'success'); })
                    .fail(function (xhr) { $('<div>').text(errorMessage(xhr, 'No se pudo guardar el feriado. Intente nuevamente.')).css('white-space', 'pre-line').appendTo(errors); errors.removeClass('d-none'); })
                    .always(() => button.prop('disabled', false));
            });
            $(document).on('submit', '.frmEstado', function (event) {
                event.preventDefault();
                const form = $(this), button = form.find('[type="submit"]');
                if (button.prop('disabled')) return;
                button.prop('disabled', true);
                $.ajax({ url: form.attr('action'), type: 'POST', data: form.serialize(), headers: { Accept: 'application/json' } })
                    .done(function (response) { table.ajax.reload(null, false); Swal.fire('Éxito', response.message, 'success'); })
                    .fail(xhr => Swal.fire('Error', errorMessage(xhr, 'No se pudo cambiar el estado del feriado.'), 'error'))
                    .always(() => button.prop('disabled', false));
            });
            $(document).on('submit', '.frmEliminar', function (event) {
                event.preventDefault();
                const form = $(this), button = form.find('[type="submit"]');
                if (button.prop('disabled')) return;
                button.prop('disabled', true);
                Swal.fire({ title: '¿Eliminar feriado?', text: 'Esta acción no se puede deshacer.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' })
                    .then(function (result) {
                        if (!result.isConfirmed) { button.prop('disabled', false); return; }
                        $.ajax({ url: form.attr('action'), type: 'POST', data: form.serialize(), headers: { Accept: 'application/json' } })
                            .done(function (response) { table.ajax.reload(null, false); Swal.fire('Éxito', response.message, 'success'); })
                            .fail(xhr => Swal.fire('Error', errorMessage(xhr, 'No se pudo eliminar el feriado.'), 'error'))
                            .always(() => button.prop('disabled', false));
                    });
            });
            $('#btnCargar').on('click', function () {
                const button = $(this);
                const year = @json($stats['year']);
                const years = {};
                for (let candidate = year; candidate <= year + 5; candidate++) years[candidate] = candidate;
                Swal.fire({
                    title: '¿Cargar feriados de Perú?',
                    text: 'Seleccione el año. Los feriados nuevos estarán activos. Se conservarán la descripción y el estado de los que ya existen.',
                    input: 'select', inputOptions: years, inputValue: String(year), inputLabel: 'Año de los feriados nacionales',
                    icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, cargar', cancelButtonText: 'Cancelar'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    button.prop('disabled', true);
                    Swal.fire({ title: 'Cargando feriados…', text: 'Consultando el calendario de Perú.', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
                    $.ajax({ url: @json(route('admin.programming.holidays.import')), type: 'POST', data: { _token: @json(csrf_token()), year: result.value }, headers: { Accept: 'application/json' } })
                        .done(function (response) { table.ajax.reload(null, false); Swal.fire('Carga finalizada', response.message, 'success'); })
                        .fail(xhr => Swal.fire('No se pudieron cargar los feriados', errorMessage(xhr, 'Intente nuevamente o registre los feriados manualmente.'), 'error'))
                        .always(() => button.prop('disabled', false));
                });
            });
        });
    </script>
@stop
