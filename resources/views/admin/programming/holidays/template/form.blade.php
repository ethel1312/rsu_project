<div class="alert alert-danger d-none" id="holidayErrors" role="alert"></div>
<div class="row">
    <div class="col-md-12 mb-3">
        {!! Form::label('date', 'Fecha del feriado *', ['class' => 'font-weight-bold']) !!}
        {!! Form::date('date', isset($holiday) ? $holiday->date->toDateString() : null, ['class' => 'form-control', 'required']) !!}
        <small class="form-text text-muted" id="holidayDay" aria-live="polite">Seleccione una fecha.</small>
    </div>
    <div class="col-md-12 mb-3">
        {!! Form::label('description', 'Descripción *', ['class' => 'font-weight-bold']) !!}
        {!! Form::text('description', null, ['class' => 'form-control', 'required', 'maxlength' => 1000, 'placeholder' => 'Ej: Día de Todos los Santos']) !!}
    </div>
    <div class="col-md-12 mb-3">
        {!! Form::label('is_active', 'Estado', ['class' => 'font-weight-bold']) !!}
        {!! Form::select('is_active', [1 => 'Activo', 0 => 'Inactivo'], isset($holiday) ? (int) $holiday->is_active : 1, ['class' => 'form-control', 'required']) !!}
        <small class="form-text text-muted">Los feriados inactivos no se consideran en las validaciones de programación.</small>
    </div>
</div>
<div class="alert alert-info small">
    <i class="bi bi-info-circle"></i> Los feriados activos estarán disponibles para excluir esos días al programar rutas.
    Puede cargar los feriados nacionales con el botón «Cargar feriados de Perú» o registrarlos manualmente.
</div>
<div class="mt-4 text-right">
    <button type="button" class="btn btn-modal-cancelar mr-2" data-bs-dismiss="modal">Cancelar</button>
    <button type="submit" class="btn btn-modal-guardar">{{ isset($holiday) ? 'Actualizar feriado' : 'Guardar' }}</button>
</div>
