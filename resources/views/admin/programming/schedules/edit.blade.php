{!! Form::model($schedule, ['route' => ['admin.programming.schedules.update', $schedule], 'method' => 'put', 'id' => 'frmSchedule']) !!}
    @include('admin.programming.schedules.template.form')
{!! Form::close() !!}
