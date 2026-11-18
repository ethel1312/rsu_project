@extends('admin.maintenances.layout')
@section('maintenance_heading', 'Seguimiento de Mantenimiento')
@section('maintenance_context')
    <div class="mb-3">
        <a href="{{ route('admin.maintenances.schedules.index', $schedule->maintenance) }}" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left"></i> Horarios</a>
        <h5>{{ $schedule->maintenance->name }} · {{ \App\Models\MaintenanceSchedule::WEEKDAYS[$schedule->weekday] }} · {{ $schedule->vehicle->plate }} · {{ $schedule->vehicle->name }}</h5>
        <p class="text-muted">{{ \App\Models\MaintenanceSchedule::TYPES[$schedule->type] }} · {{ substr($schedule->start_time, 0, 5) }} a {{ substr($schedule->end_time, 0, 5) }}</p>
    </div>
@endsection
@section('maintenance_columns')
    <th>Fecha</th><th>Observación</th><th>Imagen</th><th>Estado</th><th>Editar</th>
@endsection
@section('maintenance_config')
    const columns = [
        { data: 'date', name: 'date', render: dateColumn },
        { data: 'observation', name: 'observation', defaultContent: '', render: textColumn },
        { data: 'image', orderable: false, searchable: false },
        { data: 'status_label', name: 'is_completed', searchable: false, render: function (value, type) {
            if (type !== 'display') return value;
            const color = value === 'Realizado' ? 'success' : (value === 'No realizado' ? 'danger' : 'secondary');
            return '<span class="badge bg-' + color + '">' + value + '</span>';
        } },
        { data: 'actions', orderable: false, searchable: false }
    ];
@endsection
