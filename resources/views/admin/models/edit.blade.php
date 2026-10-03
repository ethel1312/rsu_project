
            {!! Form::model($model, ['route' => ['admin.models.update', $model], 'method' => 'put', 'id' => 'frmModel']) !!}
            @include('admin.models.template.form')
            <div class="form-group">
                <button type="submit" class="btn btn-success"><i class="bi bi-pencil-square"></i> Actualizar</button>
                <a href="{{ route('admin.brands.index') }}" class="btn btn-danger"><i class="bi bi-x-circle"></i></i>
                    Cancelar</a>
            </div>
            {!! Form::close() !!}
