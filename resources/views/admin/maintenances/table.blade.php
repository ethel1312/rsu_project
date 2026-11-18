@php
    $canEdit = auth()->user()->hasPermission('maintenances.edit');
    $canDelete = auth()->user()->hasPermission('maintenances.delete');
    $columns = match ($section) {
        'periods' => ['Nombre', 'Fecha de inicio', 'Fecha de fin', 'Horarios', 'Acciones'],
        'schedules' => ['Día', 'Vehículo', 'Responsable', 'Tipo', 'Hora de inicio', 'Hora de fin', 'Ver días', 'Editar', 'Eliminar'],
        'days' => ['Fecha', 'Observación', 'Imagen', 'Estado', 'Editar'],
    };
@endphp

@if ($rows->currentPage() > $rows->lastPage())
    <span data-last-page="{{ $rows->url($rows->lastPage()) }}"></span>
@endif
<div class="table-responsive">
    <table class="table table-striped table-hover maintenance-table mb-0">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th scope="col">{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @if ($section === 'periods')
                    <tr>
                        <th scope="row">{{ $row->name }}</th>
                        <td>{{ $row->start_date->format('d/m/Y') }}</td>
                        <td>{{ $row->end_date->format('d/m/Y') }}</td>
                        <td><span class="badge text-bg-info">{{ $row->schedules_count }} {{ $row->schedules_count === 1 ? 'horario' : 'horarios' }}</span></td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <a class="btn btn-sm btn-primary" href="{{ route('admin.maintenances.schedules.index', $row) }}">
                                    <i class="bi bi-calendar-week"></i> Ver horarios
                                </a>
                                @if ($canEdit)
                                    @include('admin.maintenances.edit-button', ['url' => route('admin.maintenances.edit', $row), 'label' => 'Editar mantenimiento'])
                                @endif
                                @if ($canDelete)
                                    @include('admin.maintenances.delete-button', [
                                        'url' => route('admin.maintenances.destroy', $row),
                                        'label' => 'Eliminar mantenimiento',
                                        'confirmation' => 'Se eliminará el período seleccionado. El período debe estar sin horarios.',
                                    ])
                                @endif
                            </div>
                        </td>
                    </tr>
                @elseif ($section === 'schedules')
                    <tr>
                        <th scope="row">{{ \App\Models\MaintenanceSchedule::WEEKDAYS[$row->weekday] }}</th>
                        <td>{{ $row->vehicle->plate }} · {{ $row->vehicle->name }}</td>
                        <td>{{ $row->employee->full_name }}</td>
                        <td>{{ \App\Models\MaintenanceSchedule::TYPES[$row->type] }}</td>
                        <td>{{ substr($row->start_time, 0, 5) }}</td>
                        <td>{{ substr($row->end_time, 0, 5) }}</td>
                        <td>
                            <a class="btn btn-sm btn-primary" href="{{ route('admin.maintenances.schedules.days.index', [$maintenance, $row]) }}" aria-label="Ver días" title="Ver días">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                        <td>
                            @if ($canEdit)
                                @include('admin.maintenances.edit-button', ['url' => route('admin.maintenances.schedules.edit', [$maintenance, $row]), 'label' => 'Editar horario'])
                            @endif
                        </td>
                        <td>
                            @if ($canDelete)
                                @include('admin.maintenances.delete-button', [
                                    'url' => route('admin.maintenances.schedules.destroy', [$maintenance, $row]),
                                    'label' => 'Eliminar horario',
                                    'confirmation' => 'Se eliminarán el horario, todos sus días generados y sus evidencias.',
                                ])
                            @endif
                        </td>
                    </tr>
                @else
                    @php
                        $badge = ['pending' => 'warning', 'done' => 'success', 'not_done' => 'danger'][$row->status];
                        $imageUrl = $row->image_path ? route('admin.maintenances.schedules.days.image', [$maintenance, $schedule, $row]) : null;
                    @endphp
                    <tr>
                        <th scope="row">{{ $row->date->format('d/m/Y') }}</th>
                        <td class="text-break">{{ $row->observation ?? '—' }}</td>
                        <td>
                            @if ($imageUrl)
                                <a href="{{ $imageUrl }}" target="_blank" rel="noopener" aria-label="Ver evidencia del {{ $row->date->format('d/m/Y') }}">
                                    <img src="{{ $imageUrl }}?v={{ $row->updated_at->timestamp }}" class="maintenance-evidence" alt="Evidencia del mantenimiento" loading="lazy">
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td><span class="badge text-bg-{{ $badge }}">{{ \App\Models\MaintenanceDay::STATUSES[$row->status] }}</span></td>
                        <td>
                            @if ($canEdit)
                                @include('admin.maintenances.edit-button', ['url' => route('admin.maintenances.schedules.days.edit', [$maintenance, $schedule, $row]), 'label' => 'Actualizar día de mantenimiento'])
                            @endif
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="{{ count($columns) }}" class="text-center text-body-secondary py-4">No hay registros.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($rows->hasPages())
    <div class="mt-3">{{ $rows->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
@endif
