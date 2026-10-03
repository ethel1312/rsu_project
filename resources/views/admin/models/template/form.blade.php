<div class="row">
    <div class="col-12">
        <div class="form-group">
            {!! Form::label('name', 'Nombre') !!}
            {!! Form::text('name', null, ['class' => 'form-control', 'placeholder' => 'Nombre del modelo', 'required']) !!}
        </div>
        
        <div class="form-group">
            {!! Form::label('code', 'Código') !!}
            {!! Form::text('code', null, ['class' => 'form-control', 'placeholder' => 'Código del modelo']) !!}
        </div>
        
        <div class="form-group">
            {!! Form::label('brand_id', 'Marca') !!}
            <!-- Aquí pasamos $brands que viene del controlador -->
            {!! Form::select('brand_id', $brands, null, ['class' => 'form-select', 'placeholder' => 'Seleccione una marca', 'required']) !!}
        </div>

        <div class="form-group">
            {!! Form::label('description', 'Descripción') !!}
            {!! Form::textarea('description', null, ['class' => 'form-control', 'placeholder' => 'Descripción del modelo', 'rows' => 3]) !!}
        </div>
    </div>
</div>