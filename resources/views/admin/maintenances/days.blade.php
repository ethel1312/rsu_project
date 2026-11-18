@extends('adminlte::page')
@section('title', 'Días de mantenimiento')
@section('content_header')
    <div class="rsu-title-bar"><div><h1 class="rsu-title"><span class="rsu-title-ico"><i class="bi bi-calendar-check"></i></span>Días programados</h1><div class="text-muted">{{ $schedule->maintenance->name }} · {{ $schedule->vehicle->code }} · {{ $schedule->vehicle->name }} · {{ $schedule->maintenance->start_date->format('d/m/Y') }} – {{ $schedule->maintenance->end_date->format('d/m/Y') }}</div></div></div>
@stop
@section('css')
<style>
    .maintenance-day-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .22rem .62rem;
        border-radius: 999px;
        font-size: .82rem;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
    }
    .maintenance-day-status-done { background: #72d69a; color: #14532d !important; }
    .maintenance-day-status-missed { background: #e45b68; color: #fff !important; }
    .maintenance-day-status-pending { background: #ffd477; color: #664d03 !important; }
    .maintenance-day-thumbnail { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; border: 1px solid #dfe6f5; cursor: pointer; transition: transform .2s ease; }
    .maintenance-day-thumbnail:hover { transform: scale(1.06); }
    #dayImageModal .modal-dialog { width: 94vw; max-width: 1500px; }
    #dayImageModal .modal-content { overflow: hidden; border: 0; border-radius: 18px; box-shadow: 0 12px 32px rgba(22, 34, 70, .2); }
    #dayImageModal .modal-header { min-height: 64px; padding: 8px 18px; background: #fff; border-bottom: 1px solid #e5ebf6; }
    #dayImageModal .modal-title { color: #172b76; font-size: 1.25rem; font-weight: 600; }
    #dayImageModal .btn-close { box-sizing: border-box; flex: 0 0 48px; width: 48px; min-width: 48px; height: 48px; margin: 0 0 0 auto; padding: 0; border: 4px solid #c7dcff; border-radius: 14px; background-size: .95rem; opacity: 1; }
    #dayImageModal .modal-body { padding: 18px; }
    #dayImagePreview { display: block; width: 100%; max-width: 100%; max-height: 78vh; margin: 0 auto; object-fit: contain; }
    #dayModal .modal-dialog { max-width: 640px; }
    #dayModal .modal-content { overflow: hidden; border: 0; border-radius: 18px; box-shadow: 0 12px 32px rgba(22, 34, 70, .2); }
    #dayModal .modal-header { min-height: 64px; padding: 8px 18px; background: #fff; border-bottom: 1px solid #e5ebf6; }
    #dayModal .modal-title { color: #172b76; font-size: 1.35rem; font-weight: 700; }
    #dayModal .btn-close { box-sizing: border-box; flex: 0 0 48px; width: 48px; min-width: 48px; height: 48px; margin: 0 0 0 auto; padding: 0; border: 4px solid #c7dcff; border-radius: 14px; background-size: .95rem; opacity: 1; }
    #dayModal .modal-body { padding: 20px; }
    #dayModal .maintenance-modal-cancel, #dayModal .maintenance-modal-save { padding: .7rem 1.2rem; border: 0; border-radius: 12px; font-weight: 600; }
    #dayModal .maintenance-modal-cancel { background: #eef2ff; color: #737f97; }
    #dayModal .maintenance-modal-cancel:hover { background: #e2e9fb; color: #596780; }
    #dayModal .maintenance-modal-save { background: #5bb8f5; color: #fff; }
    #dayModal .maintenance-modal-save:hover { background: #42a9ec; color: #fff; }
</style>
@stop
@section('content')
    <div class="mb-3"><a href="{{ route('admin.maintenances.schedules', $schedule->maintenance) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver a horarios</a></div>
    <div class="card"><div class="card-body table-responsive"><table class="table table-striped table-hover align-middle w-100" id="DataTable">
        <thead><tr><th>Fecha</th><th>Observación</th><th>Imagen</th><th>Estado</th><th class="text-center">Editar</th></tr></thead>
    </table></div></div>
    <div class="modal fade" id="dayModal" tabindex="-1" aria-labelledby="dayModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="dayModalLabel">Actualizar día de mantenimiento</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"></div></div></div></div>
    <div class="modal fade" id="dayImageModal" tabindex="-1" aria-labelledby="dayImageModalLabel" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="dayImageModalLabel">Imagen del mantenimiento</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body text-center"><img id="dayImagePreview" src="" alt=""></div></div></div></div>
@stop
@section('js')
<script>
$(function(){
    const modal=bootstrap.Modal.getOrCreateInstance(document.getElementById('dayModal'));
    const imageModal=bootstrap.Modal.getOrCreateInstance(document.getElementById('dayImageModal'));
    const table=$('#DataTable').DataTable({processing:true,serverSide:true,ajax:@json(route('admin.maintenances.days',$schedule)),columns:[
        {data:'date_label',name:'maintenance_date',searchable:false}, {data:'observation',name:'observation',defaultContent:'—'}, {data:'image_label',name:'image_path',orderable:false,searchable:false,className:'text-center'}, {data:'status',name:'status',className:'text-center'}, {data:'actions',name:'actions',orderable:false,searchable:false,className:'text-center'}
    ],language:{url:'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'}});
    function errors(xhr){const list=xhr.responseJSON?.errors;Swal.fire('Revisa los datos',list?Object.values(list).flat().join('<br>'):(xhr.responseJSON?.error||'No se pudo actualizar el día.'),'error');}
    $(document).on('click','.btnVerImagenMantenimiento',function(){const imageUrl=$(this).data('image'),date=$(this).data('date');$('#dayImageModalLabel').text(`Mantenimiento del ${date}`);$('#dayImagePreview').attr({src:imageUrl,alt:`Mantenimiento del ${date}`});imageModal.show();});
    $('#dayImageModal').on('hidden.bs.modal',function(){$('#dayImagePreview').attr({src:'',alt:''});});
    $(document).on('change','#maintenance_day_image',function(){const form=$(this).closest('form'),preview=form.find('#maintenanceImagePreview'),wrap=form.find('#maintenanceImagePreviewWrap'),file=this.files?.[0],oldUrl=preview.data('preview-url');if(oldUrl)URL.revokeObjectURL(oldUrl);if(!file){preview.attr('src',preview.data('current-src')||'');wrap.prop('hidden',!preview.attr('src'));return;}form.find('#remove_maintenance_image').prop('checked',false);const url=URL.createObjectURL(file);preview.attr('src',url).data('preview-url',url);wrap.prop('hidden',false);});
    $(document).on('change','#remove_maintenance_image',function(){const form=$(this).closest('form'),input=form.find('#maintenance_day_image'),preview=form.find('#maintenanceImagePreview'),wrap=form.find('#maintenanceImagePreviewWrap');if(this.checked){input.val('');wrap.prop('hidden',true);}else{preview.attr('src',preview.data('current-src')||'');wrap.prop('hidden',!preview.attr('src'));}});
    $('#dayModal').on('hidden.bs.modal',function(){const preview=$('#maintenanceImagePreview'),previewUrl=preview.data('preview-url');if(previewUrl)URL.revokeObjectURL(previewUrl);});
    $(document).on('click','.btnEditarDia',function(){$.get($(this).data('url')).done(html=>{$('#dayModal .modal-body').html(html);modal.show();}).fail(()=>Swal.fire('Error','No se pudo cargar el día.','error'));});
    $(document).on('submit','.frmDiaMantenimiento',function(event){event.preventDefault();const form=this,button=$(form).find('[type=submit]').prop('disabled',true);$.ajax({url:form.action,type:'POST',data:new FormData(form),processData:false,contentType:false}).done(response=>{modal.hide();table.ajax.reload(null,false);Swal.fire('Guardado',response.message,'success');}).fail(xhr=>{button.prop('disabled',false);errors(xhr);});});
});
</script>
@stop
