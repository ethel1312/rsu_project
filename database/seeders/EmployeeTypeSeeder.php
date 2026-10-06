<?php

namespace Database\Seeders;

use App\Models\Employees\EmployeeType;
use Illuminate\Database\Seeder;

class EmployeeTypeSeeder extends Seeder
{
    public function run()
    {
        EmployeeType::updateOrCreate(
            ['name' => 'Conductor'],
            [
                'description' => 'Personal autorizado para conducir vehículos.',
                'is_default' => true
            ]
        );

        EmployeeType::updateOrCreate(
            ['name' => 'Ayudante'],
            [
                'description' => 'Personal encargado de apoyar las actividades operativas.',
                'is_default' => true
            ]
        );
    }
}