@extends('admin.maintenances.layout')
@section('maintenance_heading', 'Lista de Mantenimientos')
@section('maintenance_new_label', 'Nuevo Mantenimiento')
@section('maintenance_create') @json(route('admin.maintenances.create')) @endsection
@section('maintenance_columns')
    <th>Nombre</th><th>Inicio</th><th>Fin</th><th>Horarios / Acciones</th>
@endsection
@section('maintenance_config')
    const columns = [
        { data: 'name', name: 'name', render: textColumn },
        { data: 'start_date', name: 'start_date', render: dateColumn },
        { data: 'end_date', name: 'end_date', render: dateColumn },
        { data: 'actions', orderable: false, searchable: false }
    ];
@endsection
