@extends('adminlte::page')
@section('title', 'Inicio')
@section('content_header')<h1>Bienvenido, {{ auth()->user()->name }}</h1>@stop
@section('content')
    <div class="alert alert-info">Su cuenta está activa, pero aún no tiene permisos para consultar los módulos. Solicite al administrador que configure sus accesos.</div>
@stop
