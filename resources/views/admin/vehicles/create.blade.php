{!! Form::model($vehicle, ['route' => ['admin.vehicles.update', $vehicle->id], 'method' => 'PUT', 'id' => 'frmVehicle']) !!}
    @include('admin.vehicles.template.form')
    <div class="mt-3 text-right">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Actualizar Vehículo</button>
    </div>
{!! Form::close() !!}