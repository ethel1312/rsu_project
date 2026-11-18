@extends('admin.maintenances.layout')
@section('maintenance_heading', 'Horarios de Mantenimiento')
@section('maintenance_new_label', 'Nuevo Horario')
@section('maintenance_create') @json(route('admin.maintenances.schedules.create', $maintenance)) @endsection
@section('maintenance_context')
    <div class="mb-3">
        <a href="{{ route('admin.maintenances.index') }}" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left"></i> Mantenimientos</a>
        <h5>{{ $maintenance->name }}</h5>
        <p class="text-muted">{{ \Carbon\Carbon::parse($maintenance->start_date)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($maintenance->end_date)->format('d/m/Y') }}</p>
    </div>
@endsection
@section('maintenance_columns')
    <th>Día</th><th>Vehículo</th><th>Responsable</th><th>Tipo</th><th>Inicio</th><th>Fin</th><th>Ver / Acciones</th>
@endsection
@section('maintenance_config')
    const columns = [
        { data: 'day_label', name: 'weekday', searchable: false, render: textColumn },
        { data: 'vehicle_label', name: 'vehicle.plate', render: textColumn },
        { data: 'employee_label', name: 'employee.first_name', render: textColumn },
        { data: 'type_label', name: 'type', render: textColumn },
        { data: 'start_time', name: 'start_time' },
        { data: 'end_time', name: 'end_time' },
        { data: 'actions', orderable: false, searchable: false }
    ];
@endsection
