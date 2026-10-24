@extends('adminlte::page')
@section('title', 'Vacaciones')
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
        #formModal .vacation-summary {
            margin-top: 1.25rem;
            padding: 1rem;
            border: 1px solid rgba(91, 184, 245, .25);
            border-radius: .5rem;
            background: #eaf5ff;
            color: var(--rsu-navy);
        }
        #formModal .vacation-summary-title {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: .75rem;
            font-size: 1rem;
        }
        #formModal .vacation-summary-title i,
        #formModal .vacation-summary-values strong {
            color: #0878e8;
        }
        #formModal .vacation-summary-values {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            text-align: center;
        }
        #formModal .vacation-summary-values > div {
            display: flex;
            flex-direction: column;
            gap: .25rem;
            padding: .25rem .5rem;
        }
        #formModal .vacation-summary-values > div + div {
            border-left: 1px solid rgba(91, 184, 245, .35);
        }
        #formModal .vacation-summary-values span {
            color: #526b93;
            font-size: .8rem;
            font-weight: 600;
        }
        #formModal .vacation-summary-values strong {
            font-size: 1.25rem;
        }
        #formModal .vacation-summary-note {
            margin-top: .75rem;
            padding-top: .65rem;
            border-top: 1px solid rgba(91, 184, 245, .25);
            color: #526b93;
            font-size: .8rem;
        }
        #formModal .vacation-summary-note i {
            margin-right: .25rem;
            color: #0878e8;
        }
        @media (max-width: 575.98px) {
            #formModal .vacation-summary-values {
                grid-template-columns: 1fr;
            }
            #formModal .vacation-summary-values > div + div {
                border-top: 1px solid rgba(91, 184, 245, .35);
                border-left: 0;
            }
        }
    </style>
@stop

