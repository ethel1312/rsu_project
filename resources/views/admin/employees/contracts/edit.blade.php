{!! Form::model($contract, [
    'route' => ['admin.contracts.update', $contract],
    'method' => 'PUT',
    'id' => 'frmContract'
]) !!}

    @include('admin.employees.contracts.template.form')

    <div class="mt-3 text-right">
        <button type="button" class="btn btn-modal-cancelar mr-2" data-bs-dismiss="modal">
            <i class="bi bi-x-circle"></i> Cancelar
        </button>
        <button type="submit" class="btn btn-modal-guardar">
            <i class="bi bi-pencil-square"></i> Actualizar Contrato
        </button>
    </div>

{!! Form::close() !!}