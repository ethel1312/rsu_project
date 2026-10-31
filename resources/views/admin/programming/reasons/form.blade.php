@extends('admin.programming.operations-layout', ['pageTitle' => $reason->exists ? 'Editar motivo' : 'Nuevo motivo'])
@section('operations')
<div class="card operation-card mx-auto" style="max-width:700px"><div class="card-header bg-primary text-white">Información del motivo</div><form class="card-body operation-form" method="post" action="{{ $reason->exists ? route('admin.programming.reasons.update', $reason) : route('admin.programming.reasons.store') }}">@csrf @if($reason->exists) @method('PUT') @endif
<label for="name">Nombre *</label><input class="form-control" id="name" name="name" required maxlength="150" value="{{ old('name', $reason->name) }}">
<label for="description">Descripción *</label><textarea class="form-control" id="description" name="description" required maxlength="2000">{{ old('description', $reason->description) }}</textarea>
<div class="d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="{{ route('admin.programming.reasons.index') }}">Cancelar</a><button class="btn btn-primary">Guardar motivo</button></div></form></div>
@endsection