@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title">
            <span class="rsu-title-ico">
                <i class="bi bi-airplane"></i>
            </span>
            Lista de Vacaciones
        </h1>

        <button type="button" id="btnNuevo" class="btn rsu-btn-new">
            <i class="bi bi-plus-circle"></i> Nueva solicitud
        </button>
    </div>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <table class="table table-striped" id="DataTable" style="width:100%">
                
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Empleado</th>
                        <th>Fecha de solicitud</th>
                        <th>Fecha de inicio</th>
                        <th>Fecha de término</th>
                        <th>Días S.</th>
                        <th>Estado</th>
                        <th>Días R.</th>
                        <th>Notas</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>

            </table>
        </div>
    </div>

    {{-- Modal de vacaciones --}}
    <div class="modal fade" id="formModal" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formModalLabel">
                        Nueva solicitud de vacaciones
                    </h5>

                    <button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
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
        ajax: "{{ route('admin.vacations.index') }}",
        
        columns: [
            { data: 'dni', name: 'dni' },
            { data: 'employee', name: 'employee' },
            { data: 'created_at', name: 'created_at' },
            { data: 'start_date', name: 'start_date' },
            { data: 'end_date', name: 'end_date' },
            { data: 'days_requested', name: 'days_requested' },
            { data: 'status', name: 'status', orderable: false },
            { data: 'days_remaining', name: 'days_remaining', orderable: false, searchable: false },
            { data: 'notes', name: 'notes', defaultContent: '' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
        ],

        language: { url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json' }
    });

    let vacationBalance = null;
    let vacationBalanceRequest = null;
    let vacationBalanceKey = null;
    let vacationBalanceLoading = false;
    let vacationFormSubmitting = false;
    let vacationBalanceError = '';
    let serverDaysError = '';

    function formatVacationDays(days) {
        return days + (days === 1 ? ' día' : ' días');
    }

    function updateVacationSummary() {
        const $summary = $('#formModal #vacationSummary');

        if (!vacationBalance) {
            $summary.hide();
            return;
        }

        const requested = Number($('#formModal #days_requested').val()) || 0;
        const available = vacationBalance.days_available;

        $('#vacationDaysAvailable').text(formatVacationDays(available));
        $('#vacationDaysRequested').text(formatVacationDays(requested));
        $('#vacationDaysAfterApproval').text(formatVacationDays(available - requested));
        $summary.show();
    }

    function validateVacationDays() {
        const $input = $('#formModal #days_requested');
        const $error = $('#formModal #days_requested_error');
        const requested = Number($input.val());
        let message = serverDaysError || vacationBalanceError;

        if (!message && vacationBalance && Number.isInteger(requested)
            && requested > vacationBalance.days_available) {
            message = 'Solo tienes ' + vacationBalance.days_available
                + (vacationBalance.days_available === 1
                    ? ' día de vacaciones disponible.'
                    : ' días de vacaciones disponibles.');
        }

        $input.toggleClass('is-invalid', Boolean(message));
        $error.text(message).toggle(Boolean(message));
        $('#formModal #frmVacation button[type="submit"]')
            .prop('disabled', Boolean(message) || vacationBalanceLoading || vacationFormSubmitting);
        updateVacationSummary();

        return !message && !vacationBalanceLoading && !vacationFormSubmitting;
    }

    function actualizarSaldoVacacional() {
        const employeeId = $('#formModal #employee_id').val();
        const startDate = $('#formModal #start_date').val();
        const year = startDate ? startDate.slice(0, 4) : '{{ now()->year }}';
        const requestKey = employeeId + ':' + year;

        if (requestKey === vacationBalanceKey
            && (vacationBalance || vacationBalanceLoading)) {
            validateVacationDays();
            return;
        }

        if (vacationBalanceRequest) {
            vacationBalanceRequest.abort();
            vacationBalanceRequest = null;
        }

        vacationBalance = null;

        if (!employeeId) {
            vacationBalanceKey = null;
            vacationBalanceLoading = false;
            vacationBalanceError = '';
            validateVacationDays();
            return;
        }

        vacationBalanceKey = requestKey;
        vacationBalanceLoading = true;
        vacationBalanceError = '';
        validateVacationDays();

        vacationBalanceRequest = $.ajax({
            url: "{{ route('admin.vacations.balance', ':employeeId') }}".replace(':employeeId', employeeId),
            type: 'GET',
            data: { year: year },
            success: function (balance) {
                vacationBalance = balance;
                vacationBalanceLoading = false;
                vacationBalanceRequest = null;
                if (serverDaysError.indexOf('Solo tienes ') === 0) {
                    serverDaysError = '';
                }
                validateVacationDays();
            },
            error: function (xhr, status) {
                if (status === 'abort') return;

                vacationBalanceLoading = false;
                vacationBalanceRequest = null;
                vacationBalanceKey = null;
                vacationBalanceError = 'No se pudo consultar el saldo de vacaciones. Intente nuevamente.';
                validateVacationDays();
                $('#formModal #vacationSummary').hide();
                console.error('No se pudo consultar el saldo de vacaciones:', xhr.responseText);
            }
        });
    }

    function inicializarBuscadorPersonal() {
        $('#formModal #employee_id').select2({
            dropdownParent: $('#formModal'),
            width: '100%',
            placeholder: 'Buscar por DNI, nombres o apellidos',
            allowClear: true,
            minimumInputLength: 2,
            maximumInputLength: 100,
            language: {
                inputTooShort: function () {
                    return 'Escriba al menos 2 caracteres para buscar.';
                },
                inputTooLong: function () {
                    return 'La búsqueda no debe superar los 100 caracteres.';
                },
                searching: function () {
                    return 'Buscando personal...';
                },
                loadingMore: function () {
                    return 'Cargando más resultados...';
                },
                noResults: function () {
                    return 'No se encontró personal con esos datos.';
                },
                errorLoading: function () {
                    return 'No se pudo buscar personal. Intente nuevamente o recargue la página.';
                }
            },
            ajax: {
                url: "{{ route('admin.vacations.employees') }}",
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        q: (params.term || '').trim(),
                        page: params.page || 1
                    };
                },
                processResults: function (data) {
                    return data;
                }
            }
        }).on('select2:open', function () {
            $('#formModal .select2-search__field')
                .attr('placeholder', 'DNI, nombres o apellidos')
                .trigger('focus');
        });
    }

    function destruirBuscadorPersonal() {
        const $select = $('#formModal #employee_id');

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
    }

    $('#formModal').on('hidden.bs.modal', function () {
        destruirBuscadorPersonal();
        if (vacationBalanceRequest) {
            vacationBalanceRequest.abort();
            vacationBalanceRequest = null;
        }
        vacationBalance = null;
        vacationBalanceKey = null;
        vacationBalanceLoading = false;
        vacationFormSubmitting = false;
        vacationBalanceError = '';
        serverDaysError = '';
        $('#formModal .modal-body').empty();
    });

    function calcularDiasSolicitados() {
        const $startDate = $('#formModal #start_date');
        const $endDate = $('#formModal #end_date');
        const startDate = $startDate.val();
        const $daysRequested = $('#formModal #days_requested');
        const daysRequested = Number($daysRequested.val());

        if (!startDate || !Number.isInteger(daysRequested) || daysRequested < 1) {
            $endDate.val('');
            return;
        }

        const [startYear, startMonth, startDay] = startDate.split('-').map(Number);
        const end = new Date(Date.UTC(startYear, startMonth - 1, startDay));
        end.setUTCDate(end.getUTCDate() + daysRequested - 1);
        $endDate.val(end.toISOString().slice(0, 10));
    }

    $(document).on('input change', '#formModal #start_date, #formModal #days_requested', function () {
        serverDaysError = '';
        calcularDiasSolicitados();
        if (this.id === 'start_date') {
            actualizarSaldoVacacional();
        } else {
            validateVacationDays();
        }
    });

    $(document).on('change', '#formModal #employee_id', function () {
        serverDaysError = '';
        actualizarSaldoVacacional();
    });

    $('#btnNuevo').click(function() {

        $.ajax({

            url: "{{ route('admin.vacations.create') }}",

            type: "GET",

            success: function (response) {
                destruirBuscadorPersonal();

                $('#formModalLabel').html('Nueva solicitud de vacaciones');
                $('#formModal .modal-body').html(response);

                inicializarBuscadorPersonal();
                actualizarSaldoVacacional();
                calcularDiasSolicitados();

                bootstrap.Modal
                    .getOrCreateInstance(document.getElementById('formModal'))
                    .show();
            },

            error: function(xhr) {

                console.error(
                    'No se pudo cargar el formulario:',
                    xhr.responseText
                );

                Swal.fire(
                    'Error',
                    'No se pudo cargar el formulario de vacaciones.',
                    'error'
                );
            }

        });

    });


    
    $(document).on('click', '.btnEditar', function() {

        var id = $(this).data('id');

        $.ajax({

            url: "{{ route('admin.vacations.edit', ':id') }}"
                .replace(':id', id),

            type: "GET",

            success: function(response) {

                destruirBuscadorPersonal();
                $('#formModalLabel').html('Editar solicitud de vacaciones');

                $('#formModal .modal-body').html(response);
                calcularDiasSolicitados();
                inicializarBuscadorPersonal();
                actualizarSaldoVacacional();

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
                    'No se pudo cargar el formulario de vacaciones.',
                    'error'
                );
            }

        });

    });


    $(document).on('submit', '#frmVacation', function(e) {
        e.preventDefault();
        if (!validateVacationDays()) return;
        var form = $(this);
        vacationFormSubmitting = true;
        validateVacationDays();
        $.ajax({
            url: form.attr('action'), type: 'POST', data: form.serialize(),
            success: function(response) {
                bootstrap.Modal.getInstance(document.getElementById('formModal')).hide();
                table.ajax.reload(null, false);
                Swal.fire('Éxito', response.message, 'success');
            },
            error: function(xhr) {
                vacationFormSubmitting = false;
                const daysErrors = xhr.responseJSON?.errors?.days_requested;
                if (xhr.status === 422 && daysErrors) {
                    serverDaysError = daysErrors[0];
                    validateVacationDays();
                    if (vacationBalanceKey) {
                        vacationBalanceKey = null;
                        actualizarSaldoVacacional();
                    }
                    return;
                }
                validateVacationDays();
                var errorMsg = xhr.responseJSON?.error || 'Verifica los datos';
                if (xhr.status === 422 && xhr.responseJSON.errors) {
                    errorMsg = Object.values(xhr.responseJSON.errors).map(function(errors) { return errors[0]; }).join('<br>');
                }
                Swal.fire('Error', errorMsg, 'error');
            }
        });
    });

    $(document).on('submit', '.frmEliminar', function(e) {
        e.preventDefault();
        var form = $(this);
        Swal.fire({ title: '¿Eliminar solicitud?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' }).then(function(result) {
            if (!result.isConfirmed) return;
            $.ajax({ url: form.attr('action'), type: 'DELETE', data: form.serialize(), success: function(response) {
                table.ajax.reload(null, false);
                Swal.fire('Eliminada', response.message, 'success');
            }});
        });
    });

    $(document).on('click', '.btnAprobar, .btnRechazar', function () {
        const id = $(this).data('id');
        const aprobar = $(this).hasClass('btnAprobar');

        const url = aprobar
            ? "{{ route('admin.vacations.approve', ':id') }}".replace(':id', id)
            : "{{ route('admin.vacations.reject', ':id') }}".replace(':id', id);

        Swal.fire({
            title: aprobar ? '¿Aprobar solicitud?' : '¿Rechazar solicitud?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: url,
                type: 'PATCH',
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function (response) {
                    table.ajax.reload(null, false);
                    Swal.fire('Éxito', response.message, 'success');
                },
                error: function (xhr) {
                    const mensaje = xhr.responseJSON?.message
                        || xhr.responseJSON?.error
                        || 'No se pudo procesar la solicitud.';

                    Swal.fire('Error', mensaje, 'error');
                }
            });
        });
    });
});
</script>
@stop
