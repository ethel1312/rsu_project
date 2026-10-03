<div class="row">
    {{-- Fila 1: Datos Principales --}}
    <div class="col-md-3 mb-3">
        {!! Form::label('code', 'Código *') !!}
        {!! Form::text('code', null, ['class' => 'form-control', 'placeholder' => 'Ej. V-001', 'required']) !!}
    </div>
    <div class="col-md-3 mb-3">
        {!! Form::label('plate', 'Placa *') !!}
        {!! Form::text('plate', null, ['class' => 'form-control', 'placeholder' => 'Ej. ABC-123 o XXXXXX', 'required']) !!}
    </div>
    <div class="col-md-3 mb-3">
        {!! Form::label('year', 'Año *') !!}
        {!! Form::number('year', null, ['class' => 'form-control', 'min' => '1980', 'max' => date('Y')+1, 'required']) !!}
    </div>
    <div class="col-md-3 mb-3">
        {!! Form::label('name', 'Nombre/Alias *') !!}
        {!! Form::text('name', null, ['class' => 'form-control', 'placeholder' => 'Ej. Recolector Norte', 'required']) !!}
    </div>

    {{-- Fila 2: Catálogos --}}
    <div class="col-md-3 mb-3">
        {!! Form::label('type_id', 'Tipo de Vehículo *') !!}
        {!! Form::select('type_id', $types, null, ['class' => 'form-select', 'placeholder' => 'Seleccione...', 'required']) !!}
    </div>
    <div class="col-md-3 mb-3">
        {!! Form::label('brand_id', 'Marca *') !!}
        {!! Form::select('brand_id', $brands, null, ['class' => 'form-select', 'placeholder' => 'Seleccione...', 'required']) !!}
    </div>
    <div class="col-md-3 mb-3">
        {!! Form::label('model_id', 'Modelo *') !!}
        {!! Form::select('model_id', $models, null, ['class' => 'form-select', 'placeholder' => 'Seleccione...', 'required']) !!}
    </div>
    <div class="col-md-3 mb-3">
        {!! Form::label('color_id', 'Color *') !!}
        {!! Form::select('color_id', $colors, null, ['class' => 'form-select', 'placeholder' => 'Seleccione...', 'required']) !!}
    </div>

    {{-- Fila 3: Capacidades --}}
    <div class="col-md-3 mb-3">
        {!! Form::label('load_capacity', 'Capac. Carga (Tn) *') !!}
        {!! Form::number('load_capacity', null, ['class' => 'form-control', 'step' => '0.01', 'required']) !!}
    </div>
    <div class="col-md-3 mb-3">
        {!! Form::label('compact_capacity', 'Capac. Compactación (Tn) *') !!}
        {!! Form::number('compact_capacity', null, ['class' => 'form-control', 'step' => '0.01', 'required']) !!}
    </div>
    <div class="col-md-3 mb-3">
        {!! Form::label('fuel_capacity', 'Capac. Combustible (L) *') !!}
        {!! Form::number('fuel_capacity', null, ['class' => 'form-control', 'step' => '0.01', 'required']) !!}
    </div>
    <div class="col-md-3 mb-3">
        {!! Form::label('occupant_capacity', 'Ocupantes *') !!}
        {!! Form::number('occupant_capacity', null, ['class' => 'form-control', 'min' => '1', 'required']) !!}
    </div>

    {{-- Fila 4: Descripción --}}
    <div class="col-md-12 mb-3">
        {!! Form::label('description', 'Descripción Adicional') !!}
        {!! Form::textarea('description', null, ['class' => 'form-control', 'rows' => 2, 'placeholder' => 'Detalles extra del vehículo...']) !!}
    </div>
</div>