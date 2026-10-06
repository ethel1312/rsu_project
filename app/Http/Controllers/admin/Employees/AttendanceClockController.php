<?php

namespace App\Http\Controllers\admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Attendance;
use App\Models\Employees\Employee;
use App\Services\Employees\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AttendanceClockController extends Controller
{
    public function create()
    {
        return view('admin.employees.attendances.clock');
    }

    public function store(Request $request, AttendanceService $attendances)
    {
        $data = $request->validate([
            'dni' => 'required|digits:8',
            'password' => 'required|string|max:255',
        ], [
            'dni.required' => 'El DNI es obligatorio.',
            'dni.digits' => 'El DNI debe tener exactamente 8 dígitos.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'Ingrese una contraseña válida.',
            'password.max' => 'La contraseña no debe superar los 255 caracteres.',
        ]);

        $key = 'attendance-clock:'.hash('sha256', $data['dni'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'dni' => 'Demasiados intentos. Intente nuevamente en '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        $attendance = DB::transaction(function () use ($data, $key, $attendances) {
            $employee = Employee::where('dni', $data['dni'])->lockForUpdate()->first();
            $stored = (string) $employee?->password;
            $hashed = password_get_info($stored)['algoName'] !== 'unknown';
            // El módulo actual de personal guarda contraseñas sin hash. Al usarlas,
            // se convierten a hash sin cambiar la contraseña elegida por el personal.
            $valid = $employee && ($hashed ? password_verify($data['password'], $stored) : hash_equals($stored, $data['password']));

            if (! $valid || ! $employee->status) {
                RateLimiter::hit($key, 60);
                throw ValidationException::withMessages([
                    'dni' => 'El DNI o la contraseña son incorrectos, o el personal está inactivo.',
                ]);
            }

            $now = now(Attendance::TIMEZONE);
            $attendance = $attendances->save([
                'employee_id' => $employee->id,
                'date' => $now->format('Y-m-d'),
                'time' => $now->format('H:i:s'),
                'status' => 'present',
                'notes' => null,
            ]);

            if (! $hashed || Hash::needsRehash($stored)) {
                $employee->update(['password' => Hash::make($data['password'])]);
            }

            return $attendance;
        }, 3);

        RateLimiter::clear($key);
        $type = $attendance->type === 'entry' ? 'Ingreso' : 'Salida';

        return redirect()->route('employees.attendances.clock')->with('status',
            $type.' registrado exitosamente el '.$attendance->date->format('d/m/Y').' a las '.$attendance->time.' (hora de Lima).'
        );
    }
}
