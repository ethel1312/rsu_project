<?php

namespace Tests\Feature;

use App\Models\{Brand, Brandmodel, Color, Maintenance, MaintenanceDay, MaintenanceSchedule, User, Vehicle, VehicleType};
use App\Models\Employees\{Employee, EmployeeType};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use DatabaseMigrations;

    private Vehicle $vehicle;
    private Employee $employee;

    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']['database.default'] !== 'sqlite' || $app['config']['database.connections.sqlite.database'] !== ':memory:') {
            throw new \RuntimeException('Estas pruebas solo pueden ejecutarse con SQLite en memoria.');
        }
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $brand = Brand::create(['name' => 'Prueba']);
        $this->vehicle = Vehicle::create([
            'name' => 'Vehículo A', 'code' => 'TEST-A', 'plate' => 'TST-001', 'year' => 2025,
            'load_capacity' => 1000, 'fuel_capacity' => 100, 'compact_capacity' => 10, 'occupant_capacity' => 3,
            'type_id' => VehicleType::create(['name' => 'Compactador'])->id,
            'brand_id' => $brand->id, 'model_id' => Brandmodel::create(['name' => 'Modelo', 'brand_id' => $brand->id])->id,
            'color_id' => Color::create(['name' => 'Blanco', 'code' => '#FFFFFF'])->id,
        ]);
        $this->employee = Employee::create([
            'dni' => '12345678', 'first_name' => 'Juan', 'last_name' => 'Pérez', 'birth_date' => '1990-01-01',
            'email' => 'juan@example.test', 'status' => true, 'password' => 'unused', 'address' => 'JLO',
            'employee_type_id' => EmployeeType::create(['name' => 'Conductor'])->id,
        ]);
        Storage::fake('public');
    }

    private function period(): Maintenance
    {
        $this->postJson(route('admin.maintenances.store'), [
            'name' => 'MANT. DICIEMBRE 2025', 'start_date' => '2025-12-01', 'end_date' => '2025-12-31',
        ])->assertOk();
        return Maintenance::latest('id')->first();
    }

    private function data(array $changes = []): array
    {
        return array_merge(['vehicle_id' => $this->vehicle->id, 'employee_id' => $this->employee->id,
            'type' => 'cleaning', 'weekday' => 1, 'start_time' => '08:00', 'end_time' => '11:00'], $changes);
    }

    private function schedule(Maintenance $period): MaintenanceSchedule
    {
        $this->postJson(route('admin.maintenances.schedules.store', $period), $this->data())->assertOk();
        return $period->schedules()->latest('id')->first();
    }

    private function image(string $name): UploadedFile
    {
        // PNG real de 1x1; no requiere la extensión GD para preparar el fixture.
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aK1sAAAAASUVORK5CYII='));
    }

    public function test_period_crud_and_inclusive_overlap_validation(): void
    {
        $period = $this->period();
        foreach ([['2025-12-31', '2026-01-10'], ['2025-11-01', '2026-02-01'], ['2025-12-10', '2025-12-11'], ['2025-12-02', '2025-12-01']] as [$start, $end]) {
            $this->postJson(route('admin.maintenances.store'), ['name' => 'Inválido', 'start_date' => $start, 'end_date' => $end])->assertUnprocessable();
        }
        $this->putJson(route('admin.maintenances.update', $period), ['name' => 'Editado', 'start_date' => '2025-12-01', 'end_date' => '2025-12-31'])->assertOk();
        $this->postJson(route('admin.maintenances.store'), ['name' => 'Enero', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31'])->assertOk();
        $this->deleteJson(route('admin.maintenances.destroy', $period))->assertOk();
        $this->assertDatabaseMissing('maintenances', ['id' => $period->id]);
    }

    public function test_generates_the_five_mondays_from_the_exam_and_regenerates_on_edit(): void
    {
        $period = $this->period();
        $schedule = $this->schedule($period);
        $this->assertSame(['2025-12-01', '2025-12-08', '2025-12-15', '2025-12-22', '2025-12-29'], $schedule->days()->orderBy('date')->pluck('date')->all());
        $this->putJson(route('admin.maintenances.schedules.update', [$period, $schedule]), $this->data(['weekday' => 3]))->assertOk();
        $this->assertSame(['2025-12-03', '2025-12-10', '2025-12-17', '2025-12-24', '2025-12-31'], $schedule->days()->orderBy('date')->pluck('date')->all());
        $this->putJson(route('admin.maintenances.update', $period), ['name' => 'Diciembre', 'start_date' => '2025-12-10', 'end_date' => '2025-12-24'])->assertOk();
        $this->assertSame(['2025-12-10', '2025-12-17', '2025-12-24'], $schedule->days()->orderBy('date')->pluck('date')->all());
    }

    public function test_vehicle_overlap_and_invalid_times_are_rejected_but_adjacent_hours_are_allowed(): void
    {
        $period = $this->period();
        $this->schedule($period);
        foreach ([['10:00', '11:00'], ['07:00', '12:00'], ['08:00', '08:00'], ['12:00', '08:00']] as [$start, $end]) {
            $this->postJson(route('admin.maintenances.schedules.store', $period), $this->data(['start_time' => $start, 'end_time' => $end]))->assertUnprocessable();
        }
        $this->postJson(route('admin.maintenances.schedules.store', $period), $this->data(['start_time' => '11:00', 'end_time' => '12:00']))->assertOk();
        $this->postJson(route('admin.maintenances.schedules.store', $period), $this->data(['weekday' => 2]))->assertOk();
        $other = $this->vehicle->replicate();
        $other->fill(['code' => 'TEST-B', 'plate' => 'TST-002'])->save();
        $this->postJson(route('admin.maintenances.schedules.store', $period), $this->data(['vehicle_id' => $other->id]))->assertOk();
        $this->assertDatabaseCount('maintenance_schedules', 4);
    }

    public function test_schedule_edit_checks_overlaps_and_excludes_itself(): void
    {
        $period = $this->period();
        $first = $this->schedule($period);
        $this->putJson(route('admin.maintenances.schedules.update', [$period, $first]), $this->data())->assertOk();
        $this->postJson(route('admin.maintenances.schedules.store', $period), $this->data(['start_time' => '11:00', 'end_time' => '12:00']))->assertOk();
        $second = $period->schedules()->latest('id')->first();
        $this->putJson(route('admin.maintenances.schedules.update', [$period, $second]), $this->data(['start_time' => '10:00', 'end_time' => '12:00']))->assertUnprocessable();
    }

    public function test_day_tracking_upload_replacement_preservation_and_confirmed_schedule_deletion(): void
    {
        $period = $this->period();
        $schedule = $this->schedule($period);
        $day = $schedule->days()->orderBy('date')->first();
        $url = route('admin.maintenance-days.update', [$schedule, $day]);
        $this->putJson($url, ['observation' => 'Todo conforme', 'is_completed' => 1, 'image' => $this->image('evidencia.png')])->assertOk();
        $path = $day->fresh()->image_path;
        Storage::disk('public')->assertExists($path);
        $this->putJson($url, ['observation' => 'No se realizó', 'is_completed' => 0])->assertOk();
        $this->assertFalse($day->fresh()->is_completed);
        $this->assertSame($path, $day->fresh()->image_path);
        $this->putJson(route('admin.maintenances.schedules.update', [$period, $schedule]), $this->data())->assertOk();
        $this->putJson(route('admin.maintenances.update', $period), ['name' => 'Renombrado', 'start_date' => '2025-12-01', 'end_date' => '2026-01-05'])->assertOk();
        $this->assertSame('No se realizó', $day->fresh()->observation);
        $this->assertSame($path, $day->fresh()->image_path);
        $this->assertSame(6, $schedule->days()->count());
        $this->putJson($url, ['observation' => 'Nueva imagen', 'is_completed' => 1, 'image' => $this->image('nueva.png')])->assertOk();
        Storage::disk('public')->assertMissing($path);
        $path = $day->fresh()->image_path;
        $this->putJson(route('admin.maintenances.update', $period), ['name' => 'Editado', 'start_date' => '2025-12-08', 'end_date' => '2025-12-31'])->assertUnprocessable();
        $this->assertSame('2025-12-01', $period->fresh()->start_date);
        $this->putJson(route('admin.maintenances.schedules.update', [$period, $schedule]), $this->data(['weekday' => 2]))->assertUnprocessable();
        $this->deleteJson(route('admin.maintenances.destroy', $period))->assertConflict();
        $this->deleteJson(route('admin.maintenances.schedules.destroy', [$period, $schedule]))->assertOk();
        $this->assertDatabaseCount('maintenance_days', 0);
        Storage::disk('public')->assertMissing($path);
        $this->deleteJson(route('admin.maintenances.destroy', $period))->assertOk();
    }

    public function test_forms_and_ajax_lists_render_and_nested_records_are_scoped(): void
    {
        $period = $this->period();
        $schedule = $this->schedule($period);
        $day = $schedule->days()->first();
        foreach ([route('admin.maintenances.index'), route('admin.maintenances.create'), route('admin.maintenances.edit', $period),
            route('admin.maintenances.schedules.index', $period), route('admin.maintenances.schedules.create', $period),
            route('admin.maintenances.schedules.edit', [$period, $schedule]), route('admin.maintenance-days.index', $schedule),
            route('admin.maintenance-days.edit', [$schedule, $day])] as $url) {
            $this->get($url)->assertOk();
        }
        foreach ([route('admin.maintenances.index'), route('admin.maintenances.schedules.index', $period), route('admin.maintenance-days.index', $schedule)] as $url) {
            $response = $this->getJson($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertJsonStructure(['data', 'recordsTotal']);
            $this->assertArrayNotHasKey('error', $response->json(), $response->json('error') ?? '');
        }
        $other = Maintenance::create(['name' => 'Otro', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $this->putJson(route('admin.maintenances.schedules.update', [$other, $schedule]), $this->data())->assertNotFound();
        $this->deleteJson(route('admin.maintenances.schedules.destroy', [$other, $schedule]))->assertNotFound();
        $otherSchedule = $this->schedule($other);
        $this->putJson(route('admin.maintenance-days.update', [$otherSchedule, $day]), ['is_completed' => 1])->assertNotFound();
        auth()->logout();
        $this->getJson(route('admin.maintenances.index'))->assertUnauthorized();
        $this->postJson(route('admin.maintenances.schedules.store', $period), $this->data())->assertUnauthorized();
        $this->putJson(route('admin.maintenance-days.update', [$schedule, $day]), ['is_completed' => 1])->assertUnauthorized();
    }

    public function test_rejects_invalid_references_images_and_weekday_outside_period(): void
    {
        $period = $this->period();
        foreach ([['weekday' => 8], ['vehicle_id' => 99999], ['employee_id' => 99999], ['type' => 'invalid']] as $changes) {
            $this->postJson(route('admin.maintenances.schedules.store', $period), $this->data($changes))->assertUnprocessable();
        }
        $schedule = $this->schedule($period);
        $day = $schedule->days()->first();
        $this->putJson(route('admin.maintenance-days.update', [$schedule, $day]), ['image' => UploadedFile::fake()->create('script.txt', 2, 'text/plain')])->assertUnprocessable();
        $short = Maintenance::create(['name' => 'Un día', 'start_date' => '2026-01-01', 'end_date' => '2026-01-01']);
        $this->postJson(route('admin.maintenances.schedules.store', $short), $this->data())->assertUnprocessable();
    }

    public function test_datatables_search_and_sort_with_the_browser_column_names(): void
    {
        $period = $this->period();
        $this->schedule($period);
        $columns = [
            ['data' => 'day_label', 'name' => 'weekday', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'vehicle_label', 'name' => 'vehicle.plate', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'employee_label', 'name' => 'employee.first_name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'type_label', 'name' => 'type', 'searchable' => 'true', 'orderable' => 'true'],
        ];
        foreach (['TST-001', 'Juan'] as $search) {
            $query = http_build_query(['draw' => 1, 'start' => 0, 'length' => 10, 'columns' => $columns,
                'search' => ['value' => $search, 'regex' => 'false'], 'order' => [['column' => 1, 'dir' => 'asc']]]);
            $response = $this->getJson(route('admin.maintenances.schedules.index', $period).'?'.$query, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
            $this->assertArrayNotHasKey('error', $response->json(), $response->json('error') ?? '');
            $response->assertJsonPath('recordsFiltered', 1);
        }
    }
}
