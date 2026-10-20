
<div class="row">
    <div class="col-md-6 form-group mb-3">
        {!! Form::label('employee_id', 'Personal *') !!}
        {!! Form::select(
            'employee_id',
            $employees,
            null,
            [
                'class' => 'form-select',
                'placeholder' => 'Buscar empleado por nombre o DNI',
                'required'
            ]
        ) !!}
    </div>

    <div class="col-md-6 form-group mb-3">
        {!! Form::label('days_requested', 'Días solicitados *') !!}
        {!! Form::number(
            'days_requested',
            $vacation->days_requested,
            [
                'class' => 'form-control',
                'placeholder' => 'Número de días',
                'min' => 1,
                'required'
            ]
        ) !!}
    </div>
</div>

<div class="row">
    <div class="col-md-6 form-group mb-3">
        {!! Form::label('start_date', 'Fecha de inicio *') !!}
        {!! Form::date(
            'start_date',
            $vacation->start_date?->format('Y-m-d'),
            ['class' => 'form-control', 'required']
        ) !!}
    </div>

    <div class="col-md-6 form-group mb-3">
        {!! Form::label('end_date', 'Fecha de fin *') !!}
        {!! Form::date(
            'end_date',
            $vacation->end_date?->format('Y-m-d'),
            ['class' => 'form-control', 'required']
        ) !!}
    </div>
</div>

<div class="form-group mb-3">
    {!! Form::label('notes', 'Notas adicionales') !!}
    {!! Form::textarea(
        'notes',
        $vacation->notes,
        [
            'class' => 'form-control',
            'placeholder' => 'Observaciones o comentarios sobre la solicitud...',
            'rows' => 3,
            'maxlength' => 1000
        ]
    ) !!}
</div>

