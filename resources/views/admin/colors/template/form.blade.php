<div class="row">
    <div class="col-12">
        <div class="form-group mb-3">
            {!! Form::label('name', 'Nombre del Color') !!}
            {!! Form::text('name', null, ['class' => 'form-control', 'placeholder' => 'Ej. Azul, Gris metálico', 'required']) !!}
        </div>
        
        <div class="form-group mb-3">
            {!! Form::label('code', 'Código del Color (RGB/Hex)') !!}
            <div class="input-group">
                {!! Form::text('code', $color->code ?? '#000000', ['class' => 'form-control', 'id' => 'hexCodeInput', 'required']) !!}
                <input type="color" class="form-control form-control-color" id="colorPicker" value="{{ $color->code ?? '#000000' }}" title="Elige un color">
            </div>
            <small class="text-muted">Haz clic en el selector o ingresa el código hexadecimal.</small>
        </div>

        <div class="form-group mb-3">
            {!! Form::label('description', 'Descripción') !!}
            {!! Form::textarea('description', null, ['class' => 'form-control', 'placeholder' => 'Agregue una descripción (opcional)', 'rows' => 2]) !!}
        </div>

        <!-- Caja de Vista Previa -->
        <div class="form-group mb-2">
            <label>Vista Previa del Color:</label>
            <div id="colorPreviewBox" class="rounded d-flex align-items-center justify-content-center text-white font-weight-bold shadow-sm" 
                 style="height: 60px; background-color: {{ $color->code ?? '#000000' }}; transition: 0.3s;">
                <span id="previewText" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.8);">{{ $color->code ?? '#000000' }}</span>
            </div>
        </div>
    </div>
</div>

