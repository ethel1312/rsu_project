<div class="row">
{{-- DATOS DE LA MARCA --}}
<div class="col-8">

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
            'rows' => 3,
        ]) !!}
    </div>

</div>


{{-- IMAGEN --}}
<div class="col-4">

    <div class="form-group">

        <div
            id="imageButton"
            style="width: 100%; text-align: center; padding: 10px; cursor: pointer;"
        >

            <img
                id="imagePreview"
                src="{{ isset($brand) && $brand->logo ? asset($brand->logo) : asset('storage/images/no_logo.png') }}"
                alt="Vista previa de la imagen"
                style="width: 100%; height: 180px; object-fit: contain; cursor: pointer;"
            >

            <p style="font-size: 12px; margin-top: 5px;">
                Haga clic para seleccionar una imagen
            </p>

        </div>

    </div>


    {{-- INPUT FILE OCULTO --}}
    <div class="form-group">

        {!! Form::file('logo', [
            'class' => 'form-control-file d-none',
            'accept' => 'image/*',
            'id' => 'imageInput',
        ]) !!}

    </div>

</div>

</div> <script> /* |-------------------------------------------------------------------------- | SELECCIONAR IMAGEN |-------------------------------------------------------------------------- */ $(document).on('click', '#imageButton', function () { $('#imageInput').click(); }); /* |-------------------------------------------------------------------------- | VISTA PREVIA DE IMAGEN |-------------------------------------------------------------------------- */ $(document).on('change', '#imageInput', function (event) { const file = event.target.files[0]; if (file) { const reader = new FileReader(); reader.onload = function (e) { $('#imagePreview') .attr('src', e.target.result) .show(); }; reader.readAsDataURL(file); } }); </script>

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