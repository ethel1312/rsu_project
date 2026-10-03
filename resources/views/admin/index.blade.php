@extends('adminlte::page')

@section('title', 'Dashboard RSU')

@section('content_header')
@stop

@section('content')
    <!-- Banner Azul Principal -->
    <div class="rounded p-4 mb-4 text-white text-center" style="background-color: #0d47a1;">
        <h4 class="font-weight-bold mb-2">
            <i class="fas fa-star text-warning"></i> ¡Bienvenido al Sistema RSU!
        </h4>
        <p class="mb-1">Sistema de Gestión de Residuos Sólidos Urbanos - Municipalidad José Leonardo Ortiz</p>
        <small><i class="fas fa-info-circle"></i> Utiliza el menú lateral para navegar entre los diferentes módulos del sistema</small>
    </div>

    <!-- Tarjeta de Contenido -->
    <div class="card border-top border-info border-3 shadow-sm">
        <div class="card-header bg-white pb-0 border-bottom-0">
            <h5 class="text-info font-weight-bold mb-0 mt-2">
                <i class="fas fa-info-circle"></i> Sistema de Gestión de Residuos Sólidos Urbanos
            </h5>
            <hr>
        </div>
        
        <div class="card-body pt-0">
            <div class="row">
                <!-- Imagen del Edificio -->
                <div class="col-md-5 text-center mb-3">
                    <div class="position-relative">
                        <img src="{{ asset('img/muni_edificio.jpg') }}" alt="Edificio Municipal" class="img-fluid rounded border w-100" style="max-height: 280px; object-fit: cover;">
                        <span class="badge bg-primary position-absolute px-3 py-2" style="bottom: -15px; left: 50%; transform: translateX(-50%); font-size: 14px;">
                            <i class="fas fa-landmark"></i> Gobierno Local
                        </span>
                    </div>
                </div>
                
                <!-- Detalles y Módulos -->
                <div class="col-md-7 pl-md-4 mt-4 mt-md-0">
                    <h4 class="text-primary font-weight-bold mb-3">
                        <i class="far fa-building"></i> Municipalidad Distrital de José Leonardo Ortiz
                    </h4>
                    <p class="text-muted text-justify mb-4">
                        Sistema integral para la gestión eficiente de los residuos sólidos urbanos del distrito, optimizando rutas de recolección, administrando personal y mejorando la calidad del servicio hacia la comunidad josefina.
                    </p>
                    
                    <!-- Lista de Accesos -->
                    <div class="row text-secondary mb-4 font-weight-bold">
                        <div class="col-sm-6 mb-3">
                            <i class="fas fa-users-cog mr-2 text-secondary"></i> Gestión de Personal
                        </div>
                        <div class="col-sm-6 mb-3">
                            <i class="fas fa-truck mr-2 text-info"></i> Control de Vehículos
                        </div>
                        <div class="col-sm-6 mb-3">
                            <i class="fas fa-route mr-2 text-warning"></i> Planificación de Rutas
                        </div>
                        <div class="col-sm-6 mb-3">
                            <i class="fas fa-clock mr-2 text-success"></i> Seguimiento de Asistencia
                        </div>
                        <div class="col-sm-6 mb-3">
                            <i class="fas fa-file-contract mr-2 text-danger"></i> Gestión de Contratos
                        </div>
                        <div class="col-sm-6 mb-3">
                            <i class="fas fa-plane-departure mr-2" style="color: #6f42c1;"></i> Control de Vacaciones
                        </div>
                    </div>
                    
                    <!-- Badges Inferiores -->
                    <div class="d-flex gap-2">
                        <span class="badge bg-success px-3 py-2 mr-2"><i class="fas fa-leaf"></i> Eco-Friendly</span>
                        <span class="badge bg-primary px-3 py-2 mr-2"><i class="fas fa-recycle"></i> Sostenible</span>
                        <span class="badge bg-warning text-dark px-3 py-2"><i class="fas fa-heart"></i> Responsable</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop