<?php

namespace App\Http\Middleware;

use App\Support\UserPermissions;
use Closure;
use Illuminate\Http\Request;

class CheckAdminPermission
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403, 'Su cuenta está inactiva.');
        if ($request->route()->getName() === 'admin.home') {
            return $next($request);
        }
        $permissions = UserPermissions::forRoute((string) $request->route()->getName());
        abort_unless(collect($permissions)->contains(fn ($key) => $user->hasPermission($key)), 403, 'No tiene permiso para realizar esta acción.');
        return $next($request);
    }
}
