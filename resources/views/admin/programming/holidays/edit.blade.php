{!! Form::model($holiday, ['route' => ['admin.programming.holidays.update', $holiday], 'id' => 'frmFeriado', 'method' => 'PUT']) !!}
    @include('admin.programming.holidays.template.form')
{!! Form::close() !!}
