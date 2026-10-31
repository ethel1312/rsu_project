<div class="mt-3">
    <label for="day{{ ucfirst($prefix) }}ReasonPreset">Motivo predefinido</label>
    <select id="day{{ ucfirst($prefix) }}ReasonPreset" class="form-control day-reason-preset">
        <option value="">Seleccione un motivo</option>
        <option value="Imprevistos" data-description="Cambio requerido por imprevistos.">Imprevistos</option>
        <option value="Mantenimiento" data-description="Cambio requerido por mantenimiento.">Mantenimiento</option>
        <option value="Emergencia operativa" data-description="Cambio requerido por una emergencia operativa.">Emergencia operativa</option>
        <option value="Vacaciones" data-description="Cambio requerido por vacaciones del personal.">Vacaciones</option>
        <option value="Otro" data-description="">Otro</option>
    </select>
    <label for="day{{ ucfirst($prefix) }}ReasonDescription" class="mt-2">Motivo del cambio <small class="text-muted">(se completará automáticamente)</small></label>
    <textarea id="day{{ ucfirst($prefix) }}ReasonDescription" class="form-control day-reason-description" rows="2" maxlength="500" placeholder="Ingrese el motivo del cambio"></textarea>
</div>
