<form method="POST" action="{{ $period ? route('admin.maintenances.update', $period) : route('admin.maintenances.store') }}" data-maintenance-save>
    @csrf
    @if ($period)
        @method('PUT')
    @endif
    <div class="alert alert-danger d-none" data-form-errors role="alert"></div>
    <div class="mb-3">
        <label class="form-label" for="maintenance-name">Nombre</label>
        <input class="form-control" id="maintenance-name" name="name" value="{{ $period?->name }}" maxlength="150" required>
    </div>
    <div class="row g-3">
        <div class="col-sm-6">
            <label class="form-label" for="maintenance-start-date">Fecha de inicio</label>
            <input type="date" class="form-control" id="maintenance-start-date" name="start_date"
                value="{{ $period?->start_date->toDateString() }}" required>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="maintenance-end-date">Fecha de fin</label>
            <input type="date" class="form-control" id="maintenance-end-date" name="end_date"
                value="{{ $period?->end_date->toDateString() }}" min="{{ $period?->start_date->toDateString() }}" required>
        </div>
    </div>
    @include('admin.maintenances.forms.footer')
</form>
