
            {!! Form::model($brand, ['route' => ['admin.brands.update', $brand], 'method' => 'put','files'=>true, 'id' => 'frmBrand']) !!}
            @include('admin.brands.template.form')
            <div class="brand-form-actions">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Actualizar</button>
            </div>
            {!! Form::close() !!}
