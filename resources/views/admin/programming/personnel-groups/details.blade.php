@extends('adminlte::page')
@section('title', 'Detalle del grupo')
@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
        <h1 class="rsu-title m-0"><span class="rsu-title-ico"><i class="bi bi-people"></i></span> Detalle del grupo</h1>
        <a href="{{ route('admin.programming.personnel-groups.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver a grupos</a>
    </div>
@stop
@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        .group-detail-card { border:1px solid #dfe6f5; border-radius:12px; box-shadow:0 2px 8px rgba(18,32,107,.08); }
        .group-detail-item { min-height:76px; padding:.75rem; border-radius:8px; background:#f8faff; }
        .group-detail-item small { display:block; color:#69758b; margin-bottom:.2rem; }
        .group-detail-item strong { color:#12206b; }
    </style>
@stop
@section('content')
    <div class="card group-detail-card mb-3"><div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-3" style="gap:8px">
            <h3 class="mb-0" style="color:#12206b">{{ $personnelGroup->name }}</h3>
            <span class="badge {{ $personnelGroup->status ? 'badge-success' : 'badge-secondary' }}">{{ $personnelGroup->status ? 'Activo' : 'Inactivo' }}</span>
        </div>
        <div class="row">
            <div class="col-md-3 mb-2"><div class="group-detail-item"><small>Zona</small><strong>{{ $personnelGroup->zone?->name ?? '—' }}</strong></div></div>
            <div class="col-md-3 mb-2"><div class="group-detail-item"><small>Turno</small><strong>{{ $personnelGroup->shift?->name ?? '—' }} @if($personnelGroup->shift) ({{ substr($personnelGroup->shift->start_time,0,5) }}–{{ substr($personnelGroup->shift->end_time,0,5) }}) @endif</strong></div></div>
            <div class="col-md-3 mb-2"><div class="group-detail-item"><small>Vehículo</small><strong>{{ $personnelGroup->vehicle?->plate ?? '—' }} · {{ $personnelGroup->vehicle?->name ?? '' }}</strong></div></div>
            <div class="col-md-3 mb-2"><div class="group-detail-item"><small>Días de trabajo</small><strong>{{ $personnelGroup->days_label ?: '—' }}</strong></div></div>
            <div class="col-md-6 mb-2"><div class="group-detail-item"><small>Conductor</small><strong>{{ $personnelGroup->driver?->full_name ?? '—' }}</strong></div></div>
            <div class="col-md-6 mb-2"><div class="group-detail-item"><small>Ayudantes</small><strong>{{ $personnelGroup->helpers->map->full_name->join(', ') ?: '—' }}</strong></div></div>
        </div>
    </div></div>
    <div class="card group-detail-card mb-3"><div class="card-header"><h5 class="mb-0">Programaciones del grupo</h5></div><div class="card-body">
        @if ($schedules->isEmpty())
            <div class="alert alert-info mb-0">Este grupo todavía no tiene programaciones registradas.</div>
        @else
            <div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Periodo</th><th>Turno</th><th>Vehículo</th><th>Conductor</th><th>Ayudantes</th><th>Estado</th></tr></thead><tbody>
                @foreach ($schedules as $schedule)
                    <tr><td>{{ $schedule->start_date->format('d/m/Y') }} – {{ $schedule->end_date->format('d/m/Y') }}<br><small>{{ collect($schedule->days_of_week)->map(fn($day) => \App\Models\Programming\PersonnelGroup::WEEKDAYS[(int)$day] ?? '')->filter()->join(', ') }}</small></td><td>{{ $schedule->shift_name }}</td><td>{{ $schedule->vehicle_label }}</td><td>{{ $schedule->driver_name }}</td><td>{{ collect($schedule->helper_names)->join(', ') }}</td><td>{{ $schedule->status }}</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </div></div>
    <div class="card group-detail-card"><div class="card-header"><h5 class="mb-0">Historial de cambios individuales y masivos</h5></div><div class="card-body">
        @include('admin.programming.schedules.history', ['schedule' => null, 'histories' => $histories, 'historyTitle' => 'Cambios registrados para este grupo'])
    </div></div>
@stop
