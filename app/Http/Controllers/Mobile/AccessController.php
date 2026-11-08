<?php

namespace App\Http\Controllers\Mobile;

use App\Models\Employees\Employee;
use App\Models\Programming\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccessController
{
    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255', 'role' => 'required|in:driver,citizen']);

        return DB::transaction(function () use ($data) {
            $driver = $data['role'] === 'driver';
            $person = $driver
                ? Employee::where('email', $data['email'])->where('status', true)->whereHas('employeeType', fn ($q) => $q->where('name', 'Conductor'))->lockForUpdate()->first()
                : DB::table('citizens')->where('email', $data['email'])->first();
            $stored = (string) ($person->password ?? '');
            $legacy = $driver && $stored !== '' && password_get_info($stored)['algoName'] === 'unknown';
            $ok = $legacy ? hash_equals($stored, $data['password']) : password_verify($data['password'], $stored);
            abort_unless($person && $ok, 401, 'Correo o contraseña incorrectos para este perfil.');
            if ($legacy) {
                $person->update(['password' => Hash::make($data['password'])]);
            }
            $token = Str::random(64);
            DB::table('mobile_sessions')->insert(['token_hash' => hash('sha256', $token), $driver ? 'employee_id' : 'citizen_id' => $person->id, 'expires_at' => now()->addDays(7)]);

            return ['token' => $token, 'role' => $data['role'], 'name' => trim($person->first_name.' '.$person->last_name), 'dni' => $person->dni];
        });
    }

    public function register(Request $r)
    {
        $data = $r->validate(['dni' => 'required|digits:8|unique:citizens', 'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:citizens', 'password' => 'required|string|min:8|max:72', 'zone_id' => ['required', 'integer', Rule::exists('zones', 'id')->where('status', true)]]);
        $data['password'] = Hash::make($data['password']);
        DB::table('citizens')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Cuenta creada. Ingresa con tu correo.'], 201);
    }

    public function zones()
    {
        return ['items' => Zone::where('status', true)->orderBy('name')->get(['id', 'name', 'description', 'coordinates'])];
    }

    public function logout(Request $r)
    {
        DB::table('mobile_sessions')->where('id', $r->attributes->get('mobile_session'))->delete();

        return ['message' => 'Sesión cerrada'];
    }
}
