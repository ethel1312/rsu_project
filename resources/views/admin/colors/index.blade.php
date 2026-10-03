@extends('adminlte::page')

@section('title', 'Proyecto RSU')

@section('content')

    <div class="card mt-3">
        <div class="card-header d-flex align-items-center">

            <div class="w-50">
                <h4 class="mb-0">Colores</h4>
            </div>

            <div class="w-50 d-flex justify-content-end">
                <button type="button" id="btnNuevo" class="btn btn-success">
                    <i class="bi bi-cloud-plus"></i> Nuevo Color
                </button>
            </div>

        </div>

        <div class="card-body">
            <table class="table table-striped" id="DataTable" style="width:100%">
                <thead>
                    <tr>
                        <th width="40" class="text-center">Color</th>
                        <th>Nombre</th>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th>Creación</th>
                        <th>Actualización</th>
                        <th width="40" class="text-center">Edit</th>
                        <th width="40" class="text-center">Elim</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- DataTables cargará los datos mediante AJAX --}}
                </tbody>
            </table>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- MODAL --}}
    {{-- ========================================================= --}}
    <div class="modal fade" id="formModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="formModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="formModalLabel">Nuevo Color</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    {{-- Aquí se cargará el formulario mediante AJAX --}}
                </div>

            </div>
        </div>
    </div>

@stop

@section('js')
<script>
    $(document).ready(function() {
        /* |-------------------------------------------------------------------------- | DATATABLE |-------------------------------------------------------------------------- */
        var table = $('#DataTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('admin.colors.index') }}",
            columns: [
                { data: 'color_box', name: 'color_box', orderable: false, searchable: false, className: 'text-center align-middle' },
                { data: 'name', name: 'name', className: 'align-middle' },
                { data: 'code', name: 'code', className: 'align-middle' },
                { data: 'description', name: 'description', className: 'align-middle' },
                { data: 'created_at', name: 'created_at', className: 'align-middle' },
                { data: 'updated_at', name: 'updated_at', className: 'align-middle' },
                { data: 'edit', name: 'edit', orderable: false, searchable: false, className: 'text-center align-middle' },
                { data: 'delete', name: 'delete', orderable: false, searchable: false, className: 'text-center align-middle' }
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json' }
        });

        /* |-------------------------------------------------------------------------- | NUEVO COLOR |-------------------------------------------------------------------------- */
        $('#btnNuevo').click(function() {
            $.ajax({
                url: "{{ route('admin.colors.create') }}",
                type: "GET",
                success: function(response) {
                    $('#formModalLabel').html('Nuevo Color');
                    $('#formModal .modal-body').html(response);
                    var modal = new bootstrap.Modal(document.getElementById('formModal'));
                    modal.show();
                }
            });
        });

        /* |-------------------------------------------------------------------------- | EDITAR COLOR |-------------------------------------------------------------------------- */
        $(document).on('click', '.btnEditar', function() {
            var id = $(this).data('id');
            $.ajax({
                url: "{{ route('admin.colors.edit', ':id') }}".replace(':id', id),
                type: "GET",
                success: function(response) {
                    $('#formModalLabel').html('Editar Color');
                    $('#formModal .modal-body').html(response);
                    var modal = new bootstrap.Modal(document.getElementById('formModal'));
                    modal.show();
                }
            });
        });

        /* |-------------------------------------------------------------------------- | GUARDAR O ACTUALIZAR |-------------------------------------------------------------------------- */
        $(document).on('submit', '#frmColor', function(e) {
            e.preventDefault();
            var form = $(this);
            form.find('button[type="submit"]').prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Guardando...');

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    $('#formModal').modal('hide');
                    table.ajax.reload(null, false);
                    Swal.fire("Proceso exitoso", response.message, "success");
                },
                error: function(xhr) {
                    form.find('button[type="submit"]').prop('disabled', false).html('<i class="bi bi-floppy-fill"></i> Reintentar');
                    let errorMsg = xhr.responseJSON?.error || "Verifica los datos";
                    if(xhr.status === 422 && xhr.responseJSON.errors) {
                        errorMsg = '';
                        $.each(xhr.responseJSON.errors, (k, v) => errorMsg += v[0] + '<br>');
                    }
                    Swal.fire("Error", errorMsg, "error");
                }
            });
        });

        /* |-------------------------------------------------------------------------- | ELIMINAR COLOR |-------------------------------------------------------------------------- */
        $(document).on('submit', '.frmEliminar', function(e) {
            e.preventDefault();
            var form = $(this);
            var csrfToken = form.find('input[name="_token"]').val(); 

            Swal.fire({
                title: "¿Está seguro de eliminar?",
                text: "¡Esta acción no se puede revertir!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, eliminar!",
                cancelButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: form.attr('action'),
                        type: 'DELETE',
                        data: form.serialize(),
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        success: function(response) {
                            table.ajax.reload(null, false);
                            Swal.fire("¡Eliminado!", response.message, "success");
                        },
                        error: function(xhr) {
                            Swal.fire("Error", "No se pudo eliminar.", "error");
                        }
                    });
                }
            
            });
        });
    /* |-------------------------------------------------------------------------- | SINCRONIZAR COLOR PICKER (AJAX) |-------------------------------------------------------------------------- */
        $(document).on('input', '#colorPicker', function() {
            let color = $(this).val().toUpperCase();
            $('#hexCodeInput').val(color);
            $('#colorPreviewBox').css('background-color', color);
            $('#previewText').text(color);
        });

        $(document).on('input', '#hexCodeInput', function() {
            let color = $(this).val();
            
            // Añade el # automáticamente si el usuario lo olvida
            if(!color.startsWith('#')) {
                color = '#' + color;
            }
            
            // Solo actualiza la caja visual si es un formato hexadecimal válido
            if (/^#[0-9A-F]{6}$/i.test(color)) {
                $('#colorPicker').val(color);
                $('#colorPreviewBox').css('background-color', color);
                $('#previewText').text(color.toUpperCase());
            }
        });
    });
</script>
@stop