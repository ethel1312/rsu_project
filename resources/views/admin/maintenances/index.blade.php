@extends('adminlte::page')

@php
    $title = match ($section) {
        'periods' => 'Mantenimientos programados',
        'schedules' => $maintenance->name.' / Horarios',
        'days' => $maintenance->name.' / Días programados',
    };
    $canCreate = auth()->user()->hasPermission('maintenances.create');
    $createUrl = match ($section) {
        'periods' => route('admin.maintenances.create'),
        'schedules' => route('admin.maintenances.schedules.create', $maintenance),
        'days' => null,
    };
    $createLabel = $section === 'periods' ? 'Nuevo mantenimiento' : 'Agregar horario';
@endphp

@section('title', $title)

@section('content_header')
    <nav aria-label="Ruta de navegación">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('admin.home') }}" aria-label="Panel"><i class="bi bi-house"></i></a></li>
            <li class="breadcrumb-item">Gestión de vehículos</li>
            @if ($section === 'periods')
                <li class="breadcrumb-item active" aria-current="page">Mantenimiento</li>
            @else
                <li class="breadcrumb-item"><a href="{{ route('admin.maintenances.index') }}">Mantenimiento</a></li>
                @if ($section === 'schedules')
                    <li class="breadcrumb-item active" aria-current="page">{{ $maintenance->name }}</li>
                @else
                    <li class="breadcrumb-item"><a href="{{ route('admin.maintenances.schedules.index', $maintenance) }}">{{ $maintenance->name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ \App\Models\MaintenanceSchedule::WEEKDAYS[$schedule->weekday] }} / {{ $schedule->vehicle->plate }}</li>
                @endif
            @endif
        </ol>
    </nav>
    <div class="rsu-title-bar">
        <h1 class="rsu-title">
            <span class="rsu-title-ico"><i class="bi {{ $section === 'periods' ? 'bi-wrench' : 'bi-calendar-week' }}"></i></span>
            {{ $title }}
        </h1>
        @if ($createUrl && $canCreate)
            <button type="button" class="btn rsu-btn-new" data-maintenance-form="{{ $createUrl }}" data-title="{{ $createLabel }}">
                <i class="bi bi-plus-circle"></i> {{ $createLabel }}
            </button>
        @endif
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/custom-listados.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        .maintenance-table th { white-space: nowrap; }
        .maintenance-table td { vertical-align: middle; }
        .maintenance-evidence { width: 68px; height: 48px; object-fit: cover; border-radius: .35rem; }
    </style>
@stop

@section('content')
    <div class="card shadow-sm">
        <div class="card-body" id="maintenance-list">
            @include('admin.maintenances.table')
        </div>
    </div>

    <div class="modal fade" id="maintenance-modal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="maintenance-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="maintenance-modal-title"></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script src="{{ asset('js/maintenances.js') }}"></script>
@stop
