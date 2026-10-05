@extends('adminlte::page')
 
@section('title', 'Proyecto RSU')
 
@section('content_header')
    <div class="rsu-title-bar">
        <h1 class="rsu-title">
            <span class="rsu-title-ico"><i class="bi bi-person"></i></span>
            Lista de Personal
        </h1>
 
        <button type="button" id="btnNuevo" class="btn rsu-btn-new">
            <i class="bi bi-plus-circle"></i> Nuevo Personal
        </button>
    </div>
@stop