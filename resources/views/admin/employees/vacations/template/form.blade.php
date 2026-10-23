
<div class="row">

    <div class="col-md-6 form-group mb-3">
        {!! Form::label('employee_id', 'Personal *') !!}
        {!! Form::select(
            'employee_id',
            $employees,
            null,
            [
                'class' => 'form-select select2-employees',
                'placeholder' => 'Escribe el nombre o DNI',
                'required',
                'id' => 'employee_id',
                'style' => 'width: 100%;'
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
                'id' => 'days_requested',
                'placeholder' => 'Ingrese los días solicitados',
                'min' => 1,
                'step' => 1,
                'required'
            ]
        ) !!}
        <small class="form-text text-muted">Ingrese la cantidad de días de vacaciones</small>
        <div id="days_requested_error" class="invalid-feedback" role="alert" style="display: none;"></div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 form-group mb-3">
        {!! Form::label('start_date', 'Fecha de inicio *') !!}
        {!! Form::date(
            'start_date',
            $vacation->start_date?->format('Y-m-d'),
            [
                'class' => 'form-control',
                'id' => 'start_date',
                'min' => now()->toDateString(),
                'required'
            ]
        ) !!}
    </div>

    <div class="col-md-6 form-group mb-3">
        {!! Form::label('end_date', 'Fecha de fin *') !!}
        {!! Form::date(
            'end_date',
            $vacation->end_date?->format('Y-m-d'),
            [
                'class' => 'form-control',
                'id' => 'end_date',
                'placeholder' => 'Se calcula automáticamente',
                'readonly' => true,
                'required'
            ]
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

<div id="vacationSummary" class="vacation-summary" style="display: none;" aria-live="polite">
    <div class="vacation-summary-title">
        <i class="bi bi-calendar-check"></i>
        <strong>Resumen de vacaciones del trabajador</strong>
    </div>
    <div class="vacation-summary-values">
        <div>
            <span>Disponibles ahora</span>
            <strong id="vacationDaysAvailable">0 días</strong>
        </div>
        <div>
            <span>Esta solicitud</span>
            <strong id="vacationDaysRequested">0 días</strong>
        </div>
        <div>
            <span>Saldo si se aprueba</span>
            <strong id="vacationDaysAfterApproval">0 días</strong>
        </div>
    </div>
    <div class="vacation-summary-note">
        <i class="bi bi-info-circle-fill"></i>
        Los días se descuentan cuando la solicitud sea aprobada.
    </div>
</div>
