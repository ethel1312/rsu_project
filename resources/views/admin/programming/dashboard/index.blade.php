@extends('admin.programming.operations-layout', ['pageTitle'=>'Dashboard diario'])
@section('operations')
<div class="row g-3 mb-4">
@foreach(['all' => [$total, 'Total de programaciones', 'primary'], 'complete' => [$complete, 'Programaciones completas', 'success'], 'incomplete' => [$total - $complete, 'Programaciones incompletas', 'danger'], 'missing' => [$missingCount, 'Personal faltante', 'warning text-dark']] as $key => [$count, $label, $color])
    <div class="col-md-3">
        <a href="{{ route('admin.programming.dashboard.index', array_filter(['date' => $date, 'shift_id' => request('shift_id'), 'filter' => $key])) }}"
           class="operation-stat bg-{{ $color }} d-block h-100 text-decoration-none {{ $key === 'missing' ? 'text-dark' : 'text-white' }} {{ $filter === $key ? 'border border-3 border-dark shadow' : '' }}"
           @if($filter === $key) aria-current="page" @endif><strong>{{ $count }}</strong>{{ $label }}</a>
    </div>
@endforeach
</div>
<p class="text-muted small">Completa: todo el equipo registró asistencia de entrada para la fecha y el turno de la programación. El estado del recorrido se muestra por separado.</p>
<form method="get" class="card card-body operation-card mb-4"><input type="hidden" name="filter" value="{{ $filter }}"><div class="row g-3"><div class="col-md-4"><label for="date">Fecha</label><input type="date" name="date" id="date" class="form-control" value="{{ $date }}" required></div><div class="col-md-4"><label for="shift_id">Turno</label><select name="shift_id" id="shift_id" class="form-select"><option value="">Todos los turnos</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}" @selected(request('shift_id') == $shift->id)>{{ $shift->name }}</option>@endforeach</select></div><div class="col-md-4 d-flex align-items-end gap-2"><button class="btn btn-primary">Buscar / actualizar</button><a href="{{ route('admin.programming.dashboard.index') }}" class="btn btn-outline-secondary">Hoy</a></div></div></form>
@if($filter === 'missing')
<div class="card operation-card mb-4"><div class="card-header">Personal sin asistencia en el día y turno seleccionados</div>
<div class="table-responsive"><table class="table mb-0"><thead><tr><th>Personal</th><th>DNI</th><th>Turnos / Grupos</th></tr></thead><tbody>
@forelse($missingPersonnel as $id => $assignments)
<tr><td>{{ $missingEmployees->get($id)?->full_name ?? 'Personal #'.$id }}</td><td>{{ $missingEmployees->get($id)?->dni ?? '—' }}</td>
<td>@foreach($assignments as $assignment)<div>{{ $assignment['shift'] }} · {{ $assignment['group'] }}</div>@endforeach</td></tr>
@empty<tr><td colspan="3" class="text-center py-4">No hay personal faltante.</td></tr>@endforelse
</tbody></table></div></div>
@else
<div class="row g-3 mb-4">@forelse($cards as $card) @php($schedule=$card['schedule'])
<div class="col-xl-4 col-md-6"><div class="card operation-card h-100"><div class="card-header {{ count($card['missing']) ? 'bg-danger' : 'bg-success' }} text-white"><i class="bi bi-geo-alt"></i> {{ $schedule->zone_name }}</div><div class="card-body"><div class="row mb-3"><div class="col"><div class="operation-label">Turno</div>{{ $schedule->shift_name }}</div><div class="col"><div class="operation-label">Grupo</div>{{ $schedule->group_name }}</div></div><div class="operation-label">Vehículo</div><p>{{ $schedule->vehicle_label }}</p><span class="badge bg-success">{{ $card['present'] }} presentes</span> <span class="badge bg-danger">{{ count($card['missing']) }} faltantes</span><p class="mt-3 mb-0"><strong>Recorrido:</strong> {{ $card['run']?->finished_at || $schedule->status === 'Finalizada' ? 'Finalizado' : ($card['run']?->started_at ? 'En curso' : 'Sin iniciar') }}</p></div><div class="card-footer d-flex gap-2"><button class="btn btn-primary flex-fill dashboard-detail-button" type="button" data-url="{{ route('admin.programming.dashboard.show', ['schedule'=>$schedule,'date'=>$date]) }}">Ver detalles</button><a class="btn btn-outline-primary" href="{{ route('admin.programming.schedules.route', ['schedule'=>$schedule,'date'=>$date]) }}">{{ $card['run']?->finished_at ? 'Ver recorrido' : 'Recorrido' }}</a></div></div></div>
@empty<div class="col-12"><div class="alert alert-info">No hay programaciones que coincidan con este filtro, fecha y turno.</div></div>@endforelse</div>
@endif
<div class="card operation-card"><div class="card-header">Cambios en las programaciones del día <span class="badge bg-primary">{{ $changes->count() }}</span></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Programación</th><th>Cambio</th><th>Motivo</th><th>Usuario</th><th>Estado</th><th></th></tr></thead><tbody>@forelse($changes as $change)<tr><td>#{{ $change->programming_schedule_id }}</td><td>{{ \App\Models\Programming\ScheduleChange::TYPES[$change->type] }}</td><td>{{ $change->reason_name }}</td><td>{{ $change->actor?->name }}</td><td>{{ $change->reverted_at ? 'Revertido' : 'Aplicado' }}</td><td><a href="{{ route('admin.programming.changes.show',$change) }}">Ver detalle</a></td></tr>@empty<tr><td colspan="6" class="text-center py-4">No hay cambios registrados para este día.</td></tr>@endforelse</tbody></table></div></div>
<div class="modal fade" id="dashboardDetailsModal" tabindex="-1" role="dialog" aria-labelledby="dashboardDetailsModalTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable" role="document"><div class="modal-content" id="dashboardDetailsContent"><div class="modal-body text-center py-5"><span class="spinner-border text-primary" role="status"></span><span class="sr-only">Cargando detalle…</span></div></div></div></div>
@endsection

