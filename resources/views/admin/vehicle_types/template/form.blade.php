<div class="row">
    <div class="col-12">
        <div class="form-group mb-3">
            {!! Form::label('name', 'Nombre') !!}
            {!! Form::text('name', null, ['class' => 'form-control', 'placeholder' => 'Ej. Camión Recolector, Compactador', 'required']) !!}
        </div>
        <div class="form-group mb-3">
            {!! Form::label('description', 'Descripción') !!}
            {!! Form::textarea('description', null, ['class' => 'form-control', 'placeholder' => 'Agregue una descripción (opcional)', 'rows' => 3]) !!}
        </div>
    </div>
</div>