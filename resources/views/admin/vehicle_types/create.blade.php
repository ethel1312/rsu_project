{!! Form::open(['route' => 'admin.vehicle_types.store', 'id' => 'frmVehicleType']) !!}
    @include('admin.vehicle_types.template.form')
    <div class="mt-3 text-right">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-floppy-fill"></i> Guardar</button>
    </div>
{!! Form::close() !!}