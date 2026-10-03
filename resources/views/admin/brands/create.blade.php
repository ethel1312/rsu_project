
{!! Form::open(['route' => 'admin.brands.store', 'files'=>true, 'id' => 'frmBrand']) !!}       
     @include('admin.brands.template.form')
            <div class="form-group">
                <button type="submit" class="btn btn-success"><i class="bi bi-floppy-fill"></i> Registrar</button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>

            </div>
            {!! Form::close() !!}
        