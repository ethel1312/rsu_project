{!! Form::open(['route' => 'admin.brands.store', 'files'=>true, 'id' => 'frmBrand']) !!}       
    @include('admin.brands.template.form')
    <div class="brand-form-actions">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-floppy-fill"></i> Guardar</button>
    </div>
{!! Form::close() !!}
        