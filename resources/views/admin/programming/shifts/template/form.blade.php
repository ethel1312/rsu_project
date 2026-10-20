<div class="alert alert-danger d-none" id="shiftErrors" role="alert"></div>
<div class="row">
    <div class="col-md-12 mb-3">
        {!! Form::label('name', 'Nombre del turno *', ['class' => 'font-weight-bold']) !!}
        {!! Form::text('name', null, ['class' => 'form-control', 'required', 'maxlength' => 150, 'placeholder' => 'Ej: Mañana, Tarde, Noche']) !!}
    </div>
    <div class="col-md-6 mb-3">
        {!! Form::label('start_time', 'Hora de inicio *', ['class' => 'font-weight-bold']) !!}
        {!! Form::time('start_time', isset($shift) ? substr($shift->start_time, 0, 5) : null, ['class' => 'form-control', 'required', 'step' => 60]) !!}
    </div>
    <div class="col-md-6 mb-3">
        {!! Form::label('end_time', 'Hora de término *', ['class' => 'font-weight-bold']) !!}
        {!! Form::time('end_time', isset($shift) ? substr($shift->end_time, 0, 5) : null, ['class' => 'form-control', 'required', 'step' => 60]) !!}
    </div>
    <div class="col-md-12 mb-3">
        {!! Form::label('description', 'Descripción', ['class' => 'font-weight-bold']) !!}
        {!! Form::textarea('description', null, ['class' => 'form-control', 'rows' => 3, 'maxlength' => 1000, 'placeholder' => 'Descripción del turno (opcional)']) !!}
    </div>
</div>
<div class="alert alert-info small">
    Si la hora de término es anterior a la de inicio, el turno termina al día siguiente.
</div>
<div class="mt-4 text-right">
    <button type="button" class="btn btn-modal-cancelar mr-2" data-bs-dismiss="modal">Cancelar</button>
    <button type="submit" class="btn btn-modal-guardar">{{ isset($shift) ? 'Actualizar turno' : 'Guardar' }}</button>
</div>
