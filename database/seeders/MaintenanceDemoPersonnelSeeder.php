<?php

namespace Database\Seeders;

use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MaintenanceDemoPersonnelSeeder extends Seeder
{
    public function run(): void
    {
        $type = EmployeeType::updateOrCreate(
            ['name' => 'Mantenimiento'],
            [
                'description' => 'Personal asignado a los mantenimientos programados de vehículos.',
                'is_default' => true,
            ],
        );

        foreach ([
            ['99000101', 'Luis Alberto', 'Mantenimiento Demo Uno', 'demo.mantenimiento1@example.test'],
            ['99000102', 'Rosa Elena', 'Mantenimiento Demo Dos', 'demo.mantenimiento2@example.test'],
        ] as [$dni, $firstName, $lastName, $email]) {
            $existing = Employee::where('email', $email)->first();
            if (! $existing && Employee::where('dni', $dni)->exists()) {
                throw new \RuntimeException("No se agregó {$email}: el DNI reservado para demostración ya está en uso.");
            }

            Employee::firstOrCreate(['email' => $email], [
                'dni' => $dni,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birth_date' => '1990-01-15',
                'phone' => null,
                'status' => true,
                'password' => Hash::make(Str::random(48)),
                'address' => 'Chiclayo - dirección ficticia de demostración',
                'employee_type_id' => $type->id,
            ]);
        }
    }
}
