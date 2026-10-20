{!! Form::model($vacation, ['route' => ['admin.vacations.update', $vacation], 'method' => 'put', 'id' => 'frmVacation']) !!}
    @include('admin.employees.vacations.template.form')
    <div class="mt-3 text-right">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Actualizar</button>
    </div>
{!! Form::close() !!}
