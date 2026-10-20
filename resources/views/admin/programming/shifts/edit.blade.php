{!! Form::model($shift, ['route' => ['admin.programming.shifts.update', $shift], 'id' => 'frmTurno', 'method' => 'PUT']) !!}
    @include('admin.programming.shifts.template.form')
{!! Form::close() !!}
