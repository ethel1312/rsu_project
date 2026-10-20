@extends('adminlte::page')
@section('title', 'Vacaciones')

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

        
    $('#btnNuevo').click(function() {

        $.ajax({

            url: "{{ route('admin.vacations.create') }}",

            type: "GET",

            success: function(response) {

                $('#formModalLabel').html('Nueva solicitud de vacaciones');

                $('#formModal .modal-body').html(response);

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


    
    $(document).on('click', '.btnEditar', function() {

        var id = $(this).data('id');

        $.ajax({

            url: "{{ route('admin.vacations.edit', ':id') }}"
                .replace(':id', id),

            type: "GET",

            success: function(response) {

                $('#formModalLabel').html('Editar solicitud de vacaciones');

                $('#formModal .modal-body').html(response);

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
        var form = $(this);
        form.find('button[type="submit"]').prop('disabled', true);
        $.ajax({
            url: form.attr('action'), type: 'POST', data: form.serialize(),
            success: function(response) {
                bootstrap.Modal.getInstance(document.getElementById('formModal')).hide();
                table.ajax.reload(null, false);
                Swal.fire('Éxito', response.message, 'success');
            },
            error: function(xhr) {
                form.find('button[type="submit"]').prop('disabled', false);
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
});
</script>
@stop
