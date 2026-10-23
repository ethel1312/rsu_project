<?php

namespace Tests\Feature\Programming;

use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeType;
use Database\Seeders\ProgrammingDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ProgrammingDemoSeederTest extends TestCase
{
    use DatabaseMigrations;

    private function existingEmployee(string $dni = '74320198'): Employee
    {
        $type = EmployeeType::firstOrCreate(['name' => 'Conductor'], [
            'description' => 'Tipo existente que debe conservarse', 'is_default' => false,
        ]);

        return Employee::create([
            'dni' => $dni, 'first_name' => 'Registro existente', 'last_name' => 'Conservado',
            'birth_date' => '1988-03-15', 'email' => 'personal.existente@example.test',
            'status' => true, 'password' => 'hash-existente', 'address' => 'Dirección existente',
            'employee_type_id' => $type->id,
        ]);
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['employee_types', 'employees', 'contracts', 'vacation_balances', 'zones', 'shifts', 'vehicles', 'vehicletypes', 'brands', 'brandmodels', 'colors', 'personnel_groups'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toArray();
        }
        $snapshot['personnel_group_helpers'] = DB::table('personnel_group_helpers')->orderBy('personnel_group_id')->orderBy('employee_id')->get()->toArray();

        return $snapshot;
    }

    public function test_demo_data_can_be_loaded_twice_without_duplicates_or_overwriting_existing_changes(): void
    {
        $existing = $this->existingEmployee();
        $originalEmployee = $existing->fresh()->getRawOriginal();

        app(ProgrammingDemoSeeder::class)->run();

        DB::table('zones')->where('name', 'Moshoqueque (demo)')->update(['description' => 'Descripción editada por el usuario']);
        DB::table('employees')->where('email', 'demo.conductor1@example.test')->update([
            'address' => 'Dirección corregida por el usuario', 'password' => 'clave-modificada-conservada',
        ]);
        $beforeSecondRun = $this->snapshot();

        app(ProgrammingDemoSeeder::class)->run();

        $this->assertEquals($beforeSecondRun, $this->snapshot());
        $this->assertSame($originalEmployee, $existing->fresh()->getRawOriginal());
        $this->assertSame(8, Employee::where('email', 'like', 'demo.%@example.test')->count());
        $this->assertDatabaseCount('zones', 2);
        $this->assertDatabaseCount('shifts', 3);
        $this->assertDatabaseCount('vehicles', 2);
        $this->assertDatabaseCount('contracts', 8);
        $this->assertDatabaseCount('vacation_balances', 16);
    }

    public function test_reserved_demo_dni_collision_rolls_back_all_demo_inserts(): void
    {
        $existing = $this->existingEmployee('99000001');
        $before = $this->snapshot();

        try {
            app(ProgrammingDemoSeeder::class)->run();
            $this->fail('El cargador debía rechazar el DNI de demostración ya ocupado.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('un DNI reservado para la demostración ya está en uso', $exception->getMessage());
        }

        $this->assertEquals($before, $this->snapshot());
        $this->assertDatabaseHas('employees', ['id' => $existing->id, 'dni' => '99000001', 'email' => 'personal.existente@example.test']);
    }
}
