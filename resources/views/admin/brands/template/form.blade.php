<div class="row brand-form-row">
    <div class="col-12 col-sm-8">
        <div class="form-group">
            {!! Form::label('name', 'Nombre') !!}
            {!! Form::text('name', null, [
                'class' => 'form-control',
                'placeholder' => 'Ejemplo Hyundai, Kia...',
                'required',
            ]) !!}
        </div>

        <div class="form-group">
            {!! Form::label('description', 'Descripción') !!}
            {!! Form::textarea('description', null, [
                'class' => 'form-control',
                'placeholder' => 'Descripción de la marca',
                'rows' => 2,
            ]) !!}
        </div>
    </div>

    <div class="col-12 col-sm-4">
        <div class="form-group">
            <button type="button" id="imageButton" class="brand-image-picker"
                aria-label="Seleccionar logo de la marca">
                <img
                    id="imagePreview"
                    class="brand-image-preview"
                    src="{{ isset($brand) && $brand->logo ? asset($brand->logo) : asset('img/no_logo.png') }}"
                    alt="Vista previa del logo de la marca"
                >
                <span class="brand-image-hint">Haga clic para seleccionar una imagen</span>
            </button>
        </div>

        <div class="form-group">
            {!! Form::file('logo', [
                'class' => 'form-control-file d-none',
                'accept' => 'image/*',
                'id' => 'imageInput',
            ]) !!}
        </div>
    </div>
</div>

<script>
    $(document).on('click', '#imageButton', function () {
        $('#imageInput').click();
    });

    $(document).on('change', '#imageInput', function (event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                $('#imagePreview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });
</script>

@if (session('error') != null)
    <script>
        Swal.fire({
            title: "Ocurrió un error",
            text: "{{ session('error') }}",
            icon: "error",
            draggable: true
        });
    </script>
@endif
