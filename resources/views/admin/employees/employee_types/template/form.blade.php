<div class="row">
    <div class="col-12">
        <div class="form-group mb-3">
            {!! Form::label('name', 'Nombre *') !!}

            {!! Form::text('name', null, [
                'class' => 'form-control',
                'placeholder' => 'Ingrese el nombre',
                'required',
                'readonly' => isset($employeeType) && $employeeType->is_default
            ]) !!}

            @if(isset($employeeType) && $employeeType->is_default)
                <small class="text-muted">
                    <i class="bi bi-lock-fill"></i>
                    El nombre de este tipo predeterminado no puede modificarse.
                </small>
            @endif
        </div>

        <div class="form-group mb-3">
            {!! Form::label('description', 'Descripción') !!}
            {!! Form::textarea('description', null, [
                'class' => 'form-control',
                'placeholder' => 'Agregue una descripción (opcional)',
                'rows' => 3
            ]) !!}
        </div>
    </div>
</div>