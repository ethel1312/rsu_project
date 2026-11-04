@extends('adminlte::page')
@section('title', 'Permisos de Usuario')
@section('content_header')
    <div class="d-flex justify-content-between flex-wrap">
        <div><h1>Permisos de Usuario</h1><p>{{ $user->name }} {{ $user->last_name }} · {{ $user->email }}</p></div>
        @can('users.view')<a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary align-self-start">Volver a Usuarios</a>@endcan
    </div>
@stop
@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        .permissions-banner, .permissions-banner label { color:#fff !important; }
        .permission-option { display:flex; align-items:center; gap:.6rem; background:#f5f7fa; padding:.8rem; height:100%; border-radius:.35rem; cursor:pointer; }
        .permission-option input { margin-left:auto; flex-shrink:0; }
        .permission-option small { display:block; color:#6c757d; }
        .permission-footer { position:sticky; bottom:0; background:white; padding:1rem; border-top:1px solid #dee2e6; z-index:2; }
    </style>
@stop
@section('content')
    @if ($user->is_admin)
        <div class="alert alert-info">Esta es una cuenta de administrador principal. Conserva todos los permisos para garantizar la administración del sistema.</div>
    @endif
    <form id="permissionsForm" action="{{ route('admin.users.permissions.update', $user) }}" method="POST">
        @csrf @method('PUT')
        <div class="card bg-primary permissions-banner"><div class="card-body d-flex justify-content-between flex-wrap">
            <div><strong>Configurar permisos de acceso</strong><div>Seleccione las acciones permitidas para cada módulo.</div></div>
            <label><input type="checkbox" id="selectAll" @disabled($user->is_admin)> Seleccionar todos</label>
        </div></div>
        <div class="row">
            @foreach ($sections as $section => $modules)
                <div class="col-xl-6"><section class="card permission-section">
                    <div class="card-header d-flex justify-content-between"><h3 class="card-title">{{ $section }}</h3><label class="ml-auto mb-0"><input type="checkbox" class="select-section" @disabled($user->is_admin)> Todo</label></div>
                    <div class="card-body">
                        @foreach ($modules as $module)
                            <h6 class="border-bottom pb-2">{{ $module['label'] }}</h6>
                            @if (!($module['available'] ?? true))<p class="text-muted small">Módulo aún no disponible en este proyecto.</p>@endif
                            <div class="row mb-3">
                                @foreach ($module['permissions'] as $key => $label)
                                    <div class="col-md-6 mb-2">
                                        <label class="permission-option">
                                            <span>{{ $label }}<small>{{ $key }}</small></span>
                                            <input type="checkbox" class="permission-check" name="permissions[]" value="{{ $key }}"
                                                @checked(($module['available'] ?? true) && ($user->is_admin || in_array($key, $user->permissions ?? [])))
                                                @disabled($user->is_admin || !($module['available'] ?? true))>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </section></div>
            @endforeach
        </div>
        <div class="permission-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-primary" @disabled($user->is_admin)>Guardar permisos</button>
        </div>
    </form>
@stop
@section('js')
<script>
$(function(){
    function reflect(master, checks) {
        const count = checks.filter(':checked').length;
        master.prop('checked',checks.length > 0 && count === checks.length).prop('indeterminate',count > 0 && count < checks.length);
    }
    function refresh(){
        $('.permission-section').each(function(){reflect($(this).find('.select-section'),$(this).find('.permission-check:enabled'));});
        reflect($('#selectAll'),$('.permission-check:enabled'));
    }
    $('#selectAll').on('change',function(){$('.permission-check:enabled').prop('checked',this.checked);refresh();});
    $('.select-section').on('change',function(){$(this).closest('.permission-section').find('.permission-check:enabled').prop('checked',this.checked);refresh();});
    $('.permission-check').on('change',refresh);
    refresh();
    $('#permissionsForm').on('submit',function(event){
        event.preventDefault();
        const form = $(this), button = form.find('[type=submit]');
        if(button.prop('disabled')) return;
        button.prop('disabled',true);
        $.ajax({url:form.attr('action'),type:'POST',data:form.serialize(),headers:{Accept:'application/json'}})
            .done(result => Swal.fire('Permisos guardados',result.message,'success'))
            .fail(xhr => Swal.fire('Error',xhr.responseJSON?.message || 'No se pudieron guardar los permisos.','error'))
            .always(() => button.prop('disabled',false));
    });
});
</script>
@stop
