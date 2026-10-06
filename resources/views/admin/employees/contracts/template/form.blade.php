<div class="row">
    {{-- Personal --}}
    <div class="col-md-12 mb-3">
        {!! Form::label('employee_id', 'Personal *') !!}
        {!! Form::select('employee_id', $employees, null, [
            'class' => 'form-select',
            'placeholder' => 'Seleccione un empleado',
            'required',
            'id' => 'employee_id'
        ]) !!}
        <small class="text-muted">Personal activo disponible para contratación.</small>
    </div>

    {{-- Tipo de Contrato --}}
    <div class="col-md-12 mb-3">
        {!! Form::label('contract_type', 'Tipo de Contrato *') !!}
        {!! Form::select('contract_type', [
            'Permanente' => 'Permanente',
            'Nombrado' => 'Nombrado',
            'Temporal' => 'Temporal'
        ], null, [
            'class' => 'form-select',
            'required',
            'id' => 'contract_type'
        ]) !!}
    </div>

    {{-- Fecha de Inicio --}}
    <div class="col-md-6 mb-3">
        {!! Form::label('start_date', 'Fecha de Inicio *') !!}
        {!! Form::date('start_date', isset($contract) && $contract->start_date ? $contract->start_date->format('Y-m-d') : date('Y-m-d'), [
            'class' => 'form-control',
            'required',
            'id' => 'start_date'
        ]) !!}
    </div>

    {{-- Fecha de Fin --}}
    <div class="col-md-6 mb-3">
        {!! Form::label('end_date', 'Fecha de Finalización') !!}
        {!! Form::date('end_date', isset($contract) && $contract->end_date ? $contract->end_date->format('Y-m-d') : null, [
            'class' => 'form-control',
            'id' => 'end_date',
            'disabled' => (!isset($contract) || $contract->contract_type !== 'Temporal')
        ]) !!}
        <small class="text-muted" id="end_date_help">Dejar en blanco si es contrato Permanente o Nombrado.</small>
    </div>

    {{-- Salario --}}
    <div class="col-md-6 mb-3">
        {!! Form::label('salary', 'Salario *') !!}
        <div class="input-group">
            <span class="input-group-text">S/</span>
            {!! Form::number('salary', null, [
                'class' => 'form-control',
                'placeholder' => '0.00',
                'step' => '0.01',
                'required'
            ]) !!}
        </div>
    </div>

    {{-- Período de Prueba --}}
    <div class="col-md-6 mb-3">
        {!! Form::label('trial_period_months', 'Período de Prueba (Meses - Opcional)') !!}
        {!! Form::number('trial_period_months', null, [
            'class' => 'form-control',
            'placeholder' => '3',
            'min' => '0'
        ]) !!}
    </div>

    {{-- Contrato Activo --}}
    <div class="col-md-12 mb-3">
        {!! Form::label('is_active', '¿Contrato Activo? *') !!}
        {!! Form::select('is_active', [
            1 => 'Activo',
            0 => 'Inactivo'
        ], null, [
            'class' => 'form-select',
            'required'
        ]) !!}
    </div>
</div>

<script>
    $('#contract_type').off('change').on('change', function() {
        if ($(this).val() === 'Temporal') {
            $('#end_date').prop('disabled', false).prop('required', true);
            $('#end_date_help').text('Obligatorio para contratos temporales.');
        } else {
            $('#end_date').prop('disabled', true).prop('required', false).val('');
            $('#end_date_help').text('Dejar en blanco si es contrato Permanente o Nombrado.');
        }
    });
</script>