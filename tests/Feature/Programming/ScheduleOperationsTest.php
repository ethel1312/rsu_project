<?php

namespace Tests\Feature\Programming;

use App\Models\Employees\Attendance;
use App\Models\Employees\Employee;
use App\Models\Programming\ChangeReason;
use App\Models\Programming\Holiday;
use App\Models\Programming\PersonnelGroup;
use App\Models\Programming\ProgrammingSchedule;
use App\Models\Programming\ScheduleChange;
use App\Models\Programming\ScheduleRun;
use App\Models\Programming\Shift;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Programming\ScheduleChangeService;
use Carbon\Carbon;
use Database\Seeders\ProgrammingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleOperationsTest extends TestCase
{
    use RefreshDatabase;

    private ProgrammingSchedule $schedule;

    private ChangeReason $reason;

    private Employee $replacement;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2027-03-01 05:00:00', 'America/Lima'));
        $this->actingAs(User::factory()->create());
        $this->seed(ProgrammingDemoSeeder::class);
        $group = PersonnelGroup::with(['shift', 'zone', 'vehicle', 'driver', 'helpers'])->where('name', 'Grupo Moshoqueque (demo)')->firstOrFail();
        $this->schedule = ProgrammingSchedule::create([
            'personnel_group_id' => $group->id, 'group_name' => $group->name,
            'shift_id' => $group->shift_id, 'shift_name' => $group->shift->name, 'shift_start_time' => $group->shift->start_time, 'shift_end_time' => $group->shift->end_time,
            'zone_id' => $group->zone_id, 'zone_name' => $group->zone->name, 'vehicle_id' => $group->vehicle_id,
            'vehicle_label' => $group->vehicle->plate.' · '.$group->vehicle->name, 'driver_id' => $group->driver_id, 'driver_name' => $group->driver->full_name,
            'helper_ids' => $group->helpers->modelKeys(), 'helper_names' => $group->helpers->map->full_name->all(),
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'start_date' => '2027-03-01', 'end_date' => '2027-03-05', 'status' => 'Programada',
        ]);
        $this->reason = ChangeReason::create(['name' => 'Imprevistos', 'description' => 'Atención de una incidencia operativa.']);
        $this->replacement = Employee::where('email', 'demo.conductor3@example.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function present(int $id): void
    {
        Attendance::create(['employee_id' => $id, 'date' => '2027-03-01', 'time' => '04:30:00', 'type' => 'entry', 'status' => 'present']);
    }

    private function edit(array $overrides = []): array
    {
        return array_merge(['date' => '2027-03-01', 'type' => 'driver', 'old_id' => $this->schedule->driver_id, 'new_id' => $this->replacement->id, 'reason_id' => $this->reason->id, 'detail' => 'Reemplazo por inasistencia.'], $overrides);
    }

    private function mass(array $overrides = []): array
    {
        return array_merge($this->edit(), ['start_date' => '2027-03-02', 'end_date' => '2027-03-03', 'zone_id' => $this->schedule->zone_id], $overrides);
    }

    public function test_all_new_pages_render_and_require_authentication(): void
    {
        foreach (['dashboard.index', 'reasons.index', 'reasons.create', 'changes.index', 'changes.create'] as $name) {
            $response = $this->get(route('admin.programming.'.$name))->assertOk();
            if (getenv('RSU_QA_RENDER')) {
                if (! is_dir(base_path('tmp/qa'))) {
                    mkdir(base_path('tmp/qa'), 0777, true);
                }
                file_put_contents(base_path('tmp/qa/'.$name.'.html'), $response->getContent());
            }
        }
        $this->get(route('admin.programming.dashboard.show', ['schedule' => $this->schedule, 'date' => '2027-03-01']))->assertOk();
        $this->get(route('admin.programming.schedules.route', ['schedule' => $this->schedule, 'date' => '2027-03-01']))->assertOk()->assertSee('Todavía no hay un recorrido');
        $this->get(route('admin.programming.schedules.edit', ['schedule' => $this->schedule, 'date' => '2027-03-01']))->assertOk()->assertSee('Imprevistos');
        auth()->logout();
        $this->get(route('admin.programming.dashboard.index'))->assertRedirect(route('login'));
    }

    public function test_reason_crud_validates_and_preserves_previous_reason_snapshots(): void
    {
        $this->post(route('admin.programming.reasons.store'), ['name' => '', 'description' => ''])->assertSessionHasErrors(['name', 'description']);
        $this->post(route('admin.programming.reasons.store'), ['name' => 'Mantenimiento', 'description' => 'Revisión del vehículo'])->assertRedirect();
        $this->put(route('admin.programming.reasons.update', $this->reason), ['name' => 'Emergencia', 'description' => 'Incidencia imprevista'])->assertRedirect();
        $this->present($this->replacement->id);
        $this->put(route('admin.programming.dashboard.update', $this->schedule), $this->edit())->assertRedirect();
        $change = ScheduleChange::firstOrFail();
        $this->delete(route('admin.programming.reasons.destroy', $this->reason))->assertRedirect();
        $this->assertSoftDeleted('change_reasons', ['id' => $this->reason->id]);
        $this->assertSame('Emergencia', $change->fresh()->reason_name);
        $this->postJson(route('admin.programming.changes.preview'), $this->mass())->assertUnprocessable();
    }

    public function test_dashboard_rejects_unattended_replacement_and_present_original(): void
    {
        $url = route('admin.programming.dashboard.update', $this->schedule);
        $this->putJson($url, $this->edit())->assertUnprocessable()->assertJsonValidationErrors('change');
        $this->present($this->replacement->id);
        $this->present($this->schedule->driver_id);
        $this->putJson($url, $this->edit())->assertUnprocessable()->assertJsonValidationErrors('change');
        $this->assertDatabaseCount('schedule_changes', 0);
    }

    public function test_dashboard_rejects_busy_replacement_even_if_other_route_is_finished(): void
    {
        $this->present($this->replacement->id);
        $other = $this->schedule->replicate();
        $other->driver_id = $this->replacement->id;
        $other->status = 'Finalizada';
        $other->save();
        $this->putJson(route('admin.programming.dashboard.update', $this->schedule), $this->edit())->assertUnprocessable();
        $this->assertDatabaseCount('schedule_changes', 0);
    }

    public function test_dashboard_changes_only_today_and_preserves_other_dates_and_history(): void
    {
        $this->present($this->replacement->id);
        $url = route('admin.programming.dashboard.update', $this->schedule);
        $this->putJson($url, $this->edit(['date' => '2027-03-02']))->assertUnprocessable();
        $this->put($url, $this->edit())->assertRedirect();
        $change = ScheduleChange::firstOrFail();
        $day = $change->schedule;
        $this->assertSame('dashboard', $change->source);
        $this->assertEquals($this->replacement->id, $day->driver_id);
        $this->assertSame('2027-03-01', $day->start_date->toDateString());
        $this->assertFalse($this->schedule->fresh()->runsOn('2027-03-01'));
        $this->assertTrue($this->schedule->fresh()->runsOn('2027-03-02'));
        $this->assertDatabaseHas('programming_schedule_histories', ['programming_schedule_id' => $day->id, 'action' => 'updated']);
        $this->get(route('admin.programming.schedules.history', $day))->assertOk()->assertSee('Reemplazo por inasistencia');
        $this->get(route('admin.programming.dashboard.index'))->assertOk()->assertSee('Cambios en las programaciones');
        $this->get(route('admin.programming.changes.show', $change))->assertOk()->assertSee($this->replacement->full_name);
    }

    public function test_started_or_past_start_routes_cannot_be_changed(): void
    {
        $this->present($this->replacement->id);
        $this->schedule->runs()->create(['service_date' => '2027-03-01', 'started_at' => now()]);
        $this->putJson(route('admin.programming.dashboard.update', $this->schedule), $this->edit())->assertUnprocessable();
        $this->schedule->runs()->delete();
        Carbon::setTestNow(Carbon::parse('2027-03-01 06:00', 'America/Lima'));
        $this->putJson(route('admin.programming.dashboard.update', $this->schedule), $this->edit())->assertUnprocessable();
    }

    public function test_mass_preview_has_no_side_effects_and_commit_records_every_day(): void
    {
        $data = $this->mass();
        $preview = $this->postJson(route('admin.programming.changes.preview'), $data)->assertOk()->assertJsonPath('count', 2);
        $this->assertDatabaseCount('schedule_changes', 0);
        $this->assertDatabaseCount('programming_schedules', 1);
        $this->postJson(route('admin.programming.changes.store'), $data + ['confirmed' => 1, 'preview_token' => $preview->json('token')])->assertOk();
        $changes = ScheduleChange::all();
        $this->assertCount(2, $changes);
        $this->assertSame(1, $changes->pluck('operation_uuid')->unique()->count());
        foreach ($changes as $change) {
            $this->assertSame('mass', $change->source);
            $this->assertDatabaseHas('programming_schedule_histories', ['programming_schedule_id' => $change->programming_schedule_id, 'action' => 'updated']);
        }
        $this->assertEquals($this->edit()['old_id'], $this->schedule->fresh()->driver_id);
        $this->assertFalse($this->schedule->fresh()->runsOn('2027-03-02'));
    }

    public function test_mass_operation_is_atomic_when_one_day_conflicts(): void
    {
        $other = $this->schedule->replicate();
        $other->driver_id = $this->replacement->id;
        $other->start_date = '2027-03-03';
        $other->end_date = '2027-03-03';
        $other->save();
        $this->postJson(route('admin.programming.changes.preview'), $this->mass())->assertUnprocessable();
        $this->assertDatabaseCount('schedule_changes', 0);
        $this->assertDatabaseCount('programming_schedules', 2);
        $this->assertEmpty($this->schedule->fresh()->excluded_dates);
    }

    public function test_mass_confirmation_is_bound_to_preview_and_current_data(): void
    {
        $data = $this->mass();
        $preview = $this->postJson(route('admin.programming.changes.preview'), $data)->assertOk();
        $this->postJson(route('admin.programming.changes.store'), $data)->assertUnprocessable();
        $this->schedule->update(['driver_name' => 'Nombre actualizado']);
        $this->postJson(route('admin.programming.changes.store'), $data + ['confirmed' => 1, 'preview_token' => $preview->json('token')])->assertUnprocessable();
        $this->assertDatabaseCount('schedule_changes', 0);
    }

    public function test_revert_restores_original_and_rejects_repeated_reversal(): void
    {
        $this->present($this->replacement->id);
        $this->put(route('admin.programming.dashboard.update', $this->schedule), $this->edit())->assertRedirect();
        $change = ScheduleChange::firstOrFail();
        $this->delete(route('admin.programming.changes.destroy', $change))->assertRedirect();
        $this->assertEquals($this->schedule->driver_id, $change->schedule->fresh()->driver_id);
        $this->assertNotNull($change->fresh()->reverted_at);
        $this->assertDatabaseHas('programming_schedule_histories', ['programming_schedule_id' => $change->programming_schedule_id, 'action' => 'reverted']);
        $this->deleteJson(route('admin.programming.changes.destroy', $change))->assertUnprocessable();
    }

    public function test_revert_does_not_overwrite_a_later_change(): void
    {
        $service = app(ScheduleChangeService::class);
        $day = DB::transaction(fn () => $service->applyDay($this->schedule, '2027-03-02', [$this->edit()], 'individual'));
        $first = ScheduleChange::first();
        $next = Employee::where('email', 'demo.conductor4@example.test')->firstOrFail();
        DB::transaction(fn () => $service->applyDay($day, '2027-03-02', [$this->edit(['old_id' => $this->replacement->id, 'new_id' => $next->id])], 'individual'));
        $this->deleteJson(route('admin.programming.changes.destroy', $first))->assertUnprocessable();
        $this->assertEquals($next->id, $day->fresh()->driver_id);
    }

    public function test_route_finishes_only_selected_date_and_keeps_recorded_path(): void
    {
        $start = route('admin.programming.schedules.start', $this->schedule);
        $this->postJson($start, ['date' => '2027-03-01'])->assertUnprocessable();
        foreach (array_merge([$this->schedule->driver_id], $this->schedule->helper_ids) as $id) {
            $this->present($id);
        }
        $this->post($start, ['date' => '2027-03-01'])->assertRedirect();
        $path = [[-6.76, -79.84], [-6.77, -79.85]];
        $this->patch(route('admin.programming.schedules.finish', $this->schedule), ['date' => '2027-03-01', 'route_points' => json_encode($path)])->assertRedirect();
        $run = ScheduleRun::firstOrFail();
        $this->assertSame($path, $run->route_points);
        $this->assertNotNull($run->finished_at);
        $this->assertSame('Programada', $this->schedule->fresh()->status);
        $this->get(route('admin.programming.schedules.route', ['schedule' => $this->schedule, 'date' => '2027-03-01']))->assertOk()->assertSee('Recorrido finalizado');
        $this->patchJson(route('admin.programming.schedules.finish', $this->schedule), ['date' => '2027-03-01', 'route_points' => json_encode($path)])->assertUnprocessable();
        $this->getJson(route('admin.programming.schedules.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    }

    public function test_individual_and_mass_creation_share_crew_and_capacity_rules(): void
    {
        $group = PersonnelGroup::findOrFail($this->schedule->personnel_group_id);
        $individual = ['mode' => 'individual', 'group_id' => $group->id, 'driver_id' => $this->schedule->driver_id, 'helper_ids' => $this->schedule->helper_ids, 'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'start_date' => '2027-03-08', 'end_date' => '2027-03-08'];
        $mass = ['mode' => 'mass', 'group_ids' => [$group->id], 'groups' => [$group->id => ['driver_id' => $this->schedule->driver_id, 'helper_ids' => $this->schedule->helper_ids]], 'start_date' => '2027-03-08', 'end_date' => '2027-03-08'];
        foreach ([$individual, $mass] as $data) {
            $this->postJson(route('admin.programming.schedules.availability'), $data)->assertOk()->assertJsonPath('available', true);
        }
        Employee::find($this->schedule->driver_id)->update(['status' => false]);
        foreach ([$individual, $mass] as $data) {
            $this->postJson(route('admin.programming.schedules.availability'), $data)->assertUnprocessable();
        }
    }

    public function test_individual_editor_registers_catalog_reason_and_rejects_unlisted_reason(): void
    {
        $payload = ['type' => 'driver', 'fromValue' => $this->schedule->driver_id, 'newValue' => $this->replacement->id, 'reasonId' => $this->reason->id, 'description' => 'Cambio individual'];
        $this->putJson(route('admin.programming.schedules.update', $this->schedule), ['service_date' => '2027-03-02', 'changes_payload' => json_encode([$payload])])->assertOk();
        $this->assertDatabaseHas('schedule_changes', ['source' => 'individual', 'reason_name' => 'Imprevistos']);
        $payload['reasonId'] = 9999;
        $this->putJson(route('admin.programming.schedules.update', $this->schedule), ['service_date' => '2027-03-03', 'changes_payload' => json_encode([$payload])])->assertUnprocessable();
    }

    public function test_holiday_configuration_is_identical_for_individual_and_mass(): void
    {
        $group = PersonnelGroup::findOrFail($this->schedule->personnel_group_id);
        $holiday = Holiday::create(['date' => '2027-03-08', 'description' => 'Feriado de prueba', 'status' => true]);
        $common = ['start_date' => '2027-03-08', 'end_date' => '2027-03-08'];
        $crew = ['driver_id' => $this->schedule->driver_id, 'helper_ids' => $this->schedule->helper_ids];
        $individual = $common + $crew + ['mode' => 'individual', 'group_id' => $group->id, 'days_of_week' => [1]];
        $mass = $common + ['mode' => 'mass', 'group_ids' => [$group->id], 'groups' => [$group->id => $crew]];
        foreach ([$individual, $mass] as $data) {
            $this->postJson(route('admin.programming.schedules.availability'), $data)->assertStatus(409);
            $this->postJson(route('admin.programming.schedules.availability'), $data + ['holidays_configured' => 1, 'holiday_ids' => []])->assertOk();
        }
    }

    public function test_shift_vehicle_and_helper_changes_can_be_applied_and_reverted(): void
    {
        $nextShift = Shift::where('name', 'Tarde (demo)')->firstOrFail();
        $nextVehicle = Vehicle::where('code', 'DEMO-002')->firstOrFail();
        $nextHelper = Employee::where('email', 'demo.ayudante3@example.test')->firstOrFail();
        foreach ([
            ['shift', $this->schedule->shift_id, $nextShift->id],
            ['vehicle', $this->schedule->vehicle_id, $nextVehicle->id],
            ['helper', $this->schedule->helper_ids[0], $nextHelper->id],
        ] as [$type,$old,$new]) {
            $data = $this->mass(['type' => $type, 'old_id' => $old, 'new_id' => $new, 'start_date' => '2027-03-04', 'end_date' => '2027-03-04']);
            $preview = $this->postJson(route('admin.programming.changes.preview'), $data)->assertOk();
            $this->postJson(route('admin.programming.changes.store'), $data + ['confirmed' => 1, 'preview_token' => $preview->json('token')])->assertOk();
            $change = ScheduleChange::latest('id')->firstOrFail();
            $this->delete(route('admin.programming.changes.destroy', $change))->assertRedirect();
            $this->assertEquals($change->before_data, $change->schedule->fresh()->only(ScheduleChangeService::FIELDS[$type]));
        }
    }

    public function test_reversal_rejects_new_resource_conflict_without_losing_audit(): void
    {
        $service = app(ScheduleChangeService::class);
        DB::transaction(fn () => $service->applyDay($this->schedule, '2027-03-02', [$this->edit()], 'individual'));
        $change = ScheduleChange::firstOrFail();
        $other = $this->schedule->replicate();
        $other->start_date = '2027-03-02';
        $other->end_date = '2027-03-02';
        $other->excluded_dates = [];
        $other->save();
        $this->deleteJson(route('admin.programming.changes.destroy', $change))->assertUnprocessable();
        $this->assertNull($change->fresh()->reverted_at);
        $this->assertEquals($this->replacement->id, $change->schedule->fresh()->driver_id);
    }

    public function test_overnight_shift_change_checks_adjacent_day_of_same_period(): void
    {
        $night = Shift::create(['name' => 'Noche extendida', 'start_time' => '23:00', 'end_time' => '08:00']);
        $data = $this->mass(['type' => 'shift', 'old_id' => $this->schedule->shift_id, 'new_id' => $night->id, 'start_date' => '2027-03-02', 'end_date' => '2027-03-02']);
        $this->postJson(route('admin.programming.changes.preview'), $data)->assertUnprocessable();
        $this->assertDatabaseCount('schedule_changes',0);
    }

    public function test_route_rejects_invalid_coordinates_without_finishing(): void
    {
        $this->schedule->runs()->create(['service_date' => '2027-03-01', 'started_at' => now()]);
        $this->patchJson(route('admin.programming.schedules.finish',$this->schedule),['date' => '2027-03-01', 'route_points' => '[[91,0],[0,181]]'])->assertUnprocessable();
        $this->assertNull(ScheduleRun::first()->finished_at);
    }
}
