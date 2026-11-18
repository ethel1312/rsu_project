@extends('adminlte::page')
@section('title', 'Mantenimientos')
@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title"><span class="rsu-title-ico"><i class="bi bi-wrench-adjustable"></i></span>Mantenimientos programados</h1>
        <button type="button" id="btnNuevoMantenimiento" class="btn rsu-btn-new"><i class="bi bi-plus-circle me-1"></i>Nuevo mantenimiento</button>
    </div>
@stop
@section('css')
<style>
    .maintenance-schedule-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .32rem .68rem;
        border-radius: 999px;
        background-color: #5bb8f5;
        color: #fff !important;
        font-size: .82rem;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
    }
</style>
@stop
@section('content')
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped table-hover align-middle w-100" id="DataTable">
            <thead><tr><th>Nombre</th><th>Fecha de inicio</th><th>Fecha de fin</th><th>Horarios</th><th class="text-center">Acciones</th></tr></thead>
        </table>
    </div></div>

    <div class="modal fade" id="formModal" tabindex="-1" aria-labelledby="formModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header">
            <h5 class="modal-title" id="formModalLabel">Mantenimiento</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div><div class="modal-body"></div></div></div>
    </div>
@stop
@section('js')
<script>
$(function () {
    const modalElement = document.getElementById('formModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const table = $('#DataTable').DataTable({
        processing: true, serverSide: true, ajax: @json(route('admin.maintenances.index')),
        columns: [
            {data:'name', name:'name', className:'align-middle font-weight-bold'},
            {data:'start_date', name:'start_date', className:'align-middle'},
            {data:'end_date', name:'end_date', className:'align-middle'},
            {data:'schedule_count', name:'schedules_count', orderable:false, searchable:false, className:'align-middle text-center'},
            {data:'actions', name:'actions', orderable:false, searchable:false, className:'align-middle text-center text-nowrap', width:'190px'}
        ], language: {url:'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'}
    });

    function openForm(url, title) {
        $.get(url).done(html => { $('#formModalLabel').text(title); $('#formModal .modal-body').html(html); modal.show(); })
            .fail(() => Swal.fire('Error', 'No se pudo cargar el formulario.', 'error'));
    }
    function showErrors(xhr) {
        const errors = xhr.responseJSON?.errors;
        const message = errors ? Object.values(errors).flat().join('<br>') : (xhr.responseJSON?.error || 'No se pudo completar la operación.');
        Swal.fire('Revisa los datos', message, 'error');
    }

    $('#btnNuevoMantenimiento').on('click', () => openForm(@json(route('admin.maintenances.create')), 'Registrar mantenimiento'));
    $(document).on('click', '.btnEditarMantenimiento', function () { openForm($(this).data('url'), 'Editar mantenimiento'); });
    $(document).off('change', '#maintenance_start').on('change', '#maintenance_start', function () {
        const startDate = this.value;
        const endDate = document.getElementById('maintenance_end');
        if (!endDate) return;
        endDate.min = startDate;
        if (endDate.value && endDate.value < startDate) endDate.value = '';
    });
    $(document).on('submit', '.frmMantenimiento', function (event) {
        event.preventDefault(); const form = $(this); const button = form.find('[type="submit"]').prop('disabled', true);
        $.ajax({url:form.attr('action'), type:form.find('[name="_method"]').val() || form.attr('method'), data:form.serialize()})
            .done(response => { modal.hide(); table.ajax.reload(null, false); Swal.fire('Guardado', response.message, 'success'); })
            .fail(xhr => { button.prop('disabled', false); showErrors(xhr); });
    });
    $(document).on('submit', '.frmEliminarMantenimiento', function (event) {
        event.preventDefault(); const form = $(this);
        Swal.fire({title:'¿Eliminar mantenimiento?', text:'También se eliminarán sus horarios y días pendientes. Los días con resultados registrados están protegidos.', icon:'warning', showCancelButton:true, confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar', confirmButtonColor:'#dc3545'})
            .then(result => { if (!result.isConfirmed) return; $.ajax({url:form.attr('action'), type:'DELETE', data:form.serialize()}).done(response => {table.ajax.reload(null,false); Swal.fire('Eliminado',response.message,'success');}).fail(showErrors); });
    });
});
</script>
@stop
