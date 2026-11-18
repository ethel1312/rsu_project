@extends('adminlte::page')
@section('title', 'Horarios de mantenimiento')
@section('css')
<style>
    #scheduleModal .modal-dialog { max-width: 640px; }
    #scheduleModal .modal-content { overflow: hidden; border: 0; border-radius: 18px; box-shadow: 0 12px 32px rgba(22, 34, 70, .2); }
    #scheduleModal .modal-header { min-height: 64px; padding: 8px 18px; background: #fff; border-bottom: 1px solid #e5ebf6; }
    #scheduleModal .modal-title { color: #172b76; font-size: 1.35rem; font-weight: 700; }
    #scheduleModal .btn-close { box-sizing: border-box; flex: 0 0 48px; width: 48px; min-width: 48px; height: 48px; margin: 0 0 0 auto; padding: 0; border: 4px solid #c7dcff; border-radius: 14px; background-size: .95rem; opacity: 1; }
    #scheduleModal .modal-body { padding: 20px; }
    #scheduleModal .maintenance-modal-cancel, #scheduleModal .maintenance-modal-save { padding: .7rem 1.2rem; border: 0; border-radius: 12px; font-weight: 600; }
    #scheduleModal .maintenance-modal-cancel { background: #eef2ff; color: #737f97; }
    #scheduleModal .maintenance-modal-cancel:hover { background: #e2e9fb; color: #596780; }
    #scheduleModal .maintenance-modal-save { background: #5bb8f5; color: #fff; }
    #scheduleModal .maintenance-modal-save:hover { background: #42a9ec; color: #fff; }
</style>
@stop
@section('content_header')
    <div class="rsu-title-bar">
        <div><h1 class="rsu-title"><span class="rsu-title-ico"><i class="bi bi-calendar-week"></i></span>Horarios del mantenimiento</h1><div class="text-muted">{{ $maintenance->name }} · {{ $maintenance->start_date->format('d/m/Y') }} – {{ $maintenance->end_date->format('d/m/Y') }}</div></div>
        <button type="button" id="btnNuevoHorario" class="btn rsu-btn-new"><i class="bi bi-plus-circle me-1"></i>Agregar horario</button>
    </div>
@stop
@section('content')
    <div class="mb-3"><a href="{{ route('admin.maintenances.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver a mantenimientos</a></div>
    <div class="card"><div class="card-body table-responsive"><table class="table table-striped table-hover align-middle w-100" id="DataTable">
        <thead><tr><th>Día</th><th>Vehículo</th><th>Responsable</th><th>Tipo</th><th>Hora de inicio</th><th>Hora de fin</th><th>Ver días</th><th>Editar</th><th>Eliminar</th></tr></thead>
    </table></div></div>
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="scheduleModalLabel">Registrar horario</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"></div></div></div></div>
@stop
@section('js')
<script>
$(function () {
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('scheduleModal'));
    const table = $('#DataTable').DataTable({processing:true,serverSide:true,ajax:@json(route('admin.maintenances.schedules',$maintenance)),columns:[
        {data:'weekday_label',name:'day_of_week',searchable:false}, {data:'vehicle_label',name:'vehicle_id',searchable:false}, {data:'employee_label',name:'employee_id',searchable:false},
        {data:'type',name:'type'}, {data:'start_time',name:'start_time',render:data=>String(data).slice(0,5)}, {data:'end_time',name:'end_time',render:data=>String(data).slice(0,5)},
        {data:'days_link',name:'days_link',orderable:false,searchable:false,className:'text-center align-middle'},
        {data:'edit_action',name:'edit_action',orderable:false,searchable:false,className:'text-center align-middle'},
        {data:'delete_action',name:'delete_action',orderable:false,searchable:false,className:'text-center align-middle'}
    ],language:{url:'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'}});
    function openForm(url,title){$.get(url).done(html=>{$('#scheduleModalLabel').text(title);$('#scheduleModal .modal-body').html(html);modal.show();}).fail(()=>Swal.fire('Error','No se pudo cargar el formulario del horario.','error'));}
    function showErrors(xhr){const errors=xhr.responseJSON?.errors;const messages=errors?Object.values(errors).flat() : [xhr.responseJSON?.error||'No se pudo guardar el horario.'];const content=messages.map(message=>`<p class="mb-2">${$('<div>').text(message).html()}</p>`).join('');Swal.fire({title:'Revisa los datos',html:content,icon:'error',confirmButtonText:'Entendido'});}
    $('#btnNuevoHorario').on('click',()=>openForm(@json(route('admin.maintenances.schedules.create',$maintenance)),'Registrar horario'));
    $(document).on('click','.btnEditarHorario',function(){openForm($(this).data('url'),'Editar horario');});
    $(document).on('submit','.frmHorario',function(event){event.preventDefault();const form=$(this),button=form.find('[type=submit]').prop('disabled',true);$.ajax({url:form.attr('action'),type:form.find('[name=_method]').val()||form.attr('method'),data:form.serialize()}).done(response=>{modal.hide();table.ajax.reload(null,false);Swal.fire('Guardado',response.message,'success');}).fail(xhr=>{button.prop('disabled',false);showErrors(xhr);});});
    $(document).on('submit','.frmEliminarHorario',function(event){event.preventDefault();const form=$(this);Swal.fire({title:'¿Eliminar horario?',text:'Se eliminarán también los días generados. Los días con resultados registrados están protegidos.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',confirmButtonColor:'#dc3545'}).then(result=>{if(!result.isConfirmed)return;$.ajax({url:form.attr('action'),type:'DELETE',data:form.serialize()}).done(response=>{table.ajax.reload(null,false);Swal.fire('Eliminado',response.message,'success');}).fail(showErrors);});});
});
</script>
@stop
