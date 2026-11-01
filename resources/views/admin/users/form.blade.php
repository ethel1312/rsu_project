<form id="userForm" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <div class="alert alert-danger d-none" id="userErrors" role="alert"></div>
    <div class="row">
        @foreach (['name' => 'Nombre', 'last_name' => 'Apellidos', 'dni' => 'DNI', 'birth_date' => 'Fecha de nacimiento', 'email' => 'Email', 'phone' => 'Teléfono', 'license' => 'Licencia'] as $field => $label)
            <div class="col-md-6 mb-3">
                <label for="user_{{ $field }}">{{ $label }} {{ in_array($field, ['phone', 'license']) ? '' : '*' }}</label>
                <input id="user_{{ $field }}" name="{{ $field }}" class="form-control"
                       type="{{ $field === 'birth_date' ? 'date' : ($field === 'email' ? 'email' : 'text') }}"
                       value="{{ $field === 'birth_date' ? $user->birth_date?->format('Y-m-d') : $user->$field }}"
                       @if (!in_array($field, ['phone', 'license'])) required @endif
                       @if ($field === 'dni') maxlength="8" pattern="[0-9]{8}" inputmode="numeric" @endif
                       @if ($field === 'birth_date') max="{{ now()->subDay()->toDateString() }}" @endif>
            </div>
        @endforeach
        <div class="col-md-6 mb-3">
            <label for="user_type">Tipo de usuario *</label>
            <select name="user_type" id="user_type" class="form-control" required>
                @foreach ($types as $type)<option @selected(($user->user_type ?? 'Operador') === $type)>{{ $type }}</option>@endforeach
            </select>
            <small class="text-muted">El tipo identifica el cargo; los accesos se asignan en Permisos.</small>
        </div>
        <div class="col-12 mb-3">
            <label for="user_address">Dirección *</label>
            <textarea name="address" id="user_address" class="form-control" maxlength="1000" required>{{ $user->address }}</textarea>
        </div>
        <div class="col-md-6 mb-3">
            <label for="user_password">Contraseña {{ $user->exists ? '(opcional)' : '*' }}</label>
            <input type="password" name="password" id="user_password" class="form-control" autocomplete="new-password" minlength="8" @required(!$user->exists)>
            @if ($user->exists)<small class="text-muted">Deje vacío para conservar la contraseña actual.</small>@endif
        </div>
        <div class="col-md-6 mb-3">
            <label for="user_password_confirmation">Confirmar contraseña</label>
            <input type="password" name="password_confirmation" id="user_password_confirmation" class="form-control" autocomplete="new-password">
        </div>
        <div class="col-md-6 mb-3">
            <label for="user_active">Estado *</label>
            <select name="is_active" id="user_active" class="form-control" required>
                <option value="1" @selected(!$user->exists || $user->is_active)>Activo</option>
                <option value="0" @selected($user->exists && !$user->is_active)>Inactivo</option>
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label for="user_photo">Foto de perfil</label>
            <input type="file" name="photo" id="user_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
            <small class="text-muted">JPG, PNG o WebP. Máximo 2 MB.</small>
            @if ($user->profile_photo_path)
                <div class="mt-2">@include('admin.users.photo') <label><input type="checkbox" name="remove_photo" value="1"> Quitar foto actual</label></div>
            @endif
        </div>
    </div>
    <div class="d-flex justify-content-end" style="gap:8px">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar usuario</button>
    </div>
</form>
