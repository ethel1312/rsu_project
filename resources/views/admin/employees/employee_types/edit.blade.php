{!! Form::model($employeeType, ['route' => ['admin.employee_types.update', $employeeType], 'method' => 'put', 'id' => 'frmEmployeeType']) !!}
    @include('admin.employees.employee_types.template.form')
    <div class="mt-3 text-right">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Actualizar</button>
    </div>
{!! Form::close() !!}