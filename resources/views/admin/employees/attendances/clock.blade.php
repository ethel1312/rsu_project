<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <img src="{{ asset('img/logo_muni.png') }}" alt="Municipalidad José Leonardo Ortiz" class="h-24 w-auto mx-auto">
        </x-slot>

        <h1 class="text-xl font-semibold mb-2">Marcación de asistencia</h1>
        <p class="text-sm text-gray-600 mb-4">Ingrese su DNI y contraseña. La fecha, hora y el ingreso o salida se registran automáticamente.</p>

        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-green-600" role="status">{{ session('status') }}</div>
        @endif
        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('employees.attendances.mark') }}" id="frmClock">
            @csrf
            <div>
                <x-label for="dni" value="DNI" />
                <x-input id="dni" class="block mt-1 w-full" type="text" name="dni" inputmode="numeric" pattern="[0-9]{8}" minlength="8" maxlength="8" required autofocus autocomplete="off" />
            </div>
            <div class="mt-4">
                <x-label for="password" value="Contraseña" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" maxlength="255" required autocomplete="off" />
            </div>
            <div class="flex items-center justify-between mt-4">
                <span class="text-sm text-gray-600">Hora de Lima</span>
                <x-button type="submit" class="ms-4 bg-gray-900 hover:bg-gray-800 text-white">Marcar asistencia</x-button>
            </div>
        </form>
        <script>
            document.getElementById('frmClock').addEventListener('submit', function (event) {
                if (this.dataset.saving) { event.preventDefault(); return; }
                this.dataset.saving = 'true';
                this.querySelector('button[type="submit"]').disabled = true;
                this.querySelector('button[type="submit"]').textContent = 'Registrando...';
            });
            window.addEventListener('pageshow', function () {
                const form = document.getElementById('frmClock');
                form.reset();
                delete form.dataset.saving;
                form.querySelector('button[type="submit"]').disabled = false;
                form.querySelector('button[type="submit"]').textContent = 'Marcar asistencia';
            });
        </script>
    </x-authentication-card>
</x-guest-layout>
