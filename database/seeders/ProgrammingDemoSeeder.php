<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Brandmodel;
use App\Models\Color;
use App\Models\Employees\Contract;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeType;
use App\Models\Employees\VacationBalance;
use App\Models\Programming\Shift;
use App\Models\Programming\PersonnelGroup;
use App\Models\Programming\Zone;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ProgrammingDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $driverType = EmployeeType::firstOrCreate(['name' => 'Conductor'], ['is_default' => true]);
            $helperType = EmployeeType::firstOrCreate(['name' => 'Ayudante'], ['is_default' => true]);
            $year = now('America/Lima')->year;

            foreach ([
                ['Mañana (demo)', '06:00', '14:00'],
                ['Tarde (demo)', '14:00', '22:00'],
                ['Noche (demo)', '22:00', '06:00'],
            ] as [$name, $start, $end]) {
                Shift::firstOrCreate(['name' => $name], [
                    'start_time' => $start, 'end_time' => $end,
                    'description' => 'Turno de demostración para el avance 04.',
                ]);
            }

            foreach ([
                ['Moshoqueque (demo)', -6.7650, -79.8380],
                ['José Leonardo Ortiz Norte (demo)', -6.7520, -79.8380],
            ] as [$name, $lat, $lng]) {
                Zone::firstOrCreate(['name' => $name], [
                    'department' => 'Lambayeque', 'province' => 'Chiclayo',
                    'district' => 'Jose Leonardo Ortiz',
                    'description' => 'Zona de demostración en Chiclayo. El perímetro es referencial, no es una delimitación municipal oficial.',
                    'average_waste_kg' => 1500, 'status' => true,
                    'coordinates' => [
                        ['lat' => $lat, 'lng' => $lng],
                        ['lat' => $lat, 'lng' => $lng + 0.006],
                        ['lat' => $lat + 0.005, 'lng' => $lng + 0.006],
                        ['lat' => $lat + 0.005, 'lng' => $lng],
                    ],
                ]);
            }

            $type = VehicleType::firstOrCreate(['name' => 'Compactadora']);
            $brand = Brand::firstOrCreate(['name' => 'Scania']);
            $model = Brandmodel::firstOrCreate(['name' => 'P320 demo', 'brand_id' => $brand->id], [
                'code' => 'DEMO-P320', 'description' => 'Modelo de demostración.',
            ]);
            $color = Color::firstOrCreate(['name' => 'Blanco'], ['code' => '#FFFFFF']);

            foreach ([1, 2] as $number) {
                Vehicle::firstOrCreate(['code' => sprintf('DEMO-%03d', $number)], [
                    'name' => "Compactadora de demostración {$number}",
                    'plate' => 'DEM-10'.$number, 'year' => $year,
                    'type_id' => $type->id, 'brand_id' => $brand->id,
                    'model_id' => $model->id, 'color_id' => $color->id,
                    'load_capacity' => 12000, 'compact_capacity' => 15,
                    'fuel_capacity' => 200, 'occupant_capacity' => 3,
                    'description' => 'Vehículo ficticio para probar el avance 04.',
                ]);
            }

            $staff = [
                ['conductor1', 'Carlos Demo', 'Conductor Uno', $driverType, 1],
                ['conductor2', 'Ana Demo', 'Conductor Dos', $driverType, 2],
                ['conductor3', 'Luis Demo', 'Conductor Suplente', $driverType, 3],
                ['ayudante1', 'María Demo', 'Ayudante Uno', $helperType, 4],
                ['ayudante2', 'José Demo', 'Ayudante Dos', $helperType, 5],
                ['ayudante3', 'Rosa Demo', 'Ayudante Tres', $helperType, 6],
                ['ayudante4', 'Pedro Demo', 'Ayudante Cuatro', $helperType, 7],
                ['ayudante5', 'Elena Demo', 'Ayudante Suplente', $helperType, 8],
            ];

            foreach ($staff as [$key, $firstName, $lastName, $employeeType, $number]) {
                $email = 'demo.'.$key.'@example.test';
                if (!Employee::where('email', $email)->exists() && Employee::where('dni', (string) (99000000 + $number))->exists()) {
                    throw new \RuntimeException('No se agregaron datos: un DNI reservado para la demostración ya está en uso.');
                }
                $employee = Employee::firstOrCreate(['email' => $email], [
                    'dni' => (string) (99000000 + $number),
                    'first_name' => $firstName, 'last_name' => $lastName,
                    'birth_date' => '1990-01-15', 'phone' => null,
                    'status' => true, 'password' => Hash::make(bin2hex(random_bytes(24))),
                    'address' => 'Chiclayo - dirección ficticia de demostración',
                    'employee_type_id' => $employeeType->id,
                ]);
                Contract::firstOrCreate(['employee_id' => $employee->id], [
                    'start_date' => "{$year}-01-01", 'contract_type' => 'Permanente', 'end_date' => null,
                    'salary' => $employeeType->id === $driverType->id ? 2400 : 1800,
                    'trial_period_months' => null, 'is_active' => true,
                ]);
                foreach ([$year, $year + 1] as $balanceYear) {
                    VacationBalance::firstOrCreate(['employee_id' => $employee->id, 'year' => $balanceYear], [
                        'days_entitled' => 30, 'days_taken' => 0,
                    ]);
                }
            }

            if (Schema::hasTable('personnel_groups')) {
                foreach ([
                    ['Grupo Moshoqueque (demo)', 'Moshoqueque (demo)', 'Mañana (demo)', 1, [1, 3, 5], [1, 2]],
                    ['Grupo JLO Norte (demo)', 'José Leonardo Ortiz Norte (demo)', 'Tarde (demo)', 2, [2, 4, 6], [3, 4]],
                ] as [$name, $zoneName, $shiftName, $number, $days, $helperNumbers]) {
                    $group = PersonnelGroup::firstOrCreate(['name' => $name], [
                        'zone_id' => Zone::where('name', $zoneName)->sole()->id,
                        'shift_id' => Shift::where('name', $shiftName)->sole()->id,
                        'vehicle_id' => Vehicle::where('code', sprintf('DEMO-%03d', $number))->sole()->id,
                        'driver_id' => Employee::where('email', 'demo.conductor'.$number.'@example.test')->sole()->id,
                        'days_of_week' => $days, 'status' => true,
                    ]);
                    if ($group->wasRecentlyCreated) {
                        $group->helpers()->sync(Employee::whereIn('email', array_map(
                            fn ($helperNumber) => 'demo.ayudante'.$helperNumber.'@example.test', $helperNumbers
                        ))->pluck('id')->all());
                    }
                }
            }
        });

        $this->command?->info('Datos de demostración de Chiclayo preparados sin reemplazar registros existentes.');
    }
}
