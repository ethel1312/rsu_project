@extends('admin.programming.operations-layout', ['pageTitle' => 'Cambios de programaciones'])
@section('plugins.Select2', true)

@section('content_header')
    <div class="rsu-title-bar flex-wrap gap-2">
        <h1 class="rsu-title"><span class="rsu-title-ico"><i class="bi bi-arrow-left-right"></i></span> Cambios de Programaciones</h1>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.programming.schedules.index') }}" class="btn btn-outline-primary">Ver programaciones</a>
            <button type="button" id="btnNuevoCambio" class="btn rsu-btn-new"><i class="bi bi-arrow-left-right me-1"></i> Nuevo Cambio Masivo</button>
        </div>
    </div>
@endsection

@section('operation_css')
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/dist/css/adminlte-select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        #DataTable td { vertical-align:middle; }
        #DataTable .change-value { min-width:140px; }
        #DataTable .change-value strong { display:block; color:#334155; font-weight:600; }
        #DataTable .change-date { min-width:145px; }
        #DataTable .change-date strong { display:block; white-space:nowrap; }
        #DataTable .change-actor { min-width:150px; }
        .change-action-form { display:inline-block; margin:0; }
        #massChangeModal .modal-dialog { max-width:1160px; }
        #massChangeModal .modal-content { overflow:hidden; border:0; border-radius:12px; box-shadow:0 18px 45px #11182735; }
        #massChangeModal .modal-header { color:#fff; background:#075985; }
        #massChangeModal .modal-title { font-weight:700; }
        #massChangeModal .modal-body { max-height:calc(100vh - 150px); overflow-y:auto; }
        #massChangeModal label { display:block; font-weight:600; margin-bottom:6px; }
        #massChangeModal .select2-container { width:100% !important; }
        #massChangeModal .select2-selection { min-height:38px; font:inherit; }
        #massChangeModal .select2-selection--multiple { align-items:center; gap:.375rem; }
        #massChangeModal .select2-search--inline { flex:1 1 12rem; min-width:0; }
        #massChangeModal .select2-selection__choice { max-width:100%; white-space:normal; overflow-wrap:anywhere; }
        #massChangeModal .mass-change-panel { padding:16px; border:1px solid #dce3eb; border-radius:8px; background:#f8fafc; }
        #massChangeModal .mass-change-panel textarea { min-height:74px; resize:vertical; }
        #massChangeModal .mass-change-complete { background:#eef2f7; }
        #massChangeModal .mass-change-help { display:block; margin-top:4px; color:#738094; font-size:.82rem; }
        #massChangeModal .mass-select2-dropdown { z-index:1065; }
        #massChangeModal .modal-footer-actions { display:flex; justify-content:flex-end; gap:.5rem; }
        .btn-modal-cancelar { background:#dc3545; color:#fff; border-radius:7px; padding:8px 18px; }
        .btn-modal-guardar { background:#0786c4; color:#fff; border-radius:7px; padding:8px 22px; }
        .btn-modal-cancelar:hover,.btn-modal-guardar:hover { color:#fff; filter:brightness(.95); }
    </style>
@endsection

@section('operations')
    <form id="changeFilters" class="card card-body operation-card mb-3">
        <div class="row align-items-end">
            <div class="col-lg-3 col-md-6 mb-2">
                <label for="dateFrom">Fecha de inicio</label>
                <input type="date" id="dateFrom" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-lg-3 col-md-6 mb-2">
                <label for="dateTo">Fecha de fin</label>
                <input type="date" id="dateTo" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-lg-3 col-md-6 mb-2">
                <label for="filterType">Tipo de cambio</label>
                <select id="filterType" name="type" class="form-control">
                    <option value="">Todos los tipos</option>
                    @foreach(\App\Models\Programming\ScheduleChange::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-6 mb-2 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill me-1"></i> Filtrar</button>
                <button type="reset" id="clearFilters" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i> Limpiar</button>
            </div>
        </div>
    </form>

    <div class="card operation-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped operation-table" id="DataTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Tipo de cambio</th>
                            <th>Fecha cambio</th>
                            <th>Antes</th>
                            <th>Después</th>
                            <th>Realizado por</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="massChangeModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="massChangeModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="massChangeModalTitle"><i class="bi bi-arrow-left-right me-2"></i> Cambio Masivo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changeDetailModal" tabindex="-1" aria-labelledby="changeDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content" id="changeDetailContent">
                <div class="modal-body text-center py-5"><span class="spinner-border text-primary" role="status"></span></div>
            </div>
        </div>
    </div>
@endsection

@section('operation_js')
<script>
$(function () {
    const table = $('#DataTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: @json(route('admin.programming.changes.index')),
            data: data => {
                data.date_from = $('#dateFrom').val();
                data.date_to = $('#dateTo').val();
                data.type = $('#filterType').val();
            },
            error: xhr => Swal.fire('No se pudo cargar el listado', xhr.responseJSON?.message || 'Intente nuevamente.', 'error')
        },
        columns: [
            { data: 'change_type', name: 'type', className: 'align-middle' },
            { data: 'change_date', name: 'created_at', className: 'align-middle' },
            { data: 'before_display', name: 'before_display', orderable: false, searchable: false, className: 'align-middle' },
            { data: 'after_display', name: 'after_display', orderable: false, searchable: false, className: 'align-middle' },
            { data: 'actor_display', name: 'actor_display', orderable: false, searchable: false, className: 'align-middle' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center align-middle' }
        ],
        order: [[1, 'desc']],
        pageLength: 10,
        language: { url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json' }
    });

    const modalElement = document.getElementById('massChangeModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const modalBody = $('#massChangeModal .modal-body');
    const detailModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('changeDetailModal'));
    const detailContent = $('#changeDetailContent');
    let previewToken = null;

    function errorText(xhr) {
        if (xhr.responseJSON?.errors) return Object.values(xhr.responseJSON.errors).flat().join(' ');
        return xhr.responseJSON?.message || 'No se pudo completar la operación.';
    }

    function showMassError(message) {
        const box = $('#massChangeError');
        box.text(message).removeClass('d-none');
        box[0]?.scrollIntoView({behavior: 'smooth', block: 'center'});
    }

    function invalidatePreview() {
        previewToken = null;
        $('#massPreview').addClass('d-none');
    }

    function initializeMassForm() {
        const form = document.getElementById('massChangeForm');
        if (!form) return;
        const $zoneSelect = $('#massZoneIds');
        const $oldSelect = $('#massOldId');
        const $newSelect = $('#massNewId');
        const startDate = document.getElementById('massStartDate');
        const endDate = document.getElementById('massEndDate');
        const allResources = Array.from($oldSelect[0].options).slice(1).map(option => option.cloneNode(true));

        startDate.addEventListener('change', function () {
            endDate.min = this.value || endDate.min;
            if (endDate.value && this.value && endDate.value < this.value) endDate.value = '';
            invalidatePreview();
        });

        if ($.fn.select2) {
            $zoneSelect.select2({width:'100%', placeholder:'Dejar vacío para aplicar en todas las zonas', allowClear:true, closeOnSelect:false, dropdownParent:$('#massChangeModal')});
            $('.mass-resource-select').select2({width:'100%', placeholder:'Seleccione un recurso', dropdownParent:$('#massChangeModal')});
        }

        function fillResources(target, kind, excluded = '') {
            const select = target[0];
            select.replaceChildren(new Option('Seleccione un recurso', ''));
            allResources.filter(option => option.dataset.kind === kind && String(option.value) !== String(excluded))
                .forEach(option => select.add(option.cloneNode(true)));
            target.val('').trigger('change.select2');
        }

        function updateResourceLists(resetOld = false) {
            const kind = $('#massChangeType').val();
            if (resetOld) $oldSelect.val('').trigger('change.select2');
            fillResources($oldSelect, kind);
            fillResources($newSelect, kind, $oldSelect.val());
        }

        $('#massChangeType').on('change', function () { updateResourceLists(true); invalidatePreview(); });
        $oldSelect.on('change', function () { fillResources($newSelect, $('#massChangeType').val(), this.value); invalidatePreview(); });
        $newSelect.on('change', invalidatePreview);

        const reasonSelect = document.getElementById('massReasonId');
        const extraDetail = document.getElementById('massAdditionalDetail');
        const completeDetail = document.getElementById('massCompleteDetail');
        function updateCompleteDetail() {
            const reason = reasonSelect.selectedOptions[0];
            const reasonName = reason?.dataset.name || '';
            const extra = extraDetail.value.trim();
            completeDetail.value = [reasonName, extra].filter(Boolean).join(' - ');
            completeDetail.dispatchEvent(new Event('input', {bubbles:true}));
        }
        reasonSelect.addEventListener('change', updateCompleteDetail);
        extraDetail.addEventListener('input', updateCompleteDetail);

        form.addEventListener('input', invalidatePreview);
        form.addEventListener('change', invalidatePreview);
        $('#massCancelPreview').on('click', function () { invalidatePreview(); });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const button = document.getElementById('massPreviewButton');
            button.disabled = true;
            invalidatePreview();
            $('#massChangeError').addClass('d-none');
            try {
                const response = await fetch(@json(route('admin.programming.changes.preview')), {
                    method:'POST', body:new FormData(form), headers:{Accept:'application/json'}
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.errors ? Object.values(result.errors).flat().join(' ') : result.message || 'No se pudo revisar el cambio.');
                previewToken = result.token;
                const rows = document.getElementById('massPreviewRows');
                rows.replaceChildren();
                const key = {shift:'turno', vehicle:'vehiculo', driver:'conductor', helper:'ayudantes'}[document.getElementById('massChangeType').value];
                result.rows.forEach(row => {
                    const tr = document.createElement('tr');
                    [`#${row.schedule_id}`, row.date, row.zone, row.before[key], row.after[key]].forEach(value => {
                        const td = document.createElement('td'); td.textContent = value ?? '—'; tr.appendChild(td);
                    });
                    rows.appendChild(tr);
                });
                const reason = reasonSelect.selectedOptions[0]?.textContent.trim() || '';
                document.getElementById('massPreviewSummary').textContent = `${result.count} programaciones recibirán el cambio. Motivo: ${reason}`;
                $('#massPreview').removeClass('d-none');
                document.getElementById('massPreview').scrollIntoView({behavior:'smooth', block:'nearest'});
            } catch (error) {
                showMassError(error.message);
            } finally { button.disabled = false; }
        });

        document.getElementById('massConfirmButton').addEventListener('click', async function () {
            if (!previewToken) return;
            const button = this;
            button.disabled = true;
            const data = new FormData(form);
            data.append('confirmed', '1');
            data.append('preview_token', previewToken);
            try {
                const response = await fetch(@json(route('admin.programming.changes.store')), {
                    method:'POST', body:data, headers:{Accept:'application/json'}
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.errors ? Object.values(result.errors).flat().join(' ') : result.message || 'No se pudo aplicar el cambio.');
                modal.hide();
                table.ajax.reload(null, false);
                Swal.fire('Cambio aplicado', result.message, 'success');
            } catch (error) {
                invalidatePreview();
                showMassError(error.message);
            } finally { button.disabled = false; }
        });

        updateResourceLists(true);
        updateCompleteDetail();
    }

    $('#changeFilters').on('submit', function (event) {
        event.preventDefault();
        const from = $('#dateFrom').val(), to = $('#dateTo').val();
        if (from && to && to < from) {
            Swal.fire('Revise las fechas', 'La fecha de fin debe ser igual o posterior a la fecha de inicio.', 'warning');
            return;
        }
        table.ajax.reload();
    }).on('reset', function () { setTimeout(() => table.ajax.reload(), 0); });

    $('#btnNuevoCambio').on('click', function () {
        const button = $(this);
        button.prop('disabled', true);
        modalBody.html('<div class="text-center py-5"><span class="spinner-border text-primary" role="status"></span></div>');
        modal.show();
        $.get(@json(route('admin.programming.changes.create')))
            .done(function (html) { modalBody.html(html); previewToken = null; initializeMassForm(); })
            .fail(xhr => showMassError(errorText(xhr)))
            .always(() => button.prop('disabled', false));
    });

    @if(request()->boolean('new_mass') && auth()->user()->can('changes.create'))
        $('#btnNuevoCambio').trigger('click');
    @endif

    $(document).on('click', '.btnVerDetalleCambio', function () {
        const button = $(this);
        const url = @json(route('admin.programming.changes.show', ':id')).replace(':id', button.data('id'));
        detailContent.html('<div class="modal-body text-center py-5"><span class="spinner-border text-primary" role="status"></span></div>');
        detailModal.show();
        $.ajax({url, type:'GET', headers:{'X-Requested-With':'XMLHttpRequest', Accept:'text/html'}})
            .done(html => detailContent.html(html))
            .fail(xhr => {
                detailContent.html('<div class="modal-header"><h5 class="modal-title">Detalle del cambio</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"><div class="alert alert-danger mb-0"></div></div>');
                detailContent.find('.alert').text(xhr.responseJSON?.message || 'No se pudo cargar el detalle del cambio.');
            });
    });

    $(document).on('submit', '.frmRevertirCambio', function (event) {
        event.preventDefault();
        const form = $(this), button = form.find('[type="submit"]');
        if (button.prop('disabled')) return;
        button.prop('disabled', true);
        Swal.fire({title:'¿Eliminar y revertir este cambio?', text:'Se restaurará el valor anterior y la reversión quedará en el historial.', icon:'warning', showCancelButton:true, confirmButtonText:'Sí, revertir', cancelButtonText:'Cancelar'})
            .then(result => {
                if (!result.isConfirmed) { button.prop('disabled', false); return; }
                $.ajax({url:form.attr('action'), type:'POST', data:form.serialize(), headers:{Accept:'application/json'}})
                    .done(response => { table.ajax.reload(null, false); Swal.fire('Cambio revertido', response.message, 'success'); })
                    .fail(xhr => Swal.fire('No se pudo revertir', errorText(xhr), 'error'))
                    .always(() => button.prop('disabled', false));
            });
    });
});
</script>
@endsection