@section('operation_js')
<script>
$(document).on('click', '.dashboard-detail-button', async function () {
    ++candidateRequestVersion;
    const modal = $('#dashboardDetailsModal');
    const content = $('#dashboardDetailsContent');
    content.html('<div class="modal-body text-center py-5"><span class="spinner-border text-primary" role="status"></span><span class="sr-only">Cargando detalle…</span></div>');
    bootstrap.Modal.getOrCreateInstance(modal[0]).show();

    try {
        const response = await fetch(this.dataset.url, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}});
        if (!response.ok) throw new Error('No se pudo cargar el detalle de la programación.');
        content.html(await response.text());
    } catch (error) {
        content.html('<div class="modal-header"><h5 class="modal-title">Detalle de programación</h5><button type="button" class="close dashboard-modal-dismiss" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="alert alert-danger mb-0"></div></div>');
        content.find('.alert').text(error.message);
    }
});

let candidateRequestVersion = 0;
$(document).on('change', '#dashboardNewShift', async function () {
    const version = ++candidateRequestVersion;
    const form = document.getElementById('dashboardReplacementForm');
    const selects = Array.from(form.querySelectorAll('.dashboard-person-select'));
    const save = document.querySelector('[form="dashboardReplacementForm"][type="submit"]');
    const alert = form.querySelector('.dashboard-form-alert');
    selects.forEach(select => { select.replaceChildren(new Option('Cargando personal del turno…', '')); select.disabled = true; });
    save.disabled = true;
    try {
        const url = new URL(form.dataset.candidatesUrl, window.location.origin);
        url.searchParams.set('candidate_shift_id', this.value || this.dataset.old);
        const response = await fetch(url, {headers: {'Accept': 'application/json'}});
        const result = await response.json();
        if (version !== candidateRequestVersion) return;
        if (!response.ok) throw new Error('No se pudo consultar el personal del turno. Vuelva a seleccionar el turno.');
        selects.forEach(select => {
            const candidates = result.candidates[select.dataset.old] || [];
            select.replaceChildren(new Option(candidates.length ? 'Seleccione personal con asistencia en este turno' : 'No hay personal disponible con asistencia en este turno', ''));
            candidates.forEach(person => select.add(new Option(person.label, person.id)));
            select.disabled = false;
        });
        alert.classList.add('d-none');
        save.disabled = false;
    } catch (error) {
        if (version !== candidateRequestVersion) return;
        alert.textContent = error.message;
        alert.classList.remove('d-none');
    }
});

$(document).on('submit', '#dashboardReplacementForm', async function (event) {
    event.preventDefault();
    const form = this;
    const button = form.querySelector('[type="submit"]') || document.querySelector('#dashboardDetailsContent [form="dashboardReplacementForm"][type="submit"]');
    const alert = form.querySelector('.dashboard-form-alert');
    const selected = Array.from(form.querySelectorAll('.dashboard-change-select')).filter(select => select.value);
    alert.classList.add('d-none');
    if (selected.length === 0) {
        alert.textContent = 'Seleccione al menos un cambio para aplicar.';
        alert.classList.remove('d-none');
        return;
    }
    form.querySelectorAll('.dashboard-edit-row').forEach(row => {
        const select = row.querySelector('.dashboard-change-select');
        if (select && !select.value) row.querySelectorAll('[name^="changes["]').forEach(input => input.disabled = true);
    });
    button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            body: (() => {
                const payload = new FormData(form);
                form.querySelectorAll('[name^="changes["]').forEach(input => input.disabled = false);
                return payload;
            })(),
        });
        const result = await response.json();
        if (!response.ok) {
            const messages = result.errors ? Object.values(result.errors).flat() : [result.message || 'No se pudo guardar el reemplazo.'];
            alert.textContent = messages.join(' ');
            alert.classList.remove('d-none');
            return;
        }
        window.location.href = result.redirect;
    } catch (error) {
        alert.textContent = 'Ocurrió un error al guardar. Revise su conexión e inténtelo de nuevo.';
        alert.classList.remove('d-none');
    } finally {
        button.disabled = false;
    }
});

$(document).on('change', '.dashboard-person-select', function () {
    const selects = Array.from(document.querySelectorAll('#dashboardReplacementForm .dashboard-person-select'));
    const selectedIds = selects.map(select => select.value).filter(Boolean);
    selects.forEach(select => {
        Array.from(select.options).forEach(option => {
            option.disabled = option.value !== '' && option.value !== select.value && selectedIds.includes(option.value);
        });
    });
});

$(document).on('click', '.dashboard-modal-dismiss', function (event) {
    event.preventDefault();
    ++candidateRequestVersion;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('dashboardDetailsModal')).hide();
});
</script>
@endsection
