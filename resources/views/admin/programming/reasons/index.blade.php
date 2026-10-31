@extends('admin.programming.operations-layout', ['pageTitle' => 'Motivos de cambio'])
@section('operations')
<div class="d-flex justify-content-between mb-3"><p class="text-muted">Catálogo de motivos disponibles para todas las programaciones.</p><a class="btn btn-primary" href="{{ route('admin.programming.reasons.create') }}">+ Nuevo motivo</a></div>
<div class="card operation-card"><div class="table-responsive"><table class="table operation-table mb-0"><thead><tr><th>Nombre</th><th>Descripción</th><th>Creación</th><th>Actualización</th><th>Acciones</th></tr></thead><tbody>
@forelse($reasons as $reason)<tr><td>{{ $reason->name }}</td><td>{{ $reason->description }}</td><td>{{ $reason->created_at->timezone('America/Lima')->format('d/m/Y H:i') }}</td><td>{{ $reason->updated_at->timezone('America/Lima')->format('d/m/Y H:i') }}</td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.programming.reasons.edit', $reason) }}">Editar</a><form method="post" action="{{ route('admin.programming.reasons.destroy', $reason) }}" onsubmit="return confirm('¿Eliminar este motivo del catálogo? El historial anterior se conservará.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Eliminar</button></form></div></td></tr>
@empty<tr><td colspan="5" class="text-center py-5">Aún no hay motivos. Registre uno para realizar cambios.</td></tr>@endforelse
</tbody></table></div></div>{{ $reasons->links() }}
@endsection
