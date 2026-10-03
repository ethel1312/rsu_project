
            {!! Form::model($brand, ['route' => ['admin.brands.update', $brand], 'method' => 'put','files'=>true, 'id' => 'frmBrand']) !!}
            @include('admin.brands.template.form')
            <div class="form-group">
                <button type="submit" class="btn btn-success"><i class="bi bi-pencil-square"></i> Actualizar</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>                    Cancelar</a>
            </div>
            {!! Form::close() !!}
