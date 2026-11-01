@extends('adminlte::page')
@section('title', 'Usuarios')
@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title"><span class="rsu-title-ico"><i class="bi bi-people"></i></span> Lista de Usuarios</h1>
        @can('users.create')<button type="button" id="newUser" class="btn rsu-btn-new"><i class="bi bi-plus-circle"></i> Nuevo Usuario</button>@endcan
    </div>
@stop
@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        #userModal .modal-dialog { height:calc(100% - 3.5rem); }
        #userModal .modal-content { max-height:100%; overflow:hidden; }
        #userModal .modal-body { overflow-y:auto; min-height:0; }
    </style>
@stop
@section('content')
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped" id="usersTable" style="width:100%">
            <thead><tr><th>Foto</th><th>Nombre</th><th>Apellidos</th><th>DNI</th><th>Email</th><th>Teléfono</th><th>Tipo usuario</th><th>Estado</th><th>Actualización</th><th>Acciones</th></tr></thead>
        </table>
    </div></div>
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalTitle" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="userModalTitle">Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"></div>
        </div></div>
    </div>
@stop
@section('js')
<script>
$(function () {
    const table = $('#usersTable').DataTable({
        processing:true, serverSide:true, ajax:@json(route('admin.users.index')),
        columns:[
            {data:'photo', orderable:false, searchable:false},
            ...['name','last_name','dni','email','phone','user_type','is_active','updated_at'].map(key => ({data:key,name:key,defaultContent:'—'})),
            {data:'actions',orderable:false,searchable:false}
        ],
        order:[[1,'asc']], language:{url:'https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'}
    });
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal'));
    function openForm(url, title, button) {
        button.prop('disabled',true);
        $.get(url).done(html => {
            $('#userModalTitle').text(title);
            $('#userModal .modal-body').html(html);
            modal.show();
        }).fail(xhr => Swal.fire('Error',xhr.responseJSON?.message || 'No se pudo abrir el formulario.','error'))
          .always(() => button.prop('disabled',false));
    }
    $('#newUser').on('click',function(){openForm(@json(route('admin.users.create')),'Nuevo Usuario',$(this));});
    $(document).on('click','.edit-user',function(){openForm($(this).data('url'),'Editar Usuario',$(this));});
    $(document).on('submit','#userForm',function(event){
        event.preventDefault();
        const form = $(this), button = form.find('[type=submit]');
        button.prop('disabled',true);
        $('#userErrors').addClass('d-none').empty();
        $.ajax({url:form.attr('action'),type:'POST',data:new FormData(this),processData:false,contentType:false,headers:{Accept:'application/json'}})
            .done(result => {modal.hide();table.ajax.reload(null,false);Swal.fire('Guardado',result.message,'success');})
            .fail(xhr => {
                const errors = xhr.responseJSON?.errors ? Object.values(xhr.responseJSON.errors).flat() : [xhr.responseJSON?.message || 'No se pudo guardar.'];
                const box = $('#userErrors').removeClass('d-none');
                errors.forEach(error => $('<div>').text(error).appendTo(box));
                $('#userModal .modal-body').scrollTop(0);
            }).always(() => button.prop('disabled',false));
    });
    $(document).on('click','.delete-user',function(){
        const button = $(this);
        Swal.fire({title:'¿Eliminar usuario?',text:'Se cerrarán sus sesiones y se eliminará su cuenta.',icon:'warning',showCancelButton:true,confirmButtonText:'Eliminar',cancelButtonText:'Cancelar'})
            .then(result => {
                if (!result.isConfirmed) return;
                button.prop('disabled',true);
                $.ajax({url:button.data('url'),type:'DELETE',data:{_token:@json(csrf_token())},headers:{Accept:'application/json'}})
                    .done(result => {table.ajax.reload(null,false);Swal.fire('Eliminado',result.message,'success');})
                    .fail(xhr => Swal.fire('Error',xhr.responseJSON?.message || 'No se pudo eliminar.','error'))
                    .always(() => button.prop('disabled',false));
            });
    });
});
</script>
@stop
