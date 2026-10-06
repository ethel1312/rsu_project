<?php

namespace Tests\Feature\Employees;

use App\Models\Employees\Attendance;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function migrateFreshUsing(): array
    {
        // Solo las dependencias del módulo. El repositorio contiene además una
        // migración de personal que intenta añadir is_default por segunda vez.
        return ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_09_19_223236_add_two_factor_columns_to_users_table.php',
            'database/migrations/2026_10_05_220523_create_employee_types_table.php',
            'database/migrations/2026_10_05_220605_create_employees_table.php',
            'database/migrations/2026_10_06_172605_create_attendances_table.php',
        ]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDateTime(2026, 10, 7, 2, 30, 0)); // 6 de octubre, 21:30 en Lima.
        $type = EmployeeType::create(['name' => 'Conductor', 'description' => 'Conductor']);
        $this->employee = Employee::create([
            'dni' => '01234567', 'first_name' => 'Ana', 'last_name' => 'Pérez',
            'birth_date' => '1990-01-01', 'email' => 'ana@example.test', 'status' => true,
            'password' => Hash::make('secreto123'), 'address' => 'Chiclayo', 'employee_type_id' => $type->id,
        ]);
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function data(string $time = '08:00', array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id, 'date' => '2026-10-06',
            'time' => $time, 'status' => 'present', 'notes' => null,
        ], $overrides);
    }

    private function createAttendance(string $time = '08:00', array $overrides = []): Attendance
    {
        $this->postJson(route('admin.attendances.store'), $this->data($time, $overrides))->assertOk();

        return Attendance::latest('id')->firstOrFail();
    }

    public function test_admin_endpoints_require_authentication_but_clock_is_public(): void
    {
        $this->get(route('admin.attendances.index'))->assertRedirect(route('login'));
        $this->postJson(route('admin.attendances.store'), $this->data())->assertUnauthorized();
        $this->getJson(route('admin.attendances.create'))->assertUnauthorized();
        $this->getJson(route('admin.attendances.preview', $this->data()))->assertUnauthorized();
        $this->getJson(route('admin.attendances.employees', ['q' => 'Ana']))->assertUnauthorized();
        $this->get(route('employees.attendances.clock'))->assertOk()->assertSee('Marcación de asistencia');
    }

    public function test_list_and_forms_render_and_default_to_lima_date(): void
    {
        $this->admin();
        $today = $this->createAttendance();
        $this->createAttendance('09:00', ['date' => '2026-10-05']);
        $this->createAttendance('09:00', ['date' => '2026-10-07']);
        $this->get(route('admin.attendances.index'))->assertOk()->assertSee('value="2026-10-06"', false);
        $this->get(route('admin.attendances.create'))->assertOk()->assertSee('21:30:00')->assertDontSee('01234567');
        $this->get(route('admin.attendances.edit', $today))->assertOk()->assertSee('Actualizar Asistencia')->assertSee('01234567');
        $this->getJson(route('admin.attendances.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.id', $today->id);
        foreach (['2026-10-05', '2026-10-07'] as $date) {
            $this->getJson(route('admin.attendances.index', ['date' => $date]), ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()->assertJsonPath('recordsTotal', 1);
        }
    }

    public function test_marks_alternate_chronologically_and_restart_each_day(): void
    {
        $this->admin();
        foreach (['17:00', '08:00', '14:00', '12:00'] as $time) {
            $this->createAttendance($time, ['type' => 'exit']); // Nunca confiar en el tipo enviado.
        }
        $this->assertSame(['entry', 'exit', 'entry', 'exit'], Attendance::orderBy('time')->pluck('type')->all());
        $next = $this->createAttendance('08:00', ['date' => '2026-10-07']);
        $this->assertSame('entry', $next->type);
        $other = $this->employee->replicate();
        $other->fill(['dni' => '87654321', 'email' => 'otro@example.test'])->save();
        $this->assertSame('entry', $this->createAttendance('10:00', ['employee_id' => $other->id])->type);
    }

    public function test_edits_deletes_and_absences_recalculate_the_sequence(): void
    {
        $this->admin();
        $first = $this->createAttendance('08:00');
        $second = $this->createAttendance('12:00');
        $third = $this->createAttendance('14:00');
        $this->putJson(route('admin.attendances.update', $first), $this->data('15:00'))->assertOk();
        $this->assertSame('entry', $second->refresh()->type);
        $this->assertSame('exit', $third->refresh()->type);
        $this->assertSame('entry', $first->refresh()->type);
        $this->putJson(route('admin.attendances.update', $second), $this->data('12:00', ['status' => 'absent']))->assertOk();
        $this->assertNull($second->refresh()->type);
        $this->assertSame('entry', $third->refresh()->type);
        $this->assertSame('exit', $first->refresh()->type);
        $this->deleteJson(route('admin.attendances.destroy', $third))->assertOk();
        $this->assertSame('entry', $first->refresh()->type);
        $this->assertDatabaseMissing('attendances', ['id' => $third->id]);
    }

    public function test_moving_a_record_to_another_person_and_date_repairs_both_groups(): void
    {
        $this->admin();
        $first = $this->createAttendance();
        $second = $this->createAttendance('12:00');
        $other = $this->employee->replicate();
        $other->fill(['dni' => '87654321', 'email' => 'otro@example.test'])->save();
        $destination = $this->createAttendance('12:00', ['employee_id' => $other->id, 'date' => '2026-10-07']);
        $this->putJson(route('admin.attendances.update', $first), $this->data('08:00', [
            'employee_id' => $other->id, 'date' => '2026-10-07',
        ]))->assertOk();
        $this->assertSame('entry', $second->refresh()->type);
        $this->assertSame('exit', $destination->refresh()->type);
    }

    public function test_duplicate_creation_and_updates_are_rejected_without_changing_records(): void
    {
        $this->admin();
        $this->createAttendance();
        $second = $this->createAttendance('12:00');
        $this->postJson(route('admin.attendances.store'), $this->data('08:00:00'))->assertUnprocessable()->assertJsonValidationErrors('time');
        $this->putJson(route('admin.attendances.update', $second), $this->data())->assertUnprocessable()->assertJsonValidationErrors('time');
        $this->assertDatabaseCount('attendances', 2);
        $this->assertSame('12:00:00', $second->refresh()->time);
    }

    public function test_invalid_fields_and_missing_records_have_correct_responses(): void
    {
        $this->admin();
        $this->postJson(route('admin.attendances.store'), [
            'employee_id' => 9999, 'date' => '2026-02-30', 'time' => '25:70',
            'status' => 'invalid', 'notes' => str_repeat('x', 1001),
        ])->assertUnprocessable()->assertJsonValidationErrors(['employee_id', 'date', 'time', 'status', 'notes']);
        $this->getJson(route('admin.attendances.index', ['date' => 'wrong']))->assertUnprocessable();
        $this->getJson(route('admin.attendances.edit', 9999))->assertNotFound();
        $this->putJson(route('admin.attendances.update', 9999), $this->data())->assertNotFound();
        $this->deleteJson(route('admin.attendances.destroy', 9999))->assertNotFound();
    }

    public function test_preview_excludes_the_edited_record_and_absences(): void
    {
        $this->admin();
        $first = $this->createAttendance();
        $this->createAttendance('09:00', ['status' => 'absent']);
        $this->getJson(route('admin.attendances.preview', $this->data('10:00')))->assertOk()->assertJsonPath('type', 'Salida');
        $this->getJson(route('admin.attendances.preview', $this->data('10:00', ['attendance_id' => $first->id])))
            ->assertOk()->assertJsonPath('type', 'Ingreso');
        $this->getJson(route('admin.attendances.preview', $this->data('10:00', ['status' => 'absent'])))
            ->assertOk()->assertJsonPath('type', 'No aplica (ausencia)');
    }

    public function test_datatable_searches_personal_and_escapes_notes(): void
    {
        $this->admin();
        $this->createAttendance('08:00', ['notes' => '<script>alert(1)</script>']);
        $this->getJson(route('admin.attendances.index', ['search' => ['value' => '01234567', 'regex' => false],
            'columns' => [['data' => 'dni', 'name' => 'employees.dni', 'searchable' => 'true', 'orderable' => 'true']],
        ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.notes', '&lt;script&gt;alert(1)&lt;/script&gt;');
    }

    public function test_clock_uses_server_time_and_does_not_log_in_as_admin(): void
    {
        $data = ['dni' => $this->employee->dni, 'password' => 'secreto123',
            'date' => '2000-01-01', 'time' => '01:00', 'status' => 'absent', 'type' => 'exit', 'employee_id' => 999];
        $this->post(route('employees.attendances.mark'), $data)->assertRedirect(route('employees.attendances.clock'))
            ->assertSessionHas('status', 'Ingreso registrado exitosamente el 06/10/2026 a las 21:30:00 (hora de Lima).');
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee->id, 'date' => '2026-10-06', 'time' => '21:30:00', 'status' => 'present', 'type' => 'entry',
        ]);
        $this->assertGuest();
        $this->travel(1)->minutes();
        $this->post(route('employees.attendances.mark'), $data)->assertSessionHasNoErrors();
        $this->assertSame('exit', Attendance::latest('id')->first()->type);
    }

    public function test_clock_accepts_existing_passwords_and_upgrades_them_to_hashes(): void
    {
        $this->employee->update(['password' => 'legacy123']);
        $this->post(route('employees.attendances.mark'), ['dni' => $this->employee->dni, 'password' => 'legacy123'])
            ->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertTrue(Hash::check('legacy123', $this->employee->refresh()->password));
    }

    public function test_clock_rejects_bad_credentials_inactive_personal_and_same_second_duplicates(): void
    {
        $this->postJson(route('employees.attendances.mark'), ['dni' => $this->employee->dni, 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('dni');
        $this->postJson(route('employees.attendances.mark'), ['dni' => '99999999', 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('dni');
        $this->employee->update(['status' => false]);
        $data = ['dni' => $this->employee->dni, 'password' => 'secreto123'];
        $this->postJson(route('employees.attendances.mark'), $data)->assertUnprocessable();
        $this->assertDatabaseCount('attendances', 0);
        $this->employee->update(['status' => true]);
        $this->post(route('employees.attendances.mark'), $data)->assertSessionHasNoErrors();
        $this->postJson(route('employees.attendances.mark'), $data)->assertUnprocessable()->assertJsonValidationErrors('time');
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_clock_limits_credential_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('employees.attendances.mark'), ['dni' => $this->employee->dni, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson(route('employees.attendances.mark'), ['dni' => $this->employee->dni, 'password' => 'secreto123'])
            ->assertUnprocessable()->assertJsonPath('errors.dni.0', 'Demasiados intentos. Intente nuevamente en 60 segundos.');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_migration_accepts_an_existing_compatible_table_without_losing_records(): void
    {
        $this->admin();
        $record = $this->createAttendance();
        (require database_path('migrations/2026_10_06_172605_create_attendances_table.php'))->up();
        $this->assertDatabaseHas('attendances', ['id' => $record->id, 'type' => 'entry']);
    }

    public function test_personal_search_matches_dni_and_combined_names_without_exposing_private_fields(): void
    {
        $this->admin();
        foreach (['0123', 'Ana', 'Pérez', 'Ana Pérez', 'Pérez Ana', '  Ana   Pérez  '] as $term) {
            $this->getJson(route('admin.attendances.employees', ['q' => $term]))->assertOk()->assertExactJson([
                'results' => [['id' => $this->employee->id, 'text' => '01234567 - Pérez, Ana']],
                'pagination' => ['more' => false],
            ]);
        }
        foreach (['', 'A', 'ZZZZ', '%%', '__'] as $term) {
            $this->getJson(route('admin.attendances.employees', ['q' => $term]))->assertOk()->assertExactJson([
                'results' => [], 'pagination' => ['more' => false],
            ]);
        }
        $this->getJson(route('admin.attendances.employees', ['q' => str_repeat('a', 101), 'page' => -1]))
            ->assertUnprocessable()->assertJsonValidationErrors(['q', 'page']);
    }

    public function test_personal_search_paginates_and_edit_only_preloads_the_selected_person(): void
    {
        $this->admin();
        $rows = [];
        for ($i = 1; $i <= 41; $i++) {
            $rows[] = array_merge($this->employee->getAttributes(), [
                'id' => $this->employee->id + $i, 'dni' => (string) (10000000 + $i),
                'first_name' => sprintf('Persona %03d', $i), 'email' => 'persona'.$i.'@example.test',
            ]);
        }
        Employee::insert($rows);
        $ids = [];
        foreach ([1 => 20, 2 => 20, 3 => 2] as $page => $count) {
            $response = $this->getJson(route('admin.attendances.employees', ['q' => 'Pérez', 'page' => $page]))
                ->assertOk()->assertJsonCount($count, 'results')->assertJsonPath('pagination.more', $page < 3);
            $ids = array_merge($ids, array_column($response->json('results'), 'id'));
        }
        $this->assertCount(42, array_unique($ids));
        $attendance = $this->createAttendance();
        $this->get(route('admin.attendances.edit', $attendance))->assertOk()->assertSee('01234567')->assertDontSee('10000001');
        $this->get(route('admin.attendances.create'))->assertOk()->assertDontSee('10000001')->assertDontSee('01234567');
    }
}
