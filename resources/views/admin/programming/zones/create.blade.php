{!! Form::open([
    'route' => 'admin.programming.zones.store',
    'id' => 'frmZona',
    'method' => 'POST'
]) !!}
    @include('admin.programming.zones.template.form')
    <div class="mt-4 text-right">
        <button type="button" class="btn btn-modal-cancelar mr-2" data-bs-dismiss="modal">
            <i class="bi bi-x-circle"></i> Cancelar
        </button>
        <button type="submit" class="btn btn-modal-guardar">
            <i class="bi bi-floppy-fill"></i> Guardar
        </button>
    </div>
{!! Form::close() !!}