<div class="d-flex" style="gap:5px">
    @if (! $user->is_admin || auth()->user()->is_admin)
        @can('users.manage')
            <a href="{{ route('admin.users.permissions', $user) }}" class="btn btn-sm btn-info" title="Permisos" aria-label="Permisos"><i class="bi bi-key"></i></a>
        @endcan
        @can('users.edit')
            <button type="button" class="btn btn-sm btn-warning edit-user" data-url="{{ route('admin.users.edit', $user) }}" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></button>
        @endcan
        @can('users.delete')
            @if (auth()->id() !== $user->id)
                <button type="button" class="btn btn-sm btn-danger delete-user" data-url="{{ route('admin.users.destroy', $user) }}" title="Eliminar" aria-label="Eliminar"><i class="bi bi-trash"></i></button>
            @endif
        @endcan
    @endif
</div>
