<div class="mt-3">
    <label for="day{{ ucfirst($prefix) }}ReasonPreset">Motivo predefinido</label>
    <select id="day{{ ucfirst($prefix) }}ReasonPreset" class="form-control day-reason-preset">
        <option value="">Seleccione un motivo</option>
        @foreach ($reasons as $reason)
            <option value="{{ $reason->id }}" data-description="{{ $reason->description }}">{{ $reason->name }}</option>
        @endforeach
    </select>
    <label for="day{{ ucfirst($prefix) }}ReasonDescription" class="mt-2">Motivo del cambio <small class="text-muted">(se completará automáticamente)</small></label>
    <textarea id="day{{ ucfirst($prefix) }}ReasonDescription" class="form-control day-reason-description" rows="2" maxlength="500" placeholder="Ingrese el motivo del cambio"></textarea>
</div>
