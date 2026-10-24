<div class="schedule-history">
    @if ($schedule)
        <p class="mb-3"><strong>{{ $schedule->group_name }}</strong> · {{ $schedule->start_date->format('d/m/Y') }} – {{ $schedule->end_date->format('d/m/Y') }}</p>
    @else
        <p class="mb-3"><strong>Últimos cambios registrados</strong></p>
    @endif
    @if ($histories->isEmpty())
        <div class="alert alert-info mb-0">Aún no hay cambios registrados.</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Fecha</th><th>Programación</th><th>Acción</th><th>Usuario</th><th>Detalle</th></tr></thead>
                <tbody>
                    @foreach ($histories as $history)
                        @php
                            $labels = ['created' => 'Creada', 'created_mass' => 'Creada en lote', 'updated' => 'Modificada', 'finished' => 'Finalizada', 'deleted' => 'Eliminada'];
                            $before = $history->before_data ?? [];
                            $after = $history->after_data ?? [];
                            $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
                            $changes = [];
                            foreach ($keys as $key) {
                                if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                                    $changes[] = $key.': '.($before[$key] ?? '—').' → '.($after[$key] ?? '—');
                                }
                            }
                        @endphp
                        <tr>
                            <td class="text-nowrap">{{ $history->created_at?->timezone('America/Lima')->format('d/m/Y H:i') }}</td>
                            <td>{{ $history->schedule_label }}</td>
                            <td>{{ $labels[$history->action] ?? ucfirst($history->action) }}</td>
                            <td>{{ $history->actor?->name ?? 'Usuario eliminado' }}</td>
                            <td>
                                {{ $history->notes }}
                                @if ($changes !== [])
                                    <details class="mt-1"><summary>Ver cambios</summary><ul class="mb-0 pl-3">@foreach ($changes as $change)<li>{{ $change }}</li>@endforeach</ul></details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
