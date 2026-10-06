{!! Form::model($employee, ['route' => ['admin.employees.update', $employee], 'method' => 'put', 'id' => 'frmEmployee']) !!}
    @include('admin.employees.employees.template.form')
    <div class="mt-3 text-right">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Actualizar Empleado</button>
    </div>
{!! Form::close() !!}