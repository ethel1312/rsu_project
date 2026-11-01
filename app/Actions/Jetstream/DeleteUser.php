<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        if ($user->is_admin) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deleteUser' => 'Un administrador principal debe ser eliminado por otro administrador desde Gestión de Usuarios.',
            ]);
        }
        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();
    }
}
