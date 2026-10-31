@extends('admin.programming.operations-layout', ['pageTitle' => 'Motivos de cambio'])

@section('content_header')
    <div class="rsu-title-bar flex-wrap gap-2">
        <h1 class="rsu-title"><span class="rsu-title-ico"><i class="bi bi-card-list"></i></span> Lista de Motivos</h1>
        <button type="button" id="btnNuevoMotivo" class="btn rsu-btn-new"><i class="bi bi-plus-circle"></i> Nuevo Motivo</button>
    </div>
@endsection

@section('operation_css')
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        #formModal .modal-dialog { max-width: 640px; }
        #formModal .modal-content { overflow:hidden; border:0; border-radius:18px; box-shadow:0 18px 45px #11182735; }
        #formModal .modal-header { align-items:center; padding:20px 24px; color:#1c286b; background:#fff; border-bottom:1px solid #e1e7f0; }
        #formModal .modal-title { color:#1c286b; font-size:1.45rem; font-weight:700; }
        #formModal .btn-close { flex:0 0 auto; margin-left:auto; padding:.7rem; }
        #formModal .btn-close:focus-visible { outline:4px solid #bfdbfe; box-shadow:0 0 0 2px #fff; }
        #formModal .modal-body { padding:22px 20px 20px; }
        #formModal .modal-body label { font-weight:600; }
        #formModal textarea { min-height:105px; resize:vertical; }
        #formModal .reason-modal-cancel { color:#69778f; background:#edf2ff; border:0; border-radius:9px; padding:10px 20px; font-weight:600; }
        #formModal .reason-modal-cancel:hover { background:#e1e9fb; }
        #formModal .reason-modal-save { color:#fff; background:#5ab8ef; border:0; border-radius:9px; padding:10px 22px; font-weight:700; }
        #formModal .reason-modal-save:hover { color:#fff; background:#39a8e6; }
        .reason-action-form { display:inline-block; margin:0; }
    </style>
@endsection

@section('operations')
    <div class="card operation-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped operation-table" id="DataTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Fecha de creación</th>
                            <th>Fecha de actualización</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="formModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="formModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formModalLabel">Nuevo Motivo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body"></div>
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
                ajax: @json(route('admin.programming.reasons.index')),
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'description', name: 'description' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'updated_at', name: 'updated_at' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false }
                ],
                order: [[0, 'asc']],
                language: { url: 'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json' }
            });
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal'));

            function openForm(url, title, button) {
                button.prop('disabled', true);
                $.get(url).done(function (html) {
                    $('#formModalLabel').text(title);
                    $('#formModal .modal-body').html(html);
                    modal.show();
                }).fail(function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo cargar el formulario del motivo.', 'error');
                }).always(function () {
                    button.prop('disabled', false);
                });
            }

            $('#btnNuevoMotivo').on('click', function () {
                openForm(@json(route('admin.programming.reasons.create')), 'Nuevo Motivo', $(this));
            });

            $(document).on('click', '.btnEditarMotivo', function () {
                const url = @json(route('admin.programming.reasons.edit', ':id')).replace(':id', $(this).data('id'));
                openForm(url, 'Editar Motivo', $(this));
            });

            $(document).on('submit', '#frmMotivo', function (event) {
                event.preventDefault();
                const form = $(this);
                const button = form.find('[type="submit"]');
                if (button.prop('disabled')) return;
                button.prop('disabled', true);
                const errors = form.find('#reasonErrors').empty().addClass('d-none');

                $.ajax({ url: form.attr('action'), type: 'POST', data: form.serialize(), headers: { Accept: 'application/json' } })
                    .done(function (response) {
                        modal.hide();
                        table.ajax.reload(null, false);
                        Swal.fire('Éxito', response.message, 'success');
                    })
                    .fail(function (xhr) {
                        const messages = xhr.responseJSON?.errors
                            ? Object.values(xhr.responseJSON.errors).flat()
                            : [xhr.responseJSON?.message || 'No se pudo guardar el motivo. Intente nuevamente.'];
                        messages.forEach(message => $('<div>').text(message).appendTo(errors));
                        errors.removeClass('d-none');
                    })
                    .always(function () { button.prop('disabled', false); });
            });

            $(document).on('submit', '.frmEliminarMotivo', function (event) {
                event.preventDefault();
                const form = $(this);
                const button = form.find('[type="submit"]');
                if (button.prop('disabled')) return;
                button.prop('disabled', true);

                Swal.fire({
                    title: '¿Eliminar motivo?',
                    text: 'Los cambios anteriores conservarán su motivo en el historial.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        button.prop('disabled', false);
                        return;
                    }
                    $.ajax({ url: form.attr('action'), type: 'POST', data: form.serialize(), headers: { Accept: 'application/json' } })
                        .done(function (response) {
                            table.ajax.reload(null, false);
                            Swal.fire('Éxito', response.message, 'success');
                        })
                        .fail(function (xhr) {
                            Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar el motivo.', 'error');
                        })
                        .always(function () { button.prop('disabled', false); });
                });
            });
        });
    </script>
@endsection
