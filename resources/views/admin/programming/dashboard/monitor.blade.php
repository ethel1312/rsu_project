<section class="card operation-card">
    <div class="card-header">Seguimiento GPS e incidencias</div>
    <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Zona y vehículo</th><th>Última ubicación (Lima)</th><th>Incidencias reportadas</th></tr></thead>
        <tbody>@forelse($monitorRuns as $run)
            <tr><td><strong>{{ $run->zone_name }}</strong><br>{{ $run->vehicle_label }} · {{ $run->shift_name }}
                <small class="d-block">{{ $run->finished_at ? 'Finalizado' : 'En curso' }}</small></td>
                <td>@if($run->last_sample_ms)
                    {{ \Carbon\Carbon::createFromTimestampMs($run->last_sample_ms)->timezone('America/Lima')->format('d/m H:i:s') }}
                    @if(!$run->finished_at && now()->timestamp * 1000 - $run->last_sample_ms > 60000)<span class="badge bg-warning text-dark">Sin señal reciente</span>@endif
                @else Sin muestra GPS @endif</td>
                <td>@forelse($incidents->get($run->id, []) as $incident)
                    <div class="mb-2"><strong>{{ $incident->category }}</strong> · {{ \Carbon\Carbon::parse($incident->created_at)->timezone('America/Lima')->format('H:i:s') }}
                        <div>{{ $incident->note }}</div>
                        @if($incident->latitude !== null)
                            <a target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat={{ $incident->latitude }}&amp;mlon={{ $incident->longitude }}#map=17/{{ $incident->latitude }}/{{ $incident->longitude }}">Ubicación del GPS a las {{ \Carbon\Carbon::createFromTimestampMs($incident->location_ms)->timezone('America/Lima')->format('H:i:s') }}</a>
                        @else <small>Sin ubicación GPS al reportar</small> @endif
                    </div>
                @empty Sin incidencias @endforelse</td>
            </tr>
        @empty<tr><td colspan="3" class="text-center">Sin recorridos iniciados en la fecha y turno seleccionados.</td></tr>@endforelse</tbody>
    </table></div>
</section>
