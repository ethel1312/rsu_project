<div class="row">

    {{-- ==================== DATOS DEL PERSONAL ==================== --}}
    <div class="col-md-8">

        {{-- Fila 1 --}}
        <div class="row">

            <div class="col-md-6 mb-3">
                {!! Form::label('dni', 'DNI *') !!}
                {!! Form::text('dni', null, [
                    'class' => 'form-control',
                    'placeholder' => '12345678',
                    'maxlength' => 8,
                    'required'
                ]) !!}
                <small class="text-muted">8 dígitos únicos</small>
            </div>

            <div class="col-md-6 mb-3">
                {!! Form::label('employee_type_id', 'Tipo de Personal *') !!}
                {!! Form::select('employee_type_id', $employeeTypes, null, [
                    'class' => 'form-select',
                    'placeholder' => 'Seleccione un tipo',
                    'required'
                ]) !!}
            </div>

        </div>


        {{-- Fila 2 --}}
        <div class="row">

            <div class="col-md-6 mb-3">
                {!! Form::label('first_name', 'Nombres *') !!}
                {!! Form::text('first_name', null, [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese los nombres',
                    'required'
                ]) !!}
            </div>

            <div class="col-md-6 mb-3">
                {!! Form::label('last_name', 'Apellidos *') !!}
                {!! Form::text('last_name', null, [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese los apellidos',
                    'required'
                ]) !!}
            </div>

        </div>


        {{-- Fila 3 --}}
        <div class="row">

            <div class="col-md-6 mb-3">
                {!! Form::label('birth_date', 'Fecha de Nacimiento *') !!}
                {!! Form::date('birth_date', null, [
                    'class' => 'form-control',
                    'required'
                ]) !!}
                <small class="text-muted">Mayor de 18 años</small>
            </div>

            <div class="col-md-6 mb-3">
                {{-- Espacio para mantener la distribución --}}
            </div>

        </div>


        {{-- Fila 4 --}}
        <div class="row">

            <div class="col-md-6 mb-3">
                {!! Form::label('phone', 'Teléfono') !!}
                {!! Form::text('phone', null, [
                    'class' => 'form-control',
                    'placeholder' => '987654321',
                    'maxlength' => 9
                ]) !!}
                <small class="text-muted">Número de teléfono (opcional)</small>
            </div>

            <div class="col-md-6 mb-3">
                {!! Form::label('email', 'Email *') !!}
                {!! Form::email('email', null, [
                    'class' => 'form-control',
                    'placeholder' => 'personal@ejemplo.com',
                    'required'
                ]) !!}
            </div>

        </div>


        {{-- Fila 5 --}}
        <div class="row">

            <div class="col-md-6 mb-3">
                {!! Form::label('status', 'Estado *') !!}
                {!! Form::select('status', [
                    1 => 'Activo',
                    0 => 'Inactivo'
                ], null, [
                    'class' => 'form-select',
                    'required'
                ]) !!}
            </div>

            <div class="col-md-6 mb-3">
                {!! Form::label('password', 'Contraseña') !!}

                <div class="input-group">

                    {!! Form::password('password', [
                        'class' => 'form-control',
                        'id' => 'password',
                        'placeholder' => isset($employee) && $employee->exists
                            ? 'Dejar vacío para mantener la actual'
                            : 'Mínimo 6 caracteres',
                        'minlength' => 6
                    ]) !!}

                    <button type="button"
                            class="btn btn-outline-secondary"
                            id="btnMostrarPassword">
                        <i class="bi bi-eye"></i>
                    </button>

                </div>

                <small class="text-muted">
                    @if(isset($employee) && $employee->exists)
                        Déjelo vacío para mantener la contraseña actual.
                    @else
                        Mínimo 6 caracteres.
                    @endif
                </small>
            </div>

        </div>


        {{-- Fila 6 --}}
        <div class="row">

            <div class="col-md-12 mb-3">
                {!! Form::label('address', 'Dirección *') !!}
                {!! Form::text('address', null, [
                    'class' => 'form-control',
                    'placeholder' => 'Av. Principal 123, Distrito, Ciudad',
                    'required'
                ]) !!}
                <small class="text-muted">
                    Dirección completa (mínimo 10 caracteres)
                </small>
            </div>

        </div>

    </div>


    {{-- ==================== FOTO DE PERFIL ==================== --}}
    <div class="col-md-4">

        <div class="form-group mb-3">

            {!! Form::label('image_path', 'Foto de Perfil') !!}

            <label for="image_path" style="width: 100%; cursor: pointer;">

                <div id="imagePreviewContainer"
                    style="
                        width: 100%;
                        height: 305px;
                        background: #f1f1f1;
                        border-radius: 8px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        overflow: hidden;
                    ">

                    @if(isset($employee) && $employee->image_path)

                        <img id="imagePreview"
                            src="{{ asset('storage/' . $employee->image_path) }}"
                            alt="Foto de Perfil"
                            style="
                                display: block;
                                width: 100%;
                                height: 100%;
                                object-fit: contain;
                            ">

                        <div id="imagePlaceholder"
                            style="display: none; text-align: center; color: #aaa;">
                            <i class="bi bi-image" style="font-size: 90px;"></i>
                        </div>

                    @else

                        <div id="imagePlaceholder"
                            style="text-align: center; color: #aaa;">

                            <i class="bi bi-image"
                                style="font-size: 90px;">
                            </i>

                        </div>

                        <img id="imagePreview"
                            src=""
                            alt="Vista previa"
                            style="
                                display: none;
                                width: 100%;
                                height: 100%;
                                object-fit: contain;
                            ">

                    @endif

                </div>

            </label>

            {!! Form::file('image_path', [
                'id' => 'image_path',
                'accept' => 'image/jpeg,image/png,image/jpg',
                'style' => 'display:none;'
            ]) !!}

            <div class="text-center mt-2">
                <small class="text-muted">
                    Haga clic para seleccionar una imagen
                </small>
            </div>

        </div>

    </div>


{{-- Vista previa de imagen --}}
<script>

    $(document).off('change', '#image_path').on('change', '#image_path', function(e) {

        const file = e.target.files[0];

        if (!file) {
            return;
        }

        const reader = new FileReader();

        reader.onload = function(e) {

            $('#imagePreview')
                .attr('src', e.target.result)
                .show();

            $('#imagePlaceholder').hide();
        };

        reader.readAsDataURL(file);

    });

    $(document).on('click', '#btnMostrarPassword', function() {

        const password = $('#password');
        const icon = $(this).find('i');

        if (password.attr('type') === 'password') {

            password.attr('type', 'text');

            icon.removeClass('bi-eye')
                .addClass('bi-eye-slash');

        } else {

            password.attr('type', 'password');

            icon.removeClass('bi-eye-slash')
                .addClass('bi-eye');
        }
    });

</script>