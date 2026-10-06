@extends('adminlte::page')

@section('title', 'Asistencias')

@section('plugins.Select2', true)

@section('css')
    <style>
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

@section('content_header')
    <div class="rsu-title-bar flex-wrap">
        <h1 class="rsu-title"><span class="rsu-title-ico"><i class="bi bi-calendar-check"></i></span> Lista de Asistencias</h1>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('employees.attendances.clock') }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-clock"></i> Marcación del Personal</a>
            <button type="button" id="btnNuevo" class="btn rsu-btn-new"><i class="bi bi-plus-circle"></i> Nueva Asistencia</button>
        </div>
    </div>
@stop

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="row align-items-end g-2">
                <div class="col-12 col-lg-3">
                    <label for="startDate" class="form-label">Fecha de inicio</label>
                    <input type="date" id="startDate" class="form-control" value="{{ $startDate }}" required>
                </div>
                <div class="col-12 col-lg-3">
                    <label for="endDate" class="form-label">Fecha de fin</label>
                    <input type="date" id="endDate" class="form-control" value="{{ $endDate }}" required>
                </div>
                <div class="col-12 col-lg-3">
                    <label for="filterEmployee" class="form-label">Buscar empleado</label>
                    <input type="search" id="filterEmployee" class="form-control" maxlength="100" placeholder="DNI, nombre o apellido...">
                </div>
                <div class="col-12 col-lg-3 d-flex gap-2">
                    <button type="button" class="btn btn-primary" id="filter"><i class="bi bi-funnel-fill"></i> Filtrar</button>
                    <button type="button" class="btn btn-outline-secondary" id="clearFilters"><i class="bi bi-eraser"></i> Limpiar</button>
                </div>
            </div>
            <div class="mt-2 mb-4">
                <button type="button" class="btn btn-outline-primary" id="today"><i class="bi bi-calendar-event"></i> Hoy</button>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="DataTable" style="width:100%">
                    <thead><tr>
                        <th>DNI</th><th>Nombres</th><th>Apellidos</th><th>Fecha</th><th>Hora</th>
                        <th>Tipo</th><th>Estado</th><th>Notas</th>
                        <th width="40" class="text-center">Edit</th><th width="40" class="text-center">Elim</th>
                    </tr></thead>
                </table>
            </div>
        </div>
    </div>
    <div class="modal fade" id="formModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="formModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="formModalLabel">Nueva Asistencia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"></div>
        </div></div>
    </div>
@stop

@section('js')
<script>
$(document).ready(function () {
    function currentDate() {
        const parts = new Intl.DateTimeFormat('en-US', {timeZone: 'America/Lima', year: 'numeric', month: '2-digit', day: '2-digit'}).formatToParts(new Date());
        const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
        return values.year + '-' + values.month + '-' + values.day;
    }

    function showError(xhr, fallback) {
        let message = xhr.responseJSON?.error || fallback;
        if (xhr.status === 422 && xhr.responseJSON?.errors) {
            message = Object.values(xhr.responseJSON.errors).map(errors => errors[0]).join('\n');
        } else if (xhr.status === 401 || xhr.status === 419) {
            message = 'La sesión ha expirado. Recargue la página e inicie sesión nuevamente.';
        }
        Swal.fire({title: xhr.status === 422 ? 'Error de Validación' : 'Error', text: message, icon: 'error'});
    }

    var table = $('#DataTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: @json(route('admin.attendances.index')),
            data: function (data) {
                data.start_date = $('#startDate').val();
                data.end_date = $('#endDate').val();
                data.employee = $('#filterEmployee').val().trim();
            },
            error: function (xhr) { showError(xhr, 'No se pudo cargar el listado de asistencias.'); }
        },
        order: [[4, 'asc']],
        columns: [
            {data: 'dni', name: 'employees.dni'},
            {data: 'first_name', name: 'employees.first_name'},
            {data: 'last_name', name: 'employees.last_name'},
            {data: 'date', name: 'attendances.date', searchable: false},
            {data: 'time', name: 'attendances.time', searchable: false},
            {data: 'type', name: 'attendances.type', searchable: false},
            {data: 'status', name: 'attendances.status', searchable: false},
            {data: 'notes', name: 'attendances.notes', defaultContent: '', orderable: false},
            {data: 'edit', orderable: false, searchable: false, className: 'text-center align-middle'},
            {data: 'delete', orderable: false, searchable: false, className: 'text-center align-middle'}
        ],
        language: {url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'}
    });

    function reloadFilteredTable() {
        const startDate = $('#startDate').val();
        const endDate = $('#endDate').val();
        if (!startDate || !endDate) {
            Swal.fire('Error de Validación', 'Debe ingresar las fechas de inicio y fin.', 'error');
            return;
        }
        if (startDate > endDate) {
            Swal.fire('Error de Validación', 'La fecha de inicio no puede ser mayor que la fecha de fin.', 'error');
            return;
        }
        table.ajax.reload();
    }
    $('#filter').on('click', reloadFilteredTable);
    $('#filterEmployee').on('keydown', function (event) {
        if (event.key === 'Enter') reloadFilteredTable();
    });
    $('#today, #clearFilters').on('click', function () {
        const today = currentDate();
        $('#startDate, #endDate').val(today);
        if (this.id === 'clearFilters') $('#filterEmployee').val('');
        table.ajax.reload();
    });

    let previewRequest;
    function previewType() {
        if (previewRequest) previewRequest.abort();
        const form = $('#frmAttendance');
        if (!form.find('[name="employee_id"]').val() || !form.find('[name="date"]').val() || !form.find('[name="time"]').val()) {
            $('#attendance_type').val('Seleccione personal, fecha y hora');
            return;
        }
        $('#attendance_type').val('Calculando...');
        previewRequest = $.ajax({
            url: @json(route('admin.attendances.preview')),
            data: {
                employee_id: form.find('[name="employee_id"]').val(),
                date: form.find('[name="date"]').val(), time: form.find('[name="time"]').val(),
                status: form.find('[name="status"]').val(), attendance_id: form.find('[name="attendance_id"]').val()
            },
            success: function (response) { $('#attendance_type').val(response.type); },
            error: function (xhr, status) {
                if (status !== 'abort') $('#attendance_type').val('Se calculará al guardar');
            }
        });
    }
    $(document).on('change', '#frmAttendance select, #frmAttendance input[type="date"], #frmAttendance input[type="time"]', previewType);
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
                url: @json(route('admin.attendances.employees')),
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
        if (previewRequest) previewRequest.abort();
        destroyEmployeeSearch();
        $('#formModal .modal-body').empty();
    });
    function openForm(url, title, data) {
        $.ajax({url: url, data: data, success: function (response) {
            destroyEmployeeSearch();
            $('#formModalLabel').text(title);
            $('#formModal .modal-body').html(response);
            initializeEmployeeSearch();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
            previewType();
        }, error: function (xhr) { showError(xhr, 'No se pudo cargar el formulario de asistencia.'); }});
    }
    $('#btnNuevo').on('click', function () {
        openForm(@json(route('admin.attendances.create')), 'Nueva Asistencia', {date: $('#endDate').val()});
    });
    $(document).on('click', '.btnEditar', function () {
        openForm(@json(route('admin.attendances.edit', ':id')).replace(':id', $(this).data('id')), 'Editar Asistencia');
    });
    $(document).on('submit', '#frmAttendance', function (event) {
        event.preventDefault();
        const form = $(this);
        if (form.data('saving')) return;
        if (!form.find('[name="employee_id"]').val()) {
            Swal.fire('Error de Validación', 'El personal es obligatorio.', 'error');
            return;
        }
        form.data('saving', true).find('button[type="submit"]').prop('disabled', true);
        $.ajax({url: form.attr('action'), type: 'POST', data: form.serialize(),
            success: function (response) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).hide();
                table.ajax.reload(null, false);
                Swal.fire('Éxito', response.message, 'success');
            },
            error: function (xhr) { showError(xhr, 'No se pudo guardar la asistencia.'); },
            complete: function () { form.data('saving', false).find('button[type="submit"]').prop('disabled', false); }
        });
    });
    $(document).on('submit', '.frmEliminar', function (event) {
        event.preventDefault();
        const form = $(this);
        if (form.data('saving')) return;
        Swal.fire({title: '¿Eliminar asistencia?', text: 'Esta acción no se puede deshacer. Se recalcularán los ingresos y salidas del día.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#3085d6', cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (!result.isConfirmed || form.data('saving')) return;
            form.data('saving', true).find('button').prop('disabled', true);
            $.ajax({url: form.attr('action'), type: 'POST', data: form.serialize(),
                success: function (response) { table.ajax.reload(null, false); Swal.fire('¡Eliminado!', response.message, 'success'); },
                error: function (xhr) { showError(xhr, 'No se pudo eliminar la asistencia.'); },
                complete: function () { form.data('saving', false).find('button').prop('disabled', false); }
            });
        });
    });
});
</script>
@stop
