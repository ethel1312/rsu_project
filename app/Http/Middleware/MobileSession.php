<?php

namespace App\Http\Middleware;

use App\Models\Employees\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileSession
{
    public function handle(Request $request, Closure $next)
    {
        $session = DB::table('mobile_sessions')->where('token_hash', hash('sha256', $request->bearerToken() ?? ''))
            ->where('expires_at', '>', now())->first();
        abort_unless($session, 401, 'Vuelve a iniciar sesión.');
        $person = $session->employee_id
            ? Employee::whereKey($session->employee_id)->where('status', true)->whereHas('employeeType', fn ($q) => $q->where('name', 'Conductor'))->first()
            : DB::table('citizens')->find($session->citizen_id);
        abort_unless($person, 401, 'La cuenta ya no está disponible.');
        $request->attributes->add(['mobile_person' => $person, 'mobile_role' => $session->employee_id ? 'driver' : 'citizen', 'mobile_session' => $session->id]);

        return $next($request);
    }
}
