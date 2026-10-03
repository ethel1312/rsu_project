@extends('adminlte::page')
@section('title', 'Tipos de Vehículos')

@section('content')
    <div class="card mt-3">
        <div class="card-header d-flex align-items-center">
            <div class="w-50">
                <h4 class="mb-0">Tipos de Vehículos</h4>
            </div>
            <div class="w-50 d-flex justify-content-end">
                <button type="button" id="btnNuevo" class="btn btn-success">
                    <i class="bi bi-cloud-plus"></i> Nuevo Tipo
                </button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-striped" id="DataTable" style="width:100%">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Creación</th>
                        <th>Actualización</th>
                        <th width="40" class="text-center">Edit</th>
                        <th width="40" class="text-center">Elim</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="formModal" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="formModalLabel">Nuevo Tipo de Vehículo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
            ajax: "{{ route('admin.vehicle_types.index') }}",
            columns: [
                { data: 'name', name: 'name', className: 'align-middle font-weight-bold' },
                { data: 'description', name: 'description', className: 'align-middle text-muted' },
                { data: 'created_at', name: 'created_at', className: 'align-middle' },
                { data: 'updated_at', name: 'updated_at', className: 'align-middle' },
                { data: 'edit', name: 'edit', orderable: false, searchable: false, className: 'text-center align-middle' },
                { data: 'delete', name: 'delete', orderable: false, searchable: false, className: 'text-center align-middle' }
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json' }
        });

        $('#btnNuevo').click(function() {
            $.ajax({
                url: "{{ route('admin.vehicle_types.create') }}",
                type: "GET",
                success: function(response) {
                    $('#formModalLabel').html('Nuevo Tipo de Vehículo');
                    $('#formModal .modal-body').html(response);
                    new bootstrap.Modal(document.getElementById('formModal')).show();
                }
            });
        });

        $(document).on('click', '.btnEditar', function() {
            var id = $(this).data('id');
            $.ajax({
                url: "{{ route('admin.vehicle_types.edit', ':id') }}".replace(':id', id),
                type: "GET",
                success: function(response) {
                    $('#formModalLabel').html('Editar Tipo de Vehículo');
                    $('#formModal .modal-body').html(response);
                    new bootstrap.Modal(document.getElementById('formModal')).show();
                }
            });
        });

        $(document).on('submit', '#frmVehicleType', function(e) {
            e.preventDefault();
            var form = $(this);
            form.find('button[type="submit"]').prop('disabled', true);
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    $('#formModal').modal('hide');
                    table.ajax.reload(null, false);
                    Swal.fire("Éxito", response.message, "success");
                },
                error: function(xhr) {
                    form.find('button[type="submit"]').prop('disabled', false);
                    let errorMsg = xhr.responseJSON?.error || "Verifica los datos";
                    if(xhr.status === 422 && xhr.responseJSON.errors) {
                        errorMsg = '';
                        $.each(xhr.responseJSON.errors, (k, v) => errorMsg += v[0] + '<br>');
                    }
                    Swal.fire("Error", errorMsg, "error");
                }
            });
        });

        $(document).on('submit', '.frmEliminar', function(e) {
            e.preventDefault();
            var form = $(this);
            Swal.fire({
                title: "¿Eliminar registro?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, eliminar"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: form.attr('action'),
                        type: 'DELETE',
                        data: form.serialize(),
                        success: function(response) {
                            table.ajax.reload(null, false);
                            Swal.fire("Eliminado", response.message, "success");
                        }
                    });
                }
            });
        });
    });
</script>
@stop