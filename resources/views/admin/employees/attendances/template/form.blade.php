@if ($attendance->exists)
    <input type="hidden" name="attendance_id" value="{{ $attendance->id }}">
@endif
<div class="row">
    @if ($attendance->exists)
        <input type="hidden" name="employee_id" value="{{ $attendance->employee_id }}">
        <input type="hidden" name="date" value="{{ $attendance->date->format('Y-m-d') }}">
        <input type="hidden" name="status" value="{{ $attendance->status }}">
        <div class="col-12 mb-3">
            {!! Form::label('employee_display', 'Personal') !!}
            <input id="employee_display" class="form-control" value="{{ $employee ? $employee->dni.' - '.$employee->last_name.', '.$employee->first_name : 'Personal no disponible' }}" readonly>
        </div>
        <div class="col-md-6 mb-3">
            {!! Form::label('date_display', 'Fecha') !!}
            <input id="date_display" class="form-control" value="{{ $attendance->date->format('d/m/Y') }}" readonly>
        </div>
        <div class="col-md-6 mb-3">
            {!! Form::label('status_display', 'Estado') !!}
            <input id="status_display" class="form-control" value="{{ $attendance->status === 'present' ? 'Presente' : 'Ausente' }}" readonly>
        </div>
        <div class="col-md-6 mb-3">
            <label for="attendance_type">Tipo automático</label>
            <input id="attendance_type" class="form-control" readonly value="{{ $attendance->type === 'entry' ? 'Ingreso' : ($attendance->type === 'exit' ? 'Salida' : 'No aplica (ausencia)') }}" aria-describedby="type-help">
            <small id="type-help" class="text-muted">Las marcaciones del día se alternan: ingreso, salida, ingreso, salida.</small>
        </div>
    @else
    <div class="col-12 mb-3">
        {!! Form::label('employee_id', 'Personal *') !!}
        {!! Form::select('employee_id', $employees, null, ['class' => 'form-select', 'placeholder' => 'Buscar por DNI, nombres o apellidos', 'aria-required' => 'true', 'aria-describedby' => 'employee-help']) !!}
        <small id="employee-help" class="text-muted">Escriba al menos 2 caracteres y seleccione una coincidencia. Puede combinar nombres y apellidos.</small>
    </div>
    <div class="col-md-6 mb-3">
        {!! Form::label('date', 'Fecha *') !!}
        {!! Form::date('date', $attendance->date->format('Y-m-d'), ['class' => 'form-control', 'required']) !!}
    </div>
    <div class="col-md-6 mb-3">
        <label for="attendance_type">Tipo automático</label>
        <input id="attendance_type" class="form-control" readonly value="Seleccione personal, fecha y hora" aria-describedby="type-help">
        <small id="type-help" class="text-muted">Las marcaciones del día se alternan: ingreso, salida, ingreso, salida.</small>
    </div>
    <div class="col-md-6 mb-3">
        {!! Form::label('status', 'Estado *') !!}
        {!! Form::select('status', ['present' => 'Presente', 'absent' => 'Ausente'], null, ['class' => 'form-select', 'required']) !!}
        <small class="text-muted">Una ausencia no cuenta como ingreso ni salida.</small>
    </div>
    @endif
    <div class="col-md-6 mb-3">
        {!! Form::label('time', 'Hora *') !!}
        {!! Form::time('time', $attendance->time, ['class' => 'form-control', 'step' => 1, 'required']) !!}
        <small class="text-muted">Hora local de Lima.</small>
    </div>
    <div class="col-12 mb-3">
        {!! Form::label('notes', 'Notas adicionales') !!}
        {!! Form::textarea('notes', null, ['class' => 'form-control', 'rows' => 3, 'maxlength' => 1000, 'placeholder' => 'Observaciones o motivo de la corrección (opcional)']) !!}
    </div>
</div>
@if ($attendance->exists)
    <div class="alert alert-info mb-0"><i class="bi bi-info-circle"></i> Al corregir una asistencia se recalculan los ingresos y salidas de las fechas involucradas.</div>
@endif
