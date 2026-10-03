{!! Form::model($color, ['route' => ['admin.colors.update', $color], 'method' => 'put', 'id' => 'frmColor']) !!}
    @include('admin.colors.template.form')
    <div class="mt-3 text-right">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
        <button type="submit" class="btn btn-warning text-dark"><i class="bi bi-pencil-square"></i> Actualizar</button>
    </div>
{!! Form::close() !!}