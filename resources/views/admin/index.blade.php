@extends('adminlte::page')

@section('title', 'Dashboard RSU')

@section('css')
<style>
    :root {
        --rsu-navy: #12206b;
        --rsu-sky: #5bb8f5;
        --rsu-soft: #eef3ff;
        --rsu-gray: #6b7388;
        --rsu-ink: #3d4455;
    }

    /* Espacio superior para que el banner no quede pegado a la barra */
    .rsu-page { padding-top: 24px; padding-bottom: 32px; }

    .rsu-banner {
        background-color: var(--rsu-navy);
        border-radius: 14px;
    }

    .rsu-card {
        border: 1px solid #dfe6f5;
        border-top: 3px solid var(--rsu-sky);
        border-radius: 14px;
    }

    /* Ícono dentro de un cuadro suave, como en la referencia */
    .rsu-tile {
        flex: 0 0 38px;
        height: 38px;
        border-radius: 10px;
        background: var(--rsu-soft);
        color: var(--rsu-navy);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
    .rsu-tile.is-sky  { color: var(--rsu-sky); }
    .rsu-tile.is-gray { color: var(--rsu-gray); }

    .rsu-item { display: flex; align-items: center; gap: 12px; color: var(--rsu-ink); font-weight: 600; text-decoration: none; }
    a.rsu-item:hover { color: var(--rsu-navy); }
    a.rsu-item:hover .rsu-tile { background: var(--rsu-sky); color: #fff; }

    /* Etiquetas en tonos sobrios */
    .rsu-pill { padding: .45rem .9rem; border-radius: 999px; font-size: .8rem; font-weight: 600; }
    .rsu-pill.navy { background: var(--rsu-navy); color: #fff; }
    .rsu-pill.eco  { background: #e3f1e8; color: #2f6f4a; }   /* verde suave */
    .rsu-pill.sost { background: #dff1f5; color: #1f6f86; }   /* turquesa suave */
    .rsu-pill.resp { background: #fbe6ea; color: #a8445a; }   /* rosa suave */
</style>
@endsection

@section('content')
<div class="rsu-page">

    <!-- Banner principal -->
    <div class="rsu-banner p-4 mb-4 text-white text-center">
        <h4 class="fw-bold mb-2">
            <i class="bi bi-star" style="color: var(--rsu-sky);"></i> ¡Bienvenido al Sistema RSU!
        </h4>
        <p class="mb-1">Sistema de Gestión de Residuos Sólidos Urbanos - Municipalidad José Leonardo Ortiz</p>
        <small style="opacity: .85;"><i class="bi bi-info-circle"></i> Utiliza el menú lateral para navegar entre los diferentes módulos del sistema</small>
    </div>

    <!-- Tarjeta de contenido -->
    <div class="card rsu-card shadow-sm">
        <div class="card-header bg-white pb-0 border-bottom-0">
            <h5 class="fw-bold mb-0 mt-2" style="color: var(--rsu-navy);">
                <i class="bi bi-info-circle" style="color: var(--rsu-sky);"></i> Sistema de Gestión de Residuos Sólidos Urbanos
            </h5>
            <hr>
        </div>

        <div class="card-body pt-0">
            <div class="row align-items-center">
                <!-- Imagen del edificio -->
                <div class="col-md-5 text-center mb-4 mb-md-0">
                    <div class="position-relative">
                        <img src="{{ asset('img/muni_edificio.jpg') }}" alt="Edificio Municipal"
                             class="img-fluid rounded w-100" style="max-height: 280px; object-fit: cover;">
                        <span class="rsu-pill navy position-absolute"
                              style="bottom: -15px; left: 50%; transform: translateX(-50%); white-space: nowrap;">
                            <i class="bi bi-bank"></i> Gobierno Local
                        </span>
                    </div>
                </div>

                <!-- Detalles y módulos -->
                <div class="col-md-7 ps-md-4 mt-4 mt-md-0">
                    <h4 class="fw-bold mb-3" style="color: var(--rsu-navy);">
                        <i class="bi bi-building"></i> Municipalidad Distrital de José Leonardo Ortiz
                    </h4>
                    <p class="mb-4" style="text-align: justify; color: var(--rsu-gray);">
                        Sistema integral para la gestión eficiente de los residuos sólidos urbanos del distrito, optimizando rutas de recolección, administrando personal y mejorando la calidad del servicio hacia la comunidad josefina.
                    </p>

                    <!-- Accesos -->
                    <div class="row mb-4">
                        <div class="col-sm-6 mb-3">
                            <div class="rsu-item">
                                <a href="{{ route('admin.employees.index') }}" class="rsu-item">
                                    <span class="rsu-tile"><i class="bi bi-people"></i></span> Gestión de Personal
                                </a>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <a href="{{ route('admin.vehicles.index') }}" class="rsu-item">
                                <span class="rsu-tile is-sky"><i class="bi bi-truck"></i></span> Control de Vehículos
                            </a>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="rsu-item">
                                <span class="rsu-tile is-gray"><i class="bi bi-signpost-split"></i></span> Planificación de Rutas
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="rsu-item">
                                <span class="rsu-tile"><i class="bi bi-clock"></i></span> Seguimiento de Asistencia
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="rsu-item">
                                <span class="rsu-tile is-sky"><i class="bi bi-file-earmark-text"></i></span> Gestión de Contratos
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="rsu-item">
                                <span class="rsu-tile is-gray"><i class="bi bi-umbrella"></i></span> Control de Vacaciones
                            </div>
                        </div>
                    </div>

                    <!-- Etiquetas inferiores -->
                    <div class="d-flex flex-wrap gap-2">
                        <span class="rsu-pill eco"><i class="bi bi-leaf"></i> Eco-Friendly</span>
                        <span class="rsu-pill sost"><i class="bi bi-recycle"></i> Sostenible</span>
                        <span class="rsu-pill resp"><i class="bi bi-heart"></i> Responsable</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@stop