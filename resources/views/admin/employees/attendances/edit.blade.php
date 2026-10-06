{!! Form::model($attendance, ['route' => ['admin.attendances.update', $attendance], 'method' => 'put', 'id' => 'frmAttendance']) !!}
    @include('admin.employees.attendances.template.form')
    <div class="mt-3 text-end">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Actualizar Asistencia</button>
    </div>
{!! Form::close() !!}
