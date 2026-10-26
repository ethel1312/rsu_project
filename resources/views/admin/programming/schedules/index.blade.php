@extends('adminlte::page')

@section('title', 'Programaciones')
@section('plugins.Select2', true)
@section('plugins.DatatablesButtons', true)

@section('content_header')
    <div class="rsu-title-bar d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
        <h1 class="rsu-title m-0"><span class="rsu-title-ico"><i class="bi bi-calendar2-week"></i></span> Lista de Programaciones</h1>
        <div class="d-flex flex-wrap" style="gap:8px">
            <button type="button" id="btnScheduleHistoryAll" class="btn btn-outline-info"><i class="bi bi-clock-history"></i> Historial</button>
            <button type="button" id="btnProgramaMasiva" class="btn btn-outline-primary"><i class="bi bi-calendar-range"></i> Programación masiva</button>
            <button type="button" id="btnNuevaPrograma" class="btn rsu-btn-new"><i class="bi bi-plus-circle"></i> Nueva programación</button>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/dist/css/adminlte-select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        #formModal .modal-dialog, #historyModal .modal-dialog { height:calc(100% - 3.5rem); }
        #formModal .modal-content, #historyModal .modal-content { max-height:100%; overflow:hidden; }
        #formModal .modal-body, #historyModal .modal-body { overflow-y:auto; min-height:0; overscroll-behavior:contain; }
        .btn-modal-cancelar { background:#edf2f7; color:#2b549a; border-radius:8px; padding:8px 18px; }
        .btn-modal-guardar { background:#38bdf8; color:#fff; border-radius:8px; padding:8px 22px; }
        #formModal .select2-container { width:100% !important; }
        #formModal .select2-selection { min-height:38px; }
        #formModal .select2-selection--multiple { align-items:center; gap:.375rem; }
        #formModal .schedule-group-summary { padding:.7rem .85rem; border:1px solid #dfe6f5; border-radius:.5rem; background:#f8faff; }
        #formModal .schedule-group-summary .col-md-3 { display:flex; flex-direction:column; gap:.15rem; }
        #formModal .schedule-group-summary small { color:#6b7388; font-size:.72rem; }
        #formModal .schedule-group-summary strong { color:#12206b; font-size:.88rem; }
        #DataTable td { vertical-align:middle; }
        #DataTable .schedule-status, #DataTable .schedule-shift { display:inline-block; min-width:84px; padding:.45rem .7rem; border-radius:.5rem; font-weight:600; }
        #DataTable .schedule-status-planned { color:#12647a; background:#d8f2f8; }
        #DataTable .schedule-status-finished { color:#166534; background:#dcf2e1; }
        #DataTable .schedule-shift { color:#154a83; background:#e7f2ff; }
        #DataTable .schedule-vehicle { display:inline-block; padding:.5rem .65rem; border:1px solid #dfe6f5; border-radius:.5rem; color:#17346b; background:#f8faff; }
        #DataTable .schedule-helper { display:block; margin:.15rem 0; padding:.2rem .45rem; border-radius:.25rem; color:#43516a; background:#edf1f6; }
        #scheduleAvailability ul { margin-bottom:0; }
        #scheduleAvailability.alert-danger { color:#fff; background:#dc3545; border-color:#dc3545; }
        #scheduleAvailability .schedule-message-section + .schedule-message-section { margin-top:.85rem; padding-top:.75rem; border-top:1px solid rgba(255,255,255,.3); }
        #scheduleAvailability .schedule-message-warning { padding:.65rem .8rem; border-radius:.35rem; background:#fff3cd; color:#664d03; border:1px solid #ffecb5; }
    </style>
@stop

@section('content')
    <div class="alert alert-info small">Valide la disponibilidad antes de guardar. Las vacaciones pendientes se muestran como advertencia; las aprobadas, feriados, inasistencias y cruces de personal o vehículos omiten únicamente los días afectados; los demás días sí se programan.</div>
    <div class="card">
        <div class="card-body">
            <div class="row g-2 align-items-end mb-3 schedule-list-filters">
                <div class="col-6 col-lg-2">
                    <label for="scheduleDateFrom" class="mb-1">Fecha desde</label>
                    <input type="date" id="scheduleDateFrom" class="form-control schedule-list-filter">
                </div>
                <div class="col-6 col-lg-2">
                    <label for="scheduleDateTo" class="mb-1">Fecha hasta</label>
                    <input type="date" id="scheduleDateTo" class="form-control schedule-list-filter">
                </div>
                <div class="col-6 col-lg-2">
                    <label for="scheduleZoneFilter" class="mb-1">Zona</label>
                    <select id="scheduleZoneFilter" class="form-control schedule-list-filter">
                        <option value="">Todas las zonas</option>
                        @foreach ($zones as $zone)<option value="{{ $zone->id }}">{{ $zone->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="scheduleShiftFilter" class="mb-1">Turno</label>
                    <select id="scheduleShiftFilter" class="form-control schedule-list-filter">
                        <option value="">Todos los turnos</option>
                        @foreach ($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-2">
                    <button type="button" id="clearScheduleFilters" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Limpiar</button>
                </div>
            </div>
            <div id="scheduleTableButtons" class="d-flex flex-wrap mb-2" style="gap:6px"></div>
            <div class="table-responsive">
                <table class="table table-striped" id="DataTable" style="width:100%">
                    <thead><tr>
                        <th>Fecha</th><th>Estado</th><th>Zona</th><th>Turno</th><th>Vehículo</th>
                        <th>Conductor</th><th>Ayudantes</th><th>Acciones</th>
                    </tr></thead>
                </table>
            </div>
        </div>
    </div>
    <div class="modal fade" id="formModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="formModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="formModalLabel">Nueva programación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"></div>
        </div></div>
    </div>
    <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historyModalLabel">Detalles del grupo e historial</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"></div>
        </div></div>
    </div>
@stop

@section('js')
<script>
$(function () {
    const table = $('#DataTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: @json(route('admin.programming.schedules.index')),
            data: function (data) {
                data.date_from = $('#scheduleDateFrom').val();
                data.date_to = $('#scheduleDateTo').val();
                data.zone_id = $('#scheduleZoneFilter').val();
                data.shift_id = $('#scheduleShiftFilter').val();
            }
        },
        dom: '<"d-flex justify-content-between align-items-center flex-wrap mb-2"l f>rt<"d-flex justify-content-between align-items-center flex-wrap mt-2"i p>',
        buttons: [
            { extend:'excelHtml5', text:'<i class="bi bi-file-earmark-excel"></i> Excel', className:'btn btn-success btn-sm', title:'Lista de Programaciones', exportOptions:{columns:':visible:not(:last-child)'} },
            { extend:'pdfHtml5', text:'<i class="bi bi-file-earmark-pdf"></i> PDF', className:'btn btn-danger btn-sm', title:'Lista de Programaciones', orientation:'landscape', pageSize:'A4', exportOptions:{columns:':visible:not(:last-child)'} },
            { extend:'print', text:'<i class="bi bi-printer"></i> Imprimir', className:'btn btn-secondary btn-sm', title:'Lista de Programaciones', exportOptions:{columns:':visible:not(:last-child)'} },
            { extend:'colvis', text:'<i class="bi bi-layout-three-columns"></i> Columnas', className:'btn btn-outline-secondary btn-sm' }
        ],
        columns: [
            {data:'date', name:'date', render:function (data, type, row) { return type === 'sort' ? row.date_sort : data; }},
            {data:'status_badge', name:'status_badge', searchable:true},
            {data:'zone_name', name:'zone_name'},
            {data:'shift_html', name:'shift_name'},
            {data:'vehicle_html', name:'vehicle_label'},
            {data:'driver_name', name:'driver_name'},
            {data:'helpers_html', name:'helpers_label', orderable:false},
            {data:'actions', name:'actions', orderable:false, searchable:false}
        ],
        order: [[0, 'asc']],
        language: {url:'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'},
        initComplete: function () { this.api().buttons().container().appendTo('#scheduleTableButtons'); }
    });
    $('.schedule-list-filter').on('change', function () { table.ajax.reload(); });
    $('#clearScheduleFilters').on('click', function () {
        $('#scheduleDateFrom, #scheduleDateTo').val('');
        $('#scheduleZoneFilter, #scheduleShiftFilter').val('');
        table.ajax.reload();
    });
    const formModalElement = document.getElementById('formModal');
    const formModal = bootstrap.Modal.getOrCreateInstance(formModalElement);
    const formBody = $('#formModal .modal-body');
    const historyModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('historyModal'));

    function destroySearches() {
        $('#formModal .schedule-select2.select2-hidden-accessible').each(function () {
            $(this).off('.scheduleSelect').select2('destroy');
        });
    }
    function openForm(url, title, button) {
        if (button) button.prop('disabled', true);
        $.get(url).done(function (html) {
            destroySearches();
            formBody.html(html);
            formBody.find('[data-change-key]').each(function () {
                const select = $(this);
                select.data('initial-values', JSON.stringify([].concat(select.val() || []).sort()));
                select.data('initial-label', select.find('option:selected').map(function () { return $(this).text(); }).get().join(', '));
            });
            $('#formModalLabel').text(title);
            formModal.show();
        }).fail(function (xhr) {
            Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo cargar el formulario de programación.', 'error');
        }).always(function () {
            if (button) button.prop('disabled', false);
        });
    }
    function initSelects() {
        if (!$.fn.select2) return;
        $('#formModal .schedule-select2').each(function () {
            const select = $(this);
            if (select.hasClass('select2-hidden-accessible')) return;
            select.select2({
                dropdownParent: $(formModalElement),
                width: '100%',
                placeholder: select.prop('multiple') ? 'Seleccione uno o varios' : 'Buscar grupo o personal',
                closeOnSelect: !select.prop('multiple')
            });
        });
    }
    $(formModalElement).on('shown.bs.modal', initSelects).on('hidden.bs.modal', function () {
        holidayVersion++;
        invalidateAvailability();
        destroySearches();
        formBody.empty();
    });

    let availabilityVersion = 0;
    function invalidateAvailability() {
        availabilityVersion++;
        $('#availabilityToken').val('');
        $('#btnGuardarSchedule').prop('disabled', true);
        $('#scheduleAvailability').addClass('d-none').removeClass('alert-success alert-warning alert-danger').empty();
        $('#scheduleErrors').addClass('d-none').empty();
    }
    $(document).on('change input', '#frmSchedule .schedule-input', invalidateAvailability);

    $(document).on('change', '#schedule_group_id', function () {
        const groupId = $(this).val();
        const summary = $('#scheduleGroupSummary');
        invalidateAvailability();
        if (!groupId) {
            summary.addClass('d-none');
            $('#scheduleSummaryGroup, #scheduleSummaryZone, #scheduleSummaryShift, #scheduleSummaryVehicle').text('—');
            return;
        }
        summary.removeClass('d-none');
        $('#scheduleSummaryGroup, #scheduleSummaryZone, #scheduleSummaryShift, #scheduleSummaryVehicle').text('Cargando…');
        const url = @json(route('admin.programming.schedules.groups.data', ':id')).replace(':id', groupId);
        $.get(url).done(function (data) {
            $('#schedule_driver_id').val(String(data.driver_id)).trigger('change.select2');
            $('#schedule_helper_ids').val(data.helper_ids.map(String)).trigger('change.select2');
            $('input[name="days_of_week[]"]').each(function () {
                $(this).prop('checked', data.days_of_week.includes(Number($(this).val())));
            });
            $('#scheduleSummaryGroup').text(data.group_name || '—');
            $('#scheduleSummaryZone').text(data.zone || '—');
            $('#scheduleSummaryShift').text(data.shift_label || data.shift || '—');
            $('#scheduleSummaryVehicle').text(data.vehicle || '—');
        }).fail(function (xhr) {
            summary.addClass('d-none');
            Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo cargar la plantilla del grupo.', 'error');
        });
    });

    $(document).on('input change', '#frmSchedule .schedule-input', function () {
        const body = $('#dayChanges');
        if (!body.length) return;
        body.empty();
        const labels = {turno:'Turno', vehiculo:'Vehículo', conductor:'Conductor', ayudantes:'Ayudantes'};
        $('#frmSchedule [data-change-key]').each(function () {
            const select = $(this);
            if (JSON.stringify([].concat(select.val() || []).sort()) === select.data('initial-values')) return;
            const field = select.data('change-key');
            const row = $('<tr>');
            $('<td>').text(labels[field]).appendTo(row);
            $('<td>').text(select.data('initial-label')).appendTo(row);
            $('<td>').text(select.find('option:selected').map(function () { return $(this).text(); }).get().join(', ')).appendTo(row);
            $('<td>').text($('#frmSchedule [name="reasons[' + field + ']"]').val() || 'Motivo pendiente').appendTo(row);
            body.append(row);
        });
        if (!body.children().length) body.append('<tr><td colspan="4" class="text-muted">Todavía no hay cambios.</td></tr>');
    });

    $(document).on('change', '#massShiftFilter, .mass-group-check', function () {
        const shift = $('#massShiftFilter').val();
        $('.mass-group').each(function () {
            const card = $(this);
            const visible = !shift || String(card.data('shift')) === shift;
            card.toggleClass('d-none', !visible);
            const checkbox = card.find('.mass-group-check');
            checkbox.prop('disabled', !visible);
            card.find('.mass-crew').prop('disabled', !visible || !checkbox.prop('checked'));
        });
        invalidateAvailability();
    });
    let holidayVersion = 0;
    $(document).on('change', '#schedule_start_date, #schedule_end_date', function () {
        if (!$('#massHolidays').length) return;
        const version = ++holidayVersion;
        const start = $('#schedule_start_date').val();
        const end = $('#schedule_end_date').val();
        $('#holidaysConfigured').val('0');
        const container = $('#massHolidays').empty();
        if (!start || !end || start > end) {
            $('#btnValidateAvailability').prop('disabled', false);
            container.text('Seleccione un rango de fechas válido.');
            return;
        }
        container.text('Cargando feriados…');
        $('#btnValidateAvailability').prop('disabled', true);
        $.get(@json(route('admin.programming.schedules.holidays')), {start_date:start, end_date:end})
            .done(function (holidays) {
                if (version !== holidayVersion) return;
                container.empty();
                holidays.forEach(holiday => {
                    const label = $('<label class="d-block">');
                    $('<input type="checkbox" name="holiday_ids[]" class="schedule-input" checked>')
                        .val(holiday.id).appendTo(label);
                    label.append(document.createTextNode(' ' + holiday.date.substring(0,10).split('-').reverse().join('/') + ' · ' + holiday.description));
                    container.append(label);
                });
                if (!holidays.length) container.text('No hay feriados activos en este rango.');
                $('#holidaysConfigured').val('1');
            }).fail(function () {
                if (version !== holidayVersion) return;
                container.text('No se pudieron cargar los feriados. Se omitirán todos los feriados activos al validar.');
            }).always(function () {
                if (version === holidayVersion) $('#btnValidateAvailability').prop('disabled', false);
            });
    });

    $('#btnScheduleHistoryAll').on('click', function () {
        $('#historyModal .modal-body').html('<div class="text-center p-4">Cargando historial…</div>');
        $.get(@json(route('admin.programming.schedules.history.all'))).done(function (html) {
            $('#historyModal .modal-body').html(html);
            historyModal.show();
        }).fail(function () {
            Swal.fire('Error', 'No se pudo cargar el historial general.', 'error');
        });
    });
    $('#btnNuevaPrograma').on('click', function () {
        openForm(@json(route('admin.programming.schedules.create')), 'Nueva programación', $(this));
    });
    $('#btnProgramaMasiva').on('click', function () {
        openForm(@json(route('admin.programming.schedules.create-mass')), 'Programación masiva', $(this));
    });
    $(document).on('click', '.btnEditSchedule', function () {
        openForm($(this).data('url'), 'Editar programación', $(this));
    });

    function appendMessageSection(container, title, items, extraClass = '') {
        if (!items?.length) return;
        const section = $('<div>').addClass('schedule-message-section ' + extraClass);
        $('<strong>').text(title).appendTo(section);
        const list = $('<ul class="mt-2 mb-0">').appendTo(section);
        items.forEach(item => $('<li>').text(item).appendTo(list));
        container.append(section);
    }

    $(document).on('click', '#btnValidateAvailability', function () {
        const version = ++availabilityVersion;
        const form = $('#frmSchedule');
        const button = $(this);
        const errors = $('#scheduleErrors').empty().addClass('d-none');
        const message = $('#scheduleAvailability').removeClass('d-none').removeClass('alert-success alert-warning alert-danger').addClass('alert-info').text('Validando disponibilidad…');
        $('#availabilityToken').val('');
        $('#btnGuardarSchedule').prop('disabled', true);
        button.prop('disabled', true);
        $.ajax({
            url: @json(route('admin.programming.schedules.availability')),
            type: 'POST',
            data: form.serializeArray().filter(field => field.name !== '_method'),
            headers: {Accept:'application/json'}
        }).done(function (result) {
            if (version !== availabilityVersion) return;
            $('#availabilityToken').val(result.token);
            $('#btnGuardarSchedule').prop('disabled', false);
            const warnings = result.warnings || [];
            const omissions = result.omissions || [];
            message.removeClass('alert-info').addClass(warnings.length || omissions.length ? 'alert-warning' : 'alert-success').empty();
            $('<div>').text(result.message + ' Días a programar: ' + result.service_days + '. Días omitidos: ' + result.skipped_days + '.').appendTo(message);
            appendMessageSection(message, 'Días que no se programarán:', omissions);
            appendMessageSection(message, 'Advertencias (no bloquean):', warnings);
            appendMessageSection(message, 'Sugerencias:', result.suggestions || []);
        }).fail(function (xhr) {
            if (version !== availabilityVersion) return;
            const problems = xhr.responseJSON?.conflicts || (xhr.responseJSON?.errors
                ? Object.values(xhr.responseJSON.errors).flat()
                : [xhr.responseJSON?.message || 'No se pudo validar la disponibilidad.']);
            const warnings = xhr.responseJSON?.warnings || [];
            message.removeClass('alert-info alert-warning').addClass('alert-danger').empty();
            appendMessageSection(message, 'Hay errores que corregir:', problems);
            appendMessageSection(message, 'Días que no se programarán:', xhr.responseJSON?.omissions || []);
            appendMessageSection(message, 'Sugerencias:', xhr.responseJSON?.suggestions || []);
            appendMessageSection(message, 'Advertencias (no bloquean la programación):', warnings, 'schedule-message-warning');
        }).always(function () {
            button.prop('disabled', false);
        });
    });

    $(document).on('submit', '#frmSchedule', function (event) {
        event.preventDefault();
        const form = $(this);
        const button = $('#btnGuardarSchedule');
        if (button.prop('disabled') || !$('#availabilityToken').val()) return;
        button.prop('disabled', true);
        $.ajax({
            url: form.attr('action'), type: 'POST', data: form.serialize(),
            headers: {Accept:'application/json'}
        }).done(function (result) {
            formModal.hide();
            table.ajax.reload(null, false);
            const details = [...(result.omissions || []), ...(result.warnings || [])];
            const counts = result.service_days !== undefined
                ? ' Días programados: ' + result.service_days + '. Días omitidos: ' + result.skipped_days + '.' : '';
            Swal.fire({
                title: details.length ? 'Programación guardada con avisos' : 'Éxito',
                text: result.message + counts + (details.length ? '\n' + details.join('\n') : ''),
                icon: details.length ? 'warning' : 'success'
            });
        }).fail(function (xhr) {
            const problems = xhr.responseJSON?.conflicts || (xhr.responseJSON?.errors
                ? Object.values(xhr.responseJSON.errors).flat()
                : [xhr.responseJSON?.message || 'No se pudo guardar la programación.']);
            const warnings = xhr.responseJSON?.warnings || [];
            const message = $('#scheduleAvailability').removeClass('d-none alert-info alert-success alert-warning').addClass('alert-danger').empty();
            appendMessageSection(message, 'Hay errores que corregir:', problems);
            appendMessageSection(message, 'Días que no se programarán:', xhr.responseJSON?.omissions || []);
            appendMessageSection(message, 'Sugerencias:', xhr.responseJSON?.suggestions || []);
            appendMessageSection(message, 'Advertencias (no bloquean la programación):', warnings, 'schedule-message-warning');
            $('#availabilityToken').val('');
        }).always(function () {
            button.prop('disabled', !$('#availabilityToken').val());
        });
    });

    $(document).on('click', '.btnScheduleHistory', function () {
        $('#historyModal .modal-body').html('<div class="text-center p-4">Cargando historial…</div>');
        $.get($(this).data('url')).done(function (html) {
            $('#historyModal .modal-body').html(html);
            historyModal.show();
        }).fail(function () {
            Swal.fire('Error', 'No se pudo cargar el historial.', 'error');
        });
    });

    $(document).on('click', '.btnFinishSchedule', function () {
        const button = $(this);
        Swal.fire({
            title:'¿Finalizar programación?',
            text:'La programación dejará de bloquear disponibilidad futura.',
            icon:'question',
            showCancelButton:true,
            confirmButtonText:'Sí, finalizar',
            cancelButtonText:'Cancelar'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            button.prop('disabled', true);
            $.ajax({
                url:button.data('url'), type:'PATCH',
                data:{_token:$('meta[name="csrf-token"]').attr('content')},
                headers:{Accept:'application/json'}
            }).done(function (response) {
                table.ajax.reload(null, false);
                Swal.fire('Finalizada', response.message, 'success');
            }).fail(function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo finalizar la programación.', 'error');
            }).always(function () { button.prop('disabled', false); });
        });
    });

    $(document).on('submit', '.frmDeleteSchedule', function (event) {
        event.preventDefault();
        const form = $(this);
        Swal.fire({
            title:'¿Eliminar programación?',
            text:'El cambio quedará registrado en el historial.',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'Sí, eliminar',
            cancelButtonText:'Cancelar'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url:form.attr('action'), type:'DELETE', data:form.serialize(),
                headers:{Accept:'application/json'}
            }).done(function (response) {
                table.ajax.reload(null, false);
                Swal.fire('Eliminada', response.message, 'success');
            }).fail(function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar la programación.', 'error');
            });
        });
    });
});
</script>
@stop



