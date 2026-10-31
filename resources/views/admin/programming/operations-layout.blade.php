@extends('adminlte::page')
@section('title', $pageTitle ?? 'Programación RSU')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h1 class="h3 mb-1">{{ $pageTitle ?? 'Programación' }}</h1><p class="text-muted mb-0">Gestión de residuos sólidos urbanos · Control de operaciones</p></div><a href="{{ route('admin.programming.schedules.index') }}" class="btn btn-outline-primary">Ver programaciones</a></div>
@endsection
@section('content')
<div class="pb-4">
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('operations')
</div>
@endsection
@section('css')
<style>.operation-card{border:1px solid #dbe5ef;border-radius:12px;overflow:hidden;box-shadow:0 3px 12px #153b6010}.operation-banner{background:#083b66;color:white;padding:20px;border-radius:12px}.operation-stat{padding:20px;border-radius:10px;color:white;height:100%}.operation-stat strong{font-size:2rem;display:block}.operation-label{font-size:.8rem;text-transform:uppercase;color:#64748b;letter-spacing:.04em}.operation-value{font-size:1.05rem;font-weight:600}.operation-table td,.operation-table th{vertical-align:middle}.operation-form label{font-weight:600;margin-bottom:5px}.operation-form .form-control,.operation-form .form-select{margin-bottom:16px}.operation-form textarea{min-height:90px}.pagination{flex-wrap:wrap}</style>
@yield('operation_css')
@endsection
@section('js')
@yield('operation_js')
@endsection
