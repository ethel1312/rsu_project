{!! Form::open([
    'route' => 'admin.employees.store',
    'id' => 'frmEmployee',
    'files' => true
]) !!}

    @include('admin.employees.employees.template.form')

    <div class="mt-3 text-right">

        <button type="button"
                class="btn btn-danger"
                data-bs-dismiss="modal">
            <i class="bi bi-x-circle"></i> Cancelar
        </button>

        <button type="submit"
                class="btn btn-primary">
            <i class="bi bi-floppy-fill"></i> Guardar
        </button>

    </div>

{!! Form::close() !!}