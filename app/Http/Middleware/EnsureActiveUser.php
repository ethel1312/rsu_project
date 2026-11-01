<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) abort(403, 'Su cuenta está inactiva.');
            return redirect()->route('login')->withErrors(['email' => 'Su cuenta está inactiva. Contacte al administrador.']);
        }
        return $next($request);
    }
}
