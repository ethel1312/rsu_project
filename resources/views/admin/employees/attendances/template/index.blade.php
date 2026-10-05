@extends('adminlte::page')

@section('title', 'Proyecto RSU')

@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title">
            <span class="rsu-title-ico"><i class="bi bi-calendar-check"></i></span>
            Lista de Asistencias
        </h1>

        <button type="button" id="btnNuevo" class="btn rsu-btn-new">
            <i class="bi bi-plus-circle"></i> Nueva Asistencia
        </button>
    </div>
@stop