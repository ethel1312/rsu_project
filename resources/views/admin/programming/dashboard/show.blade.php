@extends('admin.programming.operations-layout', ['pageTitle'=>'Detalle de programación #'.$schedule->id])
@section('operations')
<div class="operation-banner mb-4"><h2 class="h4">{{ $schedule->zone_name }} · {{ $schedule->group_name }}</h2>{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} · {{ $schedule->shift_name }} ({{ substr($schedule->shift_start_time,0,5) }}–{{ substr($schedule->shift_end_time,0,5) }}) · {{ $schedule->vehicle_label }}</div>
@if(!$editable)<div class="alert alert-info">Consulta de la programación. Los cambios desde el dashboard están disponibles solo hoy, antes del inicio del recorrido y del horario del turno.</div>@endif
<div class="card operation-card mb-4"><div class="card-header bg-primary text-white">Asistencia del equipo</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Personal</th><th>Rol</th><th>Asistencia</th><th>Reemplazos disponibles</th></tr></thead><tbody>
@foreach($crew as $member)<tr><td>{{ $member['employee']?->full_name ?? 'Personal #'.$member['id'] }}</td><td>{{ $member['role'] === 'driver' ? 'Conductor' : 'Ayudante' }}</td><td><span class="badge {{ $member['present'] ? 'bg-success' : 'bg-danger' }}">{{ $member['present'] ? 'Asistió' : 'Faltante' }}</span></td><td>
@if($editable && !$member['present'])
@if(!empty($candidates[$member['id']]))<button class="btn btn-sm btn-outline-primary choose-replacement" type="button" data-old="{{ $member['id'] }}" data-role="{{ $member['role'] }}">Reemplazar personal</button>@else<span class="text-muted">No hay personal asistente disponible de este tipo.</span>@endif
@elseif($member['present'])<small class="text-muted">No se puede reemplazar: sí asistió.</small>@endif
</td></tr>@endforeach</tbody></table></div></div>
@if($editable)
<form class="card operation-card operation-form" method="post" action="{{ route('admin.programming.dashboard.update', $schedule) }}"><div class="card-header">Aplicar cambio al día</div><div class="card-body">@csrf @method('PUT')<input type="hidden" name="date" value="{{ $date }}"><input type="hidden" id="old_id" name="old_id">
<div class="row"><div class="col-md-6"><label for="type">Recurso a cambiar</label><select class="form-select" id="type" name="type"><option value="shift">Turno actual: {{ $schedule->shift_name }}</option><option value="vehicle">Vehículo actual: {{ $schedule->vehicle_label }}</option>@foreach($crew->where('present',false) as $member) @if(!empty($candidates[$member['id']]))<option value="{{ $member['role'] }}" data-old="{{ $member['id'] }}">{{ $member['role']==='driver'?'Conductor':'Ayudante' }}: {{ $member['employee']?->full_name }}</option>@endif @endforeach</select></div><div class="col-md-6"><label for="new_id">Nuevo recurso</label><select class="form-select" id="new_id" name="new_id" required></select></div></div>
@include('admin.programming.changes.reason')<button class="btn btn-primary">Guardar cambio e historial</button></div></form>
@endif
<div class="d-flex gap-2 mt-3"><a class="btn btn-outline-primary" href="{{ route('admin.programming.dashboard.index',['date'=>$date]) }}">Volver al dashboard</a><a class="btn btn-primary" href="{{ route('admin.programming.schedules.route',['schedule'=>$schedule,'date'=>$date]) }}">Ver / registrar recorrido</a></div>
<div class="card operation-card mt-4"><div class="card-header">Historial de cambios de esta programación</div><div class="card-body">@forelse($schedule->changes()->latest('id')->get() as $change)<p><a href="{{ route('admin.programming.changes.show',$change) }}">#{{ $change->id }} · {{ \App\Models\Programming\ScheduleChange::TYPES[$change->type] }}</a> · {{ $change->reason_name }} {{ $change->reverted_at ? '(Revertido)' : '' }}</p>@empty<p class="text-muted mb-0">Sin cambios registrados.</p>@endforelse</div></div>
@endsection
@section('operation_js')
@if($editable)
@php
$resourceData=['shift'=>$shifts->map(fn($s)=>['id'=>$s->id,'label'=>$s->name.' ('.$s->start_time.'–'.$s->end_time.')']), 'vehicle'=>$vehicles->map(fn($v)=>['id'=>$v->id,'label'=>$v->plate.' · '.$v->name])];
$peopleData=collect($candidates)->map(fn($people)=>collect($people)->map(fn($e)=>['id'=>$e->id,'label'=>$e->full_name]));
@endphp
<script>
const resources=@json($resourceData), people=@json($peopleData), oldIds=@json(['shift'=>$schedule->shift_id,'vehicle'=>$schedule->vehicle_id]);
const type=document.getElementById('type');function updateResources(){const option=type.selectedOptions[0],old=option.dataset.old||oldIds[type.value];document.getElementById('old_id').value=old;const target=document.getElementById('new_id');target.replaceChildren(new Option('Seleccione un reemplazo',''));for(const item of (resources[type.value]||people[old]||[])){if(String(item.id)!==String(old))target.add(new Option(item.label,item.id));}}
type.addEventListener('change',updateResources);updateResources();document.querySelectorAll('.choose-replacement').forEach(button=>button.onclick=()=>{const option=Array.from(type.options).find(o=>o.dataset.old===button.dataset.old);type.selectedIndex=option.index;updateResources();type.scrollIntoView({behavior:'smooth'});});
</script>
@endif
@endsection
