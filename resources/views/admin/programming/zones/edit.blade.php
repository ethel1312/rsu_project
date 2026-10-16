{!! Form::model($zone, [
    'route' => ['admin.programming.zones.update', $zone],
    'method' => 'PUT',
    'id' => 'frmZona'
]) !!}

    @include('admin.programming.zones.template.form')

    <div class="mt-4 text-right">
        <button type="button" class="btn btn-modal-cancelar mr-2" data-bs-dismiss="modal">
            <i class="bi bi-x-circle"></i> Cancelar
        </button>
        <button type="submit" class="btn btn-modal-guardar">
            <i class="bi bi-pencil-square"></i> Actualizar Zona
        </button>
    </div>

{!! Form::close() !!}