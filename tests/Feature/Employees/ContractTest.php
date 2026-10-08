<?php

namespace Tests\Feature\Employees;

use App\Models\Employees\Contract;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_09_19_223236_add_two_factor_columns_to_users_table.php',
            'database/migrations/2026_10_05_220523_create_employee_types_table.php',
            'database/migrations/2026_10_05_220605_create_employees_table.php',
            'database/migrations/2026_10_06_145635_create_contracts_table.php',
        ]];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $type = EmployeeType::create(['name' => 'Conductor', 'description' => 'Conductor']);
        $this->employee = Employee::create([
            'dni' => '01234567',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'birth_date' => '1990-01-01',
            'email' => 'ana@example.test',
            'status' => true,
            'password' => Hash::make('secreto123'),
            'address' => 'Chiclayo',
            'employee_type_id' => $type->id,
        ]);

        $this->actingAs(User::factory()->create());
    }

    private function contractData(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'contract_type' => 'Temporal',
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-31',
            'salary' => 1500,
            'trial_period_months' => null,
            'is_active' => true,
        ], $overrides);
    }

    private function createTemporaryContract(array $overrides = []): Contract
    {
        return Contract::create($this->contractData($overrides));
    }

    public function test_another_active_temporary_contract_is_allowed_when_dates_do_not_overlap(): void
    {
        $this->createTemporaryContract([
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
        ]);

        $this->postJson(route('admin.contracts.store'), $this->contractData([
            'start_date' => '2026-12-10',
            'end_date' => '2026-12-31',
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Contrato registrado exitosamente.');

        $this->assertDatabaseCount('contracts', 2);
        $this->assertSame(2, Contract::where('is_active', true)->count());
    }

    public function test_employee_search_returns_active_matches_by_name_or_dni(): void
    {
        $this->getJson(route('admin.contracts.employees', ['q' => 'Ana']))
            ->assertOk()
            ->assertExactJson([
                'results' => [['id' => $this->employee->id, 'text' => '01234567 - Pérez, Ana']],
                'pagination' => ['more' => false],
            ]);

        $this->getJson(route('admin.contracts.employees', ['q' => '0123']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $this->employee->id);
    }

    public function test_overlapping_temporary_contract_is_rejected_even_when_dates_only_touch(): void
    {
        $this->createTemporaryContract([
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-12',
        ]);

        $this->postJson(route('admin.contracts.store'), $this->contractData())
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Las fechas seleccionadas se cruzan con un contrato previo de este empleado.');

        $this->assertDatabaseCount('contracts', 1);
    }

    public function test_temporary_contract_is_rejected_before_two_months_have_passed(): void
    {
        $this->createTemporaryContract([
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
        ]);

        $this->postJson(route('admin.contracts.store'), $this->contractData([
            'start_date' => '2026-12-09',
            'end_date' => '2026-12-31',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Deben pasar al menos 2 meses desde el fin del contrato anterior para iniciar uno nuevo.');

        $this->assertDatabaseCount('contracts', 1);
    }

    public function test_an_active_temporary_contract_can_be_updated_when_its_dates_do_not_overlap(): void
    {
        $this->createTemporaryContract([
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-10',
        ]);
        $contract = $this->createTemporaryContract([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]);

        $this->putJson(route('admin.contracts.update', $contract), $this->contractData([
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-31',
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Contrato actualizado exitosamente.');

        $this->assertSame('2026-10-10', $contract->fresh()->start_date->toDateString());
        $this->assertSame('2026-10-31', $contract->fresh()->end_date->toDateString());
        $this->assertSame(2, Contract::where('is_active', true)->count());
    }
}
