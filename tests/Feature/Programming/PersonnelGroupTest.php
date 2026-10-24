<?php

namespace Tests\Feature\Programming;

use App\Models\Brand;
use App\Models\Brandmodel;
use App\Models\Color;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeType;
use App\Models\Programming\PersonnelGroup;
use App\Models\Programming\Shift;
use App\Models\Programming\Zone;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PersonnelGroupTest extends TestCase
{
    use DatabaseMigrations;

    private Employee $driver;
    private Employee $helper;
    private Employee $secondHelper;
    private Shift $shift;
    private Zone $zone;
    private Vehicle $vehicle;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_09_19_223236_add_two_factor_columns_to_users_table.php',
            'database/migrations/2026_09_23_004654_create_brands_table.php',
            'database/migrations/2026_09_30_010448_create_brandmodels_table.php',
            'database/migrations/2026_09_30_020540_create_colors_table.php',
            'database/migrations/2026_09_30_020737_create_vehicletypes_table.php',
            'database/migrations/2026_09_30_020743_create_vehicles_table.php',
            'database/migrations/2026_10_05_220523_create_employee_types_table.php',
            'database/migrations/2026_10_05_220605_create_employees_table.php',
            'database/migrations/2026_10_15_210133_create_zones_table.php',
            'database/migrations/2026_10_20_000000_create_shifts_table.php',
            'database/migrations/2026_10_23_110000_create_personnel_groups_table.php',
        ]];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $driverType = EmployeeType::create(['name' => 'Conductor']);
        $helperType = EmployeeType::create(['name' => 'Ayudante']);
        $this->driver = $this->employee('00000001', 'Jorge', $driverType);
        $this->helper = $this->employee('00000002', 'María', $helperType);
        $this->secondHelper = $this->employee('00000003', 'Pedro', $helperType);
        $this->shift = Shift::create(['name' => 'Noche', 'start_time' => '22:00', 'end_time' => '06:00']);
        $this->zone = Zone::create([
            'name' => 'Centro de Chiclayo', 'department' => 'Lambayeque', 'province' => 'Chiclayo',
            'district' => 'Chiclayo', 'coordinates' => [[-6.77, -79.84], [-6.78, -79.84], [-6.77, -79.85]], 'status' => true,
        ]);
        $brand = Brand::create(['name' => 'Marca de prueba']);
        $model = Brandmodel::create(['name' => 'Recolector', 'brand_id' => $brand->id]);
        $this->vehicle = Vehicle::create([
            'name' => 'Recolector de prueba', 'code' => 'TEST-01', 'plate' => 'TST-001', 'year' => 2026,
            'load_capacity' => 1000, 'fuel_capacity' => 100, 'compact_capacity' => 10, 'occupant_capacity' => 3,
            'type_id' => VehicleType::create(['name' => 'Compactador'])->id,
            'brand_id' => $brand->id, 'model_id' => $model->id, 'color_id' => Color::create(['name' => 'Blanco', 'code' => '#ffffff'])->id,
        ]);
        $this->actingAs(User::factory()->create());
    }

    private function employee(string $dni, string $name, EmployeeType $type): Employee
    {
        return Employee::create([
            'dni' => $dni, 'first_name' => $name, 'last_name' => 'Prueba', 'birth_date' => '1990-01-01',
            'email' => $dni.'@example.test', 'status' => true, 'password' => 'unused-test-password',
            'address' => 'Chiclayo', 'employee_type_id' => $type->id,
        ]);
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Grupo Centro', 'shift_id' => $this->shift->id, 'zone_id' => $this->zone->id,
            'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id,
            'days_of_week' => [5, 1, 3], 'helpers' => [$this->helper->id, $this->secondHelper->id],
        ], $overrides);
    }

    private function createGroup(array $overrides = []): PersonnelGroup
    {
        $this->postJson(route('admin.programming.personnel-groups.store'), $this->data($overrides))->assertOk();

        return PersonnelGroup::latest('id')->firstOrFail();
    }

    public function test_creates_an_active_reusable_template_with_sorted_days_and_helpers(): void
    {
        $group = $this->createGroup();

        $this->assertTrue($group->status);
        $this->assertSame([1, 3, 5], $group->days_of_week);
        $this->assertSame('Lunes, Miércoles, Viernes', $group->days_label);
        $this->assertEqualsCanonicalizing([$this->helper->id, $this->secondHelper->id], $group->helpers->modelKeys());

        // Las plantillas no asignan fechas; pueden reutilizar el mismo equipo y días.
        $this->createGroup(['name' => 'Grupo Centro Mes Siguiente']);
        $this->assertDatabaseCount('personnel_groups', 2);
    }

    public function test_updates_template_and_replaces_helpers_without_leaving_old_assignments(): void
    {
        $group = $this->createGroup();

        $this->putJson(route('admin.programming.personnel-groups.update', $group), $this->data([
            'name' => 'Grupo Centro Actualizado', 'days_of_week' => [2, 4, 7],
            'helpers' => [$this->secondHelper->id], 'status' => false,
        ]))->assertOk()->assertJsonPath('message', 'Grupo de personal actualizado exitosamente.');

        $group->refresh();
        $this->assertFalse($group->status);
        $this->assertSame([2, 4, 7], $group->days_of_week);
        $this->assertSame([$this->secondHelper->id], $group->helpers->modelKeys());
    }

    public function test_rejects_inactive_zone_and_inactive_or_wrong_employee_types(): void
    {
        $this->zone->update(['status' => false]);
        $this->driver->update(['status' => false]);
        $this->helper->update(['status' => false]);

        $this->postJson(route('admin.programming.personnel-groups.store'), $this->data())
            ->assertUnprocessable()->assertJsonValidationErrors(['zone_id', 'driver_id', 'helpers.0']);

        $this->zone->update(['status' => true]);
        $this->driver->update(['status' => true]);
        $this->postJson(route('admin.programming.personnel-groups.store'), $this->data([
            'driver_id' => $this->secondHelper->id, 'helpers' => [$this->driver->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['driver_id', 'helpers.0']);
        $this->assertDatabaseCount('personnel_groups', 0);
    }

    public function test_rejects_duplicate_names_days_and_helpers_and_missing_days(): void
    {
        $this->createGroup();

        $this->postJson(route('admin.programming.personnel-groups.store'), $this->data([
            'days_of_week' => [1, 1, 8], 'helpers' => [$this->helper->id, $this->helper->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['name', 'days_of_week.0', 'days_of_week.2', 'helpers.0']);

        $this->postJson(route('admin.programming.personnel-groups.store'), $this->data([
            'name' => 'Grupo sin personal', 'days_of_week' => [], 'helpers' => [],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['days_of_week', 'helpers']);
        $this->assertDatabaseCount('personnel_groups', 1);
    }

    public function test_rejects_more_people_than_the_vehicle_can_carry_including_the_driver(): void
    {
        $this->vehicle->update(['occupant_capacity' => 2]);

        $this->postJson(route('admin.programming.personnel-groups.store'), $this->data())
            ->assertUnprocessable()->assertJsonValidationErrors('helpers')
            ->assertJsonPath('errors.helpers.0', 'El vehículo TST-001 admite 2 personas en total. Seleccionó 3 (1 conductor y 2 ayudantes). Reduzca los ayudantes o seleccione otro vehículo.');

        $this->assertDatabaseCount('personnel_groups', 0);
    }

    public function test_invalid_update_keeps_the_previous_template_and_helpers(): void
    {
        $group = $this->createGroup();
        $this->vehicle->update(['occupant_capacity' => 2]);

        $this->putJson(route('admin.programming.personnel-groups.update', $group), $this->data([
            'name' => 'Nombre no guardado', 'status' => false,
        ]))->assertUnprocessable()->assertJsonValidationErrors('helpers');

        $group->refresh();
        $this->assertSame('Grupo Centro', $group->name);
        $this->assertTrue($group->status);
        $this->assertCount(2, $group->helpers);
    }

    public function test_lists_and_opens_group_forms_including_searchable_employee_options(): void
    {
        $group = $this->createGroup();

        $this->get(route('admin.programming.personnel-groups.index'))->assertOk()->assertSee('Lista de Grupos de Personal');
        $this->get(route('admin.programming.personnel-groups.create'))->assertOk()
            ->assertSee('DNI 00000001')->assertSee('data-capacity="3"', false)->assertSee('Días de trabajo');
        $this->get(route('admin.programming.personnel-groups.edit', $group))->assertOk()->assertSee('Grupo Centro');
        $this->getJson(route('admin.programming.personnel-groups.index', ['draw' => 1, 'start' => 0, 'length' => 10]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.name', 'Grupo Centro')
            ->assertJsonPath('data.0.days_label', 'Lunes, Miércoles, Viernes')
            ->assertJsonMissingPath('data.0.driver.password')->assertJsonMissingPath('data.0.helpers.0.password');
    }

    public function test_database_prevents_deleting_a_vehicle_or_employee_referenced_by_a_group(): void
    {
        $this->createGroup();

        foreach ([$this->vehicle, $this->driver, $this->helper, $this->zone, $this->shift] as $record) {
            try {
                $record->delete();
                $this->fail('Se permitió eliminar un recurso referenciado por un grupo.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('FOREIGN KEY', $exception->getMessage());
            }
        }
    }

    public function test_deleting_a_group_cleans_pivot_and_preserves_employees_and_vehicle(): void
    {
        $group = $this->createGroup();

        $this->deleteJson(route('admin.programming.personnel-groups.destroy', $group))->assertOk();

        $this->assertDatabaseCount('personnel_groups', 0);
        $this->assertDatabaseCount('personnel_group_helpers', 0);
        $this->assertDatabaseCount('employees', 3);
        $this->assertDatabaseCount('vehicles', 1);
    }
}
