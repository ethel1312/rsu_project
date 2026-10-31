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
        .btn-modal-cancelar { background:#edf2f7; color:#2b549a; border-radius:8px; padding:8px 18px; }
        .btn-modal-guardar { background:#38bdf8; color:#fff; border-radius:8px; padding:8px 22px; }
        #formModal .modal-dialog { margin:.5rem auto; }
        #formModal .modal-dialog-scrollable .modal-content { max-height:calc(100vh - 1rem); overflow:hidden; }
        #formModal .modal-dialog-scrollable .modal-body { min-height:0; overflow-y:auto; overflow-x:hidden; overscroll-behavior:contain; scrollbar-gutter:stable; }
        #formModal .select2-container { width:100% !important; }
        #formModal .select2-selection { min-height:38px; }
        #formModal .select2-selection--multiple { align-items:center; gap:.375rem; }
        #formModal .schedule-group-summary { padding:.7rem .85rem; border:1px solid #dfe6f5; border-radius:.5rem; background:#f8faff; }
        #formModal .schedule-group-summary .col-md-3 { display:flex; flex-direction:column; gap:.15rem; }
        #formModal .schedule-group-summary small { color:#6b7388; font-size:.72rem; }
        #formModal .schedule-group-summary strong { color:#12206b; font-size:.88rem; }
        #formModal .mass-group-header { position:relative; display:flex; align-items:center; min-height:68px; padding-right:3.5rem; }
        #formModal .mass-group-title { margin:0; color:#43516a; font-weight:700; }
        #formModal .mass-group-remove { position:absolute; top:50%; right:.85rem; display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; padding:0; transform:translateY(-50%); }
        #formModal .mass-group-meta { display:grid; gap:.4rem; color:#343a40; }
        #formModal .mass-group-badge { display:inline-block; padding:.35rem .55rem; font-size:.8rem; vertical-align:middle; }
        #formModal .mass-group-shift { color:#fff; background:#0d6efd; }
        #formModal .mass-group-vehicle { color:#154a83; background:#e7f2ff; border:1px solid #cfe2ff; }
        #formModal .day-change-card .card-header { min-height:44px; font-weight:700; }
        #formModal .day-change-card .card-body { padding:.9rem; }
        #formModal .day-change-card label { margin-bottom:.3rem; font-weight:600; }
        #formModal .day-reason-description { min-height:64px; }
        #formModal .day-change-availability { padding:.45rem .6rem; border-radius:.25rem; font-size:.82rem; }
        #formModal .day-change-availability.is-available { color:#155724; background:#d4edda; border:1px solid #c3e6cb; }
        #formModal .day-change-availability.is-unavailable { color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; }
        #formModal .day-registered-changes .table { min-width:680px; }
        #formModal .day-registered-changes th { white-space:nowrap; }
        #formModal .mass-crew-feedback { margin-top:.25rem; margin-bottom:0; padding:.35rem .5rem; font-size:.75rem; line-height:1.35; }
        #formModal .mass-crew.is-invalid + .select2-container .select2-selection { border-color:#dc3545 !important; background-color:#f8d7da !important; }
        #formModal .mass-crew.is-invalid + .select2-container .select2-selection__rendered { background-color:transparent; }
        #formModal .mass-crew.is-warning + .select2-container .select2-selection { border-color:#ffc107 !important; background-color:#fff3cd !important; }
        #DataTable td { vertical-align:middle; }
        #DataTable .schedule-actions-row { flex-wrap:nowrap !important; min-width:max-content; white-space:nowrap; }
        #DataTable .schedule-actions-row .frmDeleteSchedule { display:inline-flex !important; flex:0 0 auto; margin:0 !important; }
        #DataTable .schedule-action { flex:0 0 40px; }
        #DataTable .schedule-action {
            display:inline-flex; align-items:center; justify-content:center;
            width:40px; height:40px; padding:0; border-radius:.375rem;
            transition:background-color .2s ease, color .2s ease, border-color .2s ease;
        }
        #DataTable .schedule-action-history { background:#e5f5fb; border:1px solid #c7e8f3; color:#137b9b; }
        #DataTable .schedule-action-history:hover { background:#1599bc; border-color:#1599bc; color:#fff; }
        #DataTable .schedule-action-edit { background:#e3edff; border:1px solid #d1e0ff; color:#1746a2; }
        #DataTable .schedule-action-edit:hover { background:#2563eb; border-color:#2563eb; color:#fff; }
        #DataTable .schedule-action-finish { background:#e1f3e6; border:1px solid #c5e7cf; color:#198754; }
        #DataTable .schedule-action-finish:hover { background:#198754; border-color:#198754; color:#fff; }
        #DataTable .frmDeleteSchedule .schedule-action-delete { background:#ffe5e8; border:1px solid #ffd1d8; color:#c62843; }
        #DataTable .frmDeleteSchedule .schedule-action-delete:hover { background:#dc3545; border-color:#dc3545; color:#fff; }
        #DataTable .schedule-status, #DataTable .schedule-shift { display:inline-block; min-width:84px; padding:.45rem .7rem; border-radius:.5rem; font-weight:600; }
        #DataTable .schedule-status-planned { color:#12647a; background:#d8f2f8; }
        #DataTable .schedule-status-finished { color:#166534; background:#dcf2e1; }
        #DataTable .schedule-status-rescheduled { color:#7145a5; background:#eee5fa; }
        #DataTable .schedule-shift { color:#154a83; background:#e7f2ff; }
        #DataTable .schedule-vehicle { display:inline-block; padding:.5rem .65rem; border:1px solid #dfe6f5; border-radius:.5rem; color:#17346b; background:#f8faff; }
        #DataTable .schedule-helper { display:block; margin:.15rem 0; padding:.2rem .45rem; border-radius:.25rem; color:#43516a; background:#edf1f6; }
        #scheduleAvailability ul { margin-bottom:0; }
        #scheduleAvailability.alert-danger { color:#fff; background:#dc3545; border-color:#dc3545; }
        #scheduleAvailability .schedule-message-section + .schedule-message-section { margin-top:.85rem; padding-top:.75rem; border-top:1px solid rgba(255,255,255,.3); }
        #scheduleAvailability .schedule-message-warning { padding:.65rem .8rem; border-radius:.35rem; background:#fff3cd; color:#664d03; border:1px solid #ffecb5; }
        #scheduleAvailability .schedule-skipped-list > li { margin-bottom:.65rem; }
        #scheduleAvailability .schedule-skipped-list details summary { color:#1746a2; cursor:pointer; font-size:.9em; }
        #scheduleAvailability .schedule-skipped-dates { overflow-wrap:anywhere; }
        #scheduleAvailability.schedule-validation-results { border:0; padding:0; background:transparent; color:inherit; }
        #scheduleAvailability .schedule-validation-title { margin:0 0 .75rem; color:#dc3545; font-size:1rem; font-weight:600; }
        #scheduleAvailability .schedule-validation-group { margin-bottom:.75rem; overflow:hidden; border:1px solid #d6e4f0; border-radius:.4rem; }
        #scheduleAvailability .schedule-validation-group > summary { display:flex; align-items:center; gap:.5rem; padding:.7rem .85rem; background:#e3f2fd; color:#343a40; cursor:pointer; list-style:none; }
        #scheduleAvailability .schedule-validation-group > summary::-webkit-details-marker { display:none; }
        #scheduleAvailability .schedule-validation-group-title { font-weight:700; }
        #scheduleAvailability .schedule-validation-badges { display:flex; gap:.4rem; margin-left:auto; }
        #scheduleAvailability .schedule-validation-chevron { flex:0 0 auto; margin-left:.15rem; color:#495057; transition:transform .18s ease; }
        #scheduleAvailability .schedule-validation-group[open] > summary .schedule-validation-chevron { transform:rotate(180deg); }
        #scheduleAvailability .schedule-validation-badge { padding:.2rem .45rem; border-radius:.25rem; color:#fff; font-size:.72rem; font-weight:700; white-space:nowrap; }
        #scheduleAvailability .schedule-validation-badge-error { background:#dc3545; }
        #scheduleAvailability .schedule-validation-badge-warning { background:#ffc107; color:#212529; }
        #scheduleAvailability .schedule-validation-content { margin:.55rem; padding:.7rem .8rem; border-left:3px solid #0d6efd; border-radius:.3rem; background:#e7f3fb; }
        #scheduleAvailability .schedule-validation-content.has-errors { border-left-color:#dc3545; background:#f8d7da; }
        #scheduleAvailability .schedule-validation-content.has-warnings:not(.has-errors) { border-left-color:#ffc107; background:#fff3cd; }
        #scheduleAvailability .schedule-validation-section + .schedule-validation-section { margin-top:.6rem; }
        #scheduleAvailability .schedule-validation-section-title { display:block; margin-bottom:.2rem; font-weight:700; }
        #scheduleAvailability .schedule-validation-section ul { padding-left:1.25rem; }
        #scheduleAvailability .schedule-validation-entry { margin-bottom:.2rem; }
        #scheduleAvailability .schedule-validation-entry details summary { color:#1746a2; cursor:pointer; }
        #historyModal .modal-dialog { max-width:min(1100px, 92vw); }
        #historyModal .modal-body { max-height:calc(100vh - 150px); overflow-y:auto; }
        #historyModal .history-detail-card { overflow:hidden; border:0; border-radius:.35rem; box-shadow:0 2px 8px rgba(0,0,0,.12); }
        #historyModal .history-detail-card > .card-header { padding:.75rem 1rem; color:#fff; font-weight:700; }
        #historyModal .history-personnel-header { background:#28a745; }
        #historyModal .history-changes-header { background:#087fca; }
        #historyModal .history-detail-card th { background:#f5f7f9; color:#495057; font-size:.78rem; text-transform:uppercase; }
        #historyModal .history-role-badge { display:inline-block; min-width:92px; padding:.35rem .55rem; border-radius:.25rem; color:#fff; font-size:.75rem; font-weight:700; text-align:center; }
        #historyModal .history-role-driver { background:#0789c7; }
        #historyModal .history-role-helper { background:#28a745; }
        #historyModal .history-person-icon { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; margin-right:.6rem; border-radius:50%; color:#fff; background:#0789c7; }
        #historyModal .history-person-icon.is-helper { background:#28a745; }
        #historyModal .history-type-badge { display:inline-block; padding:.3rem .55rem; border-radius:1rem; color:#12345a; background:#e7f2ff; font-size:.78rem; font-weight:700; white-space:nowrap; }
        #historyModal .history-reason { min-width:150px; max-width:280px; overflow-wrap:anywhere; }
        #historyModal .modal-footer .btn-detail-close { min-width:120px; color:#fff; background:#dc3545; }
        #scheduleAvailability .schedule-validation-entry .schedule-skipped-dates { margin-left:.2rem; }
        .schedule-mass-section-title { font-weight:700 !important; color:#343a40; }
    </style>
@stop

@section('content')
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
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historyModalLabel">Historial de cambios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-detail-close" data-bs-dismiss="modal"><i class="bi bi-x-lg mr-1" aria-hidden="true"></i>Cerrar</button>
            </div>
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
    const formModalRoot = $('#formModal');
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
            select.select2({
                dropdownParent: formModalRoot,
                width: '100%',
                placeholder: select.prop('multiple') ? 'Seleccione uno o varios' : 'Buscar grupo o personal',
                closeOnSelect: !select.prop('multiple')
            });
            select.on('select2:opening.scheduleSelect', function () {
                const body = formBody[0];
                const control = select.next('.select2-container')[0];
                if (!body || !control) return;

                const controlBottom = control.getBoundingClientRect().bottom;
                const modalBottom = formModalElement.getBoundingClientRect().bottom;
                const visibleBottom = Math.min(window.innerHeight - 16, modalBottom - 16);
                const roomBelow = visibleBottom - controlBottom;
                const neededRoom = 260;

                if (roomBelow < neededRoom) {
                    body.scrollTop += neededRoom - roomBelow;
                }
            });
        });
    }
    let dayEditBaseState = null;
    let dayEditChanges = [];

    function dayOptionText(select, value) {
        return $(select).find('option').filter(function () { return String(this.value) === String(value); }).text().trim();
    }

    function dayEmployeeOption(employeeId) {
        return $('#dayPersonnelCatalog option').filter(function () { return String(this.value) === String(employeeId); }).first();
    }

    function dayEmployeeLabel(employeeId) {
        return dayEmployeeOption(employeeId).text().trim();
    }

    function dayEmployeeRole(employeeId) {
        return dayEmployeeOption(employeeId).data('role');
    }

    function renderDayChanges() {
        if (!$('#dayChanges').length) return;
        const body = $('#dayChanges').empty();
        if (!dayEditChanges.length) {
            $('<tr><td colspan="6" class="text-center text-muted py-3">No hay cambios registrados. Agregue cambios usando los botones superiores.</td></tr>').appendTo(body);
            $('#btnGuardarSchedule').prop('disabled', true);
        } else {
            dayEditChanges.forEach(change => {
                const row = $('<tr>').appendTo(body);
                $('<td>').text(change.typeLabel).appendTo(row);
                $('<td>').text(change.beforeLabel).appendTo(row);
                $('<td>').text(change.afterLabel).appendTo(row);
                $('<td>').text(change.reasonType).appendTo(row);
                $('<td>').text(change.description).appendTo(row);
                const action = $('<td class="text-center">').appendTo(row);
                $('<button type="button" class="btn btn-sm btn-outline-danger day-remove-change" title="Eliminar cambio" aria-label="Eliminar cambio">')
                    .attr('data-change-key', change.key)
                    .append('<i class="bi bi-x-lg" aria-hidden="true"></i>')
                    .appendTo(action);
            });
        }

        const combinedReason = dayEditChanges.map(change => change.typeLabel + ' (' + change.reasonType + '): ' + change.description).join(' | ');
        $('#dayChangeReason').val(combinedReason);
        $('#dayChangesPayload').val(JSON.stringify(dayEditChanges));
    }

    function rebuildDayPersonnelSelectors(state) {
        const currentSelect = $('#dayPersonnelCurrent');
        const nextSelect = $('#dayPersonnelNew');
        if (!currentSelect.length || !nextSelect.length) return;

        const previousCurrent = currentSelect.val();
        const currentCrew = [String(state.driver_id), ...state.helper_ids.map(String)];
        currentSelect.empty().append($('<option value="">').text('Seleccione un personal'));
        currentCrew.forEach(employeeId => {
            const option = dayEmployeeOption(employeeId);
            if (!option.length) return;
            $('<option>').val(employeeId).attr('data-role', option.data('role')).text(option.text()).appendTo(currentSelect);
        });
        const currentValue = currentCrew.includes(String(previousCurrent)) ? String(previousCurrent) : (currentCrew[0] || '');
        currentSelect.val(currentValue).trigger('change.select2');

        const role = dayEmployeeRole(currentValue);
        const previousNew = nextSelect.val();
        nextSelect.find('option').not(':first').remove();
        $('#dayPersonnelCatalog option').each(function () {
            const employeeId = String(this.value);
            if ($(this).data('role') !== role || currentCrew.includes(employeeId)) return;
            $('<option>').val(employeeId).attr('data-role', role).text($(this).text()).appendTo(nextSelect);
        });
        nextSelect.val(nextSelect.find('option').filter(function () { return String(this.value) === String(previousNew); }).length ? previousNew : '').trigger('change.select2');
    }

    function applyDayEditChanges() {
        if (!dayEditBaseState) return;
        const state = {
            shift_id: dayEditBaseState.shift_id,
            vehicle_id: dayEditBaseState.vehicle_id,
            driver_id: dayEditBaseState.driver_id,
            helper_ids: dayEditBaseState.helper_ids.slice()
        };
        dayEditChanges.forEach(change => {
            if (change.type === 'shift') state.shift_id = change.newValue;
            if (change.type === 'vehicle') state.vehicle_id = change.newValue;
            if (change.type === 'driver') state.driver_id = change.newValue;
            if (change.type === 'helper') {
                const index = state.helper_ids.findIndex(id => String(id) === String(change.fromValue));
                if (index !== -1) state.helper_ids[index] = change.newValue;
            }
        });

        $('#dayShiftValue').val(state.shift_id);
        $('#dayVehicleValue').val(state.vehicle_id);
        $('#dayDriverValue').val(state.driver_id);
        $('.day-helper-value').remove();
        state.helper_ids.forEach(helperId => $('<input type="hidden" name="helper_ids[]" class="day-helper-value schedule-input">').val(helperId).appendTo('#frmSchedule'));
        $('#dayShiftCurrent').val(dayOptionText('#dayShiftNew', state.shift_id));
        $('#dayVehicleCurrent').val(dayOptionText('#dayVehicleNew', state.vehicle_id));
        rebuildDayPersonnelSelectors(state);
        renderDayChanges();
    }

    function initDayScheduleEditor() {
        const form = $('#frmSchedule.day-schedule-editor');
        if (!form.length) return;
        dayEditBaseState = {
            shift_id: String($('#dayShiftValue').val()),
            vehicle_id: String($('#dayVehicleValue').val()),
            driver_id: String($('#dayDriverValue').val()),
            helper_ids: $('.day-helper-value').map(function () { return String($(this).val()); }).get()
        };
        dayEditChanges = [];
        rebuildDayPersonnelSelectors(dayEditBaseState);
        renderDayChanges();
    }

    $(document).on('change', '#dayPersonnelCurrent', function () {
        if (!dayEditBaseState) return;
        rebuildDayPersonnelSelectors({
            driver_id: String($('#dayDriverValue').val()),
            helper_ids: $('.day-helper-value').map(function () { return String($(this).val()); }).get()
        });
    });

    function invalidateDayScheduleEditor() {
        invalidateAvailability();
        $('.day-change-availability').addClass('d-none').removeClass('is-available is-unavailable').empty();
    }

    $(document).on('change', '.day-reason-preset', function () {
        const description = $(this).find(':selected').data('description');
        const target = $(this).closest('.day-change-card').find('.day-reason-description');
        if (description !== undefined) target.val(description);
    });

    $(document).on('click', '.day-add-change', function () {
        const type = $(this).data('type');
        const card = $(this).closest('.day-change-card');
        const reasonId = card.find('.day-reason-preset').val();
        const reasonType = reasonId ? card.find('.day-reason-preset option:selected').text() : '';
        const description = card.find('.day-reason-description').val().trim();
        if (!reasonType || description.length < 5) {
            Swal.fire('Motivo requerido', 'Seleccione un motivo e indique una descripción de al menos 5 caracteres.', 'info');
            return;
        }

        let change;
        if (type === 'shift' || type === 'vehicle') {
            const select = type === 'shift' ? $('#dayShiftNew') : $('#dayVehicleNew');
            const currentId = type === 'shift' ? String($('#dayShiftValue').val()) : String($('#dayVehicleValue').val());
            const newId = String(select.val() || '');
            if (!newId || newId === currentId) {
                Swal.fire('Seleccione un cambio', 'Elija un valor nuevo distinto al actual.', 'info');
                return;
            }
            const label = type === 'shift' ? 'Cambio de turno' : 'Cambio de vehículo';
            change = {
                key: type,
                type,
                typeLabel: label,
                fromValue: currentId,
                newValue: newId,
                beforeLabel: type === 'shift' ? $('#dayShiftCurrent').val() : $('#dayVehicleCurrent').val(),
                afterLabel: dayOptionText(select, newId),
                reasonType, reasonId,
                description
            };
        } else {
            const currentId = String($('#dayPersonnelCurrent').val() || '');
            const newId = String($('#dayPersonnelNew').val() || '');
            if (!currentId || !newId) {
                Swal.fire('Seleccione el personal', 'Indique el integrante actual y el nuevo personal.', 'info');
                return;
            }
            const role = dayEmployeeRole(currentId);
            if (role !== dayEmployeeRole(newId)) {
                Swal.fire('Tipo de personal diferente', 'Reemplace conductores por conductores y ayudantes por ayudantes.', 'info');
                return;
            }
            change = {
                key: (role === 'driver' ? 'driver' : 'helper:' + currentId),
                type: role,
                typeLabel: role === 'driver' ? 'Cambio de conductor' : 'Cambio de ayudante',
                fromValue: currentId,
                newValue: newId,
                beforeLabel: dayEmployeeLabel(currentId),
                afterLabel: dayEmployeeLabel(newId),
                reasonType, reasonId,
                description
            };
        }

        const existingIndex = dayEditChanges.findIndex(item => item.key === change.key);
        const nextChanges = dayEditChanges.filter(item => item.key !== change.key).concat(change);
        const nextReason = nextChanges.map(item => item.typeLabel + ' (' + item.reasonType + '): ' + item.description).join(' | ');
        if (nextReason.length > 500) {
            Swal.fire('Motivos demasiado largos', 'La suma de los motivos no debe superar los 500 caracteres.', 'info');
            return;
        }
        if (existingIndex >= 0) {
            change.fromValue = dayEditChanges[existingIndex].fromValue;
            change.beforeLabel = dayEditChanges[existingIndex].beforeLabel;
            dayEditChanges.splice(existingIndex, 1);
        }
        dayEditChanges.push(change);
        applyDayEditChanges();
        invalidateDayScheduleEditor();
        $('#btnValidateAvailability').trigger('click');
    });

    $(document).on('click', '.day-remove-change', function () {
        const key = String($(this).attr('data-change-key'));
        dayEditChanges = dayEditChanges.filter(change => String(change.key) !== key);
        applyDayEditChanges();
        invalidateDayScheduleEditor();
        if (dayEditChanges.length) $('#btnValidateAvailability').trigger('click');
    });

    $(formModalElement).on('shown.bs.modal', function () {
        initSelects();
        initDayScheduleEditor();
        queueCrewAvailabilityValidation();
    }).on('hidden.bs.modal', function () {
        destroySearches();
        formBody.empty();
    });

    function invalidateAvailability() {
        $('#availabilityToken').val('');
        $('#btnGuardarSchedule').prop('disabled', true);
        $('#scheduleAvailability').addClass('d-none').removeClass('alert-success alert-warning alert-danger').empty();
        $('#scheduleErrors').addClass('d-none').empty();
    }
    $(document).on('change input', '#frmSchedule .schedule-input', invalidateAvailability);

    let crewValidationTimer = null;
    let crewValidationRequest = null;
    let crewValidationVersion = 0;

    function clearCrewAvailability() {
        $('#frmSchedule .mass-crew').removeClass('is-invalid is-warning');
        $('#frmSchedule .mass-crew-feedback').addClass('d-none').removeClass('alert alert-info alert-warning').empty();
    }

    function selectedCrewIds(select) {
        const value = select.val();
        return (Array.isArray(value) ? value : [value]).filter(Boolean).map(String);
    }

    function renderCrewAvailability(availability) {
        $('.mass-group').each(function () {
            const card = $(this);
            const groupId = String(card.find('.mass-group-id').val() || '');
            const groupIssues = availability[groupId] || {};

            card.find('.mass-crew').each(function () {
                const select = $(this);
                const feedback = select.nextAll('.mass-crew-feedback').first();
                const issues = selectedCrewIds(select).flatMap(employeeId => groupIssues[employeeId] || []);
                if (!issues.length) return;

                const hasError = issues.some(issue => issue.type === 'error');
                const hasWarning = !hasError && issues.some(issue => issue.type === 'warning');
                select.toggleClass('is-invalid', hasError).toggleClass('is-warning', hasWarning);
                feedback.removeClass('d-none').addClass('alert ' + (hasError ? 'alert-info' : 'alert-warning'));

                issues.forEach(issue => {
                    const dates = issue.dates || [];
                    const shownDates = dates.slice(0, 4);
                    const extraDates = dates.length - shownDates.length;
                    let message = issue.reason;
                    if (shownDates.length) message += ' Fechas: ' + shownDates.join(', ');
                    if (extraDates > 0) message += ' y ' + extraDates + ' más';
                    if (Number(issue.date_count || 0) > 0) message += ' (' + issue.date_count + (Number(issue.date_count) === 1 ? ' fecha).' : ' fechas).');

                    const line = $('<div class="d-flex align-items-start">');
                    $('<i aria-hidden="true">').addClass(hasError ? 'bi bi-x-circle-fill mr-1' : 'bi bi-exclamation-circle-fill mr-1').appendTo(line);
                    $('<span>').text(message).appendTo(line);
                    feedback.append(line);
                });
            });
        });
    }

    function queueCrewAvailabilityValidation() {
        const form = $('#frmSchedule');
        if (!form.length || form.find('[name="mode"]').val() !== 'mass') return;

        clearTimeout(crewValidationTimer);
        if (crewValidationRequest) crewValidationRequest.abort();
        const version = ++crewValidationVersion;
        clearCrewAvailability();

        const start = $('#schedule_start_date').val();
        const end = $('#schedule_end_date').val();
        if (!start || !end || start > end || !form.find('input[name="group_ids[]"]:enabled').length) return;

        crewValidationTimer = setTimeout(function () {
            crewValidationRequest = $.ajax({
                url: @json(route('admin.programming.schedules.availability')),
                type: 'POST',
                data: form.serializeArray().filter(function (field) { return field.name !== '_method'; }),
                headers: {Accept:'application/json'}
            }).done(function (result) {
                if (version === crewValidationVersion) renderCrewAvailability(result.crew_availability || {});
            }).fail(function (xhr) {
                if (version === crewValidationVersion && xhr.status === 409) {
                    renderCrewAvailability(xhr.responseJSON?.crew_availability || {});
                }
            }).always(function () {
                if (version === crewValidationVersion) crewValidationRequest = null;
            });
        }, 350);
    }

    $(document).on('change', '#frmSchedule .mass-crew, #schedule_start_date, #schedule_end_date', queueCrewAvailabilityValidation);

    function applyMassShiftFilter() {
        const shift = String($('.mass-shift-filter.active').attr('data-shift') || '');
        $('.mass-group').each(function () {
            const card = $(this);
            const visible = !shift || String(card.data('shift')) === shift;
            card.toggleClass('d-none', !visible);
            card.find('.mass-group-id, .mass-crew').prop('disabled', !visible);
        });
    }
    $(document).on('click', '.mass-shift-filter', function () {
        $('.mass-shift-filter').removeClass('btn-primary active').addClass('btn-outline-primary').attr('aria-pressed', 'false');
        $(this).removeClass('btn-outline-primary').addClass('btn-primary active').attr('aria-pressed', 'true');
        applyMassShiftFilter();
        invalidateAvailability();
        queueCrewAvailabilityValidation();
    });
    $(document).on('click', '.mass-group-remove', function () {
        $(this).closest('.mass-group').remove();
        invalidateAvailability();
        queueCrewAvailabilityValidation();
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
        $.get(@json(route('admin.programming.schedules.holidays')), {start_date: start, end_date: end})
            .done(function (holidays) {
                if (version !== holidayVersion) return;
                container.empty();
                holidays.forEach(holiday => {
                    const label = $('<label class="d-block">');
                    $('<input type="checkbox" name="holiday_ids[]" class="schedule-input" checked>')
                        .val(holiday.id).appendTo(label);
                    label.append(document.createTextNode(' ' + holiday.date.substring(0, 10).split('-').reverse().join('/') + ' · ' + holiday.description));
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
            if ($('#schedule_shift_id').length) $('#schedule_shift_id').val(String(data.shift_id)).trigger('change.select2');
            if ($('#schedule_vehicle_id').length) $('#schedule_vehicle_id').val(String(data.vehicle_id)).trigger('change.select2');
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

    $('#btnScheduleHistoryAll').on('click', function () {
        $('#historyModalLabel').text('Historial general de cambios');
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
        openForm($(this).data('url'), 'Modificar Programación', $(this));
    });

    function appendMessageSection(container, title, items, extraClass = '') {
        if (!items?.length) return;
        const section = $('<div>').addClass('schedule-message-section ' + extraClass);
        $('<strong>').text(title).appendTo(section);
        const list = $('<ul class="mt-2 mb-0">').appendTo(section);
        items.forEach(item => $('<li>').text(item).appendTo(list));
        container.append(section);
    }

    function skippedMessages(items) {
        return (items || []).map(item => {
            const group = item.group_name ? item.group_name + ': ' : '';
            const count = Number(item.date_count || 0);
            return group + item.reason + ' (' + count + (count === 1 ? ' fecha afectada).' : ' fechas afectadas).');
        });
    }

    function appendSkippedSection(container, title, items) {
        if (!items?.length) return;
        const section = $('<div>').addClass('schedule-message-section');
        $('<strong>').text(title).appendTo(section);
        const list = $('<ul class="mt-2 mb-0 schedule-skipped-list">').appendTo(section);
        items.forEach(item => {
            const entry = $('<li>');
            const group = item.group_name ? item.group_name + ': ' : '';
            const count = Number(item.date_count || 0);
            $('<div>').text(group + item.reason + ' (' + count + (count === 1 ? ' fecha afectada).' : ' fechas afectadas).')).appendTo(entry);
            if (item.dates?.length) {
                const details = $('<details class="mt-1">').appendTo(entry);
                $('<summary>').text('Ver fechas afectadas').appendTo(details);
                $('<div class="schedule-skipped-dates small mt-1">').text(item.dates.join(', ')).appendTo(details);
            }
            list.append(entry);
        });
        container.append(section);
    }

    function appendGroupedIssues(container, title, items, icon, className) {
        if (!items?.length) return;
        const section = $('<div class="schedule-validation-section">').appendTo(container);
        $('<strong class="schedule-validation-section-title">').append($('<i aria-hidden="true">').addClass(icon + ' mr-1')).append(document.createTextNode(title)).appendTo(section);
        const list = $('<ul class="mb-0">').appendTo(section);
        items.forEach(item => {
            const entry = $('<li class="schedule-validation-entry">').addClass(className || '').appendTo(list);
            const count = Number(item.date_count || 0);
            const suffix = count ? ' (' + count + (count === 1 ? ' fecha afectada).' : ' fechas afectadas).') : '';
            $('<span>').text((item.reason || item) + suffix).appendTo(entry);
            if (item.dates?.length) {
                const details = $('<details class="small">').appendTo(entry);
                $('<summary>').text('Ver fechas afectadas').appendTo(details);
                $('<div class="schedule-skipped-dates">').text(item.dates.join(', ')).appendTo(details);
            }
        });
    }

    function renderGeneralValidation(container, result) {
        container.removeClass('alert-info alert-success alert-warning alert-danger').addClass('schedule-validation-results').empty();
        const heading = $('<h6 class="schedule-validation-title">').appendTo(container);
        $('<i class="bi bi-exclamation-triangle-fill mr-1" aria-hidden="true">').appendTo(heading);
        heading.append(document.createTextNode('Resultado de Validación General'));
        $('<div class="small text-muted mb-2">').text('Se revisaron ' + result.service_days + ' fechas de servicio: se programarán ' + result.available_service_days + ' y se omitirán ' + result.skipped_service_days + '.').appendTo(container);

        (result.group_results || []).forEach(group => {
            const details = $('<details class="schedule-validation-group" open>').appendTo(container);
            const summary = $('<summary>').appendTo(details);
            $('<i class="bi bi-people-fill" aria-hidden="true">').appendTo(summary);
            $('<span class="schedule-validation-group-title">').text(group.group_name || 'Grupo').appendTo(summary);
            $('<span class="small text-muted">').text('· ' + (group.zone_name || 'Zona no asignada') + ' · ' + (group.shift_name || 'Turno no asignado')).appendTo(summary);

            const badges = $('<span class="schedule-validation-badges">').appendTo(summary);
            if (group.errors?.length) $('<span class="schedule-validation-badge schedule-validation-badge-error">').text('Con errores').appendTo(badges);
            if (group.warnings?.length) $('<span class="schedule-validation-badge schedule-validation-badge-warning">').text('Con advertencias').appendTo(badges);
            $('<i class="bi bi-chevron-down schedule-validation-chevron" aria-hidden="true">').appendTo(summary);

            const hasErrors = Boolean(group.errors?.length);
            const hasWarnings = Boolean(group.warnings?.length);
            const content = $('<div class="schedule-validation-content">').toggleClass('has-errors', hasErrors).toggleClass('has-warnings', hasWarnings).appendTo(details);
            if (Number(group.uncovered_day_count || 0) > 0) {
                const dayLabel = Number(group.uncovered_day_count) === 1 ? 'día no cubierto' : 'días no cubiertos';
                $('<div class="mb-2">').append($('<strong>').text('Días no cubiertos: ')).append(document.createTextNode(group.uncovered_day_count + ' ' + dayLabel + ' (el grupo solo trabaja: ' + (group.work_days || []).join(', ') + ').')).appendTo(content);
            }
            appendGroupedIssues(content, 'Errores:', group.errors, 'bi bi-x-circle-fill text-danger', 'text-danger');
            appendGroupedIssues(content, 'Advertencias:', group.warnings, 'bi bi-exclamation-triangle-fill text-success', 'text-success');
            if (!hasErrors && !hasWarnings && !Number(group.uncovered_day_count || 0)) {
                $('<div class="text-success">').text('Sin inconsistencias en este grupo.').appendTo(content);
            }
        });
    }

    $(document).on('click', '#btnValidateAvailability', function () {
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
            data: form.serializeArray().filter(function (field) { return field.name !== '_method'; }),
            headers: {Accept:'application/json'}
        }).done(function (result) {
            $('#availabilityToken').val(result.token);
            const stagedChanges = form.find('#dayChangesPayload').length ? JSON.parse(form.find('#dayChangesPayload').val() || '[]') : null;
            $('#btnGuardarSchedule').prop('disabled', stagedChanges !== null && stagedChanges.length === 0);
            if (stagedChanges !== null) {
                $('.day-change-availability').removeClass('d-none is-unavailable').addClass('is-available').text('Cambio disponible para esta fecha.');
            }
            renderGeneralValidation(message, result);
        }).fail(function (xhr) {
            const problems = xhr.responseJSON?.conflicts || (xhr.responseJSON?.errors
                ? Object.values(xhr.responseJSON.errors).flat()
                : [xhr.status === 404
                    ? 'No se encontró la programación seleccionada. Actualiza la lista y vuelve a intentarlo.'
                    : xhr.responseJSON?.message || 'No se pudo validar la disponibilidad.']);
            if (xhr.responseJSON?.group_results) {
                if (form.find('#dayChangesPayload').length) {
                    $('.day-change-availability').removeClass('d-none is-available').addClass('is-unavailable').text('No disponible para la fecha seleccionada. Revise los errores del informe.');
                }
                renderGeneralValidation(message, xhr.responseJSON);
            } else {
                message.removeClass('alert-info alert-warning').addClass('alert-danger').empty();
                appendMessageSection(message, 'No se puede generar la programación:', problems);
                appendMessageSection(message, 'Advertencias (no bloquean la programación):', xhr.responseJSON?.warnings || [], 'schedule-message-warning');
            }
            if (form.find('#dayChangesPayload').length) $('#btnGuardarSchedule').prop('disabled', true);
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
            const skipped = result.skipped || [];
            const warnings = result.warnings || [];
            const details = [result.message];
            if (skipped.length) details.push('', 'Fechas omitidas:', ...skippedMessages(skipped).map(item => '• ' + item));
            if (warnings.length) details.push('', 'Advertencias:', ...warnings.map(item => '• ' + item));
            const hasNotices = skipped.length || warnings.length;
            const isUpdate = String(form.find('[name="_method"]').val() || '').toUpperCase() === 'PUT';
            Swal.fire({
                title: skipped.length ? (isUpdate ? 'Programación actualizada parcialmente' : 'Programación generada parcialmente') : (warnings.length ? (isUpdate ? 'Programación actualizada con advertencias' : 'Programación con advertencias') : (isUpdate ? 'Programación actualizada' : 'Éxito')),
                text: details.join('\n'),
                icon: hasNotices ? 'warning' : 'success'
            });
        }).fail(function (xhr) {
            const problems = xhr.responseJSON?.conflicts || (xhr.responseJSON?.errors
                ? Object.values(xhr.responseJSON.errors).flat()
                : [xhr.responseJSON?.message || 'No se pudo guardar la programación.']);
            const warnings = xhr.responseJSON?.warnings || [];
            const skipped = xhr.responseJSON?.skipped || [];
            const message = $('#scheduleAvailability').removeClass('d-none alert-info alert-success alert-warning').addClass('alert-danger').empty();
            appendMessageSection(message, 'No se pudo completar la programación:', problems);
            appendSkippedSection(message, 'Fechas que no se generarán:', skipped);
            if (!skipped.length) appendMessageSection(message, 'Sugerencias:', xhr.responseJSON?.suggestions || []);
            appendMessageSection(message, 'Advertencias (no bloquean la programación):', warnings, 'schedule-message-warning');
            $('#availabilityToken').val('');
        }).always(function () {
            button.prop('disabled', !$('#availabilityToken').val());
        });
    });

    $(document).on('click', '.btnScheduleHistory', function () {
        const button = $(this);
        $('#historyModalLabel').text('Programación · ' + button.data('title'));
        $('#historyModal .modal-body').html('<div class="text-center p-4">Cargando historial…</div>');
        $.get(button.data('url')).done(function (html) {
            $('#historyModal .modal-body').html(html);
            historyModal.show();
        }).fail(function () {
            Swal.fire('Error', 'No se pudo cargar el historial.', 'error');
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



