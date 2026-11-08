<?php

namespace Tests\Feature;

use App\Models\Employees\Attendance;
use App\Models\Programming\PersonnelGroup;
use App\Models\Programming\ProgrammingSchedule;
use Carbon\Carbon;
use Database\Seeders\ProgrammingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_profiles_share_schedule_attendance_live_track_history_and_rating(): void
    {
        $this->travelTo(Carbon::parse('2027-03-01 09:00:00', 'America/Lima'));
        $this->seed(ProgrammingDemoSeeder::class);
        $g = PersonnelGroup::with(['shift', 'zone', 'vehicle', 'driver', 'helpers'])->firstOrFail();
        $g->driver->update(['password' => Hash::make('ClavePrueba123')]);
        $schedule = ProgrammingSchedule::create([
            'personnel_group_id' => $g->id, 'group_name' => $g->name, 'shift_id' => $g->shift_id, 'shift_name' => $g->shift->name,
            'shift_start_time' => $g->shift->start_time, 'shift_end_time' => $g->shift->end_time, 'zone_id' => $g->zone_id,
            'zone_name' => $g->zone->name, 'vehicle_id' => $g->vehicle_id, 'vehicle_label' => $g->vehicle->plate,
            'driver_id' => $g->driver_id, 'driver_name' => $g->driver->full_name, 'helper_ids' => $g->helpers->modelKeys(),
            'helper_names' => $g->helpers->map->full_name->all(), 'days_of_week' => [1], 'start_date' => '2027-03-01', 'end_date' => '2027-03-15', 'status' => 'Programada',
        ]);
        $api = '/api/mobile';
        $path = "$api/schedules/$schedule->id/2027-03-01";
        $this->getJson($path)->assertUnauthorized();
        $driver = ['role' => 'driver', 'email' => $g->driver->email, 'password' => 'ClavePrueba123'];
        $citizen = ['role' => 'citizen', 'email' => 'vecino@example.test', 'password' => 'ClaveCiudadano123'];
        $this->postJson("$api/register", $citizen + ['dni' => '91000000', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'zone_id' => $g->zone_id])->assertCreated();
        $d = ['Authorization' => 'Bearer '.$this->postJson("$api/login", $driver)->assertOk()->json('token')];
        $c = ['Authorization' => 'Bearer '.$this->postJson("$api/login", $citizen)->assertOk()->json('token')];
        $this->getJson("$api/schedules?month=2027-03", $c)->assertOk()->assertJsonCount(3, 'items');
        $this->postJson("$path/start", [], $c)->assertForbidden();
        $this->postJson("$path/start", [], $d)->assertUnprocessable();
        $mark = ['shift_id' => $g->shift_id, 'request_id' => (string) Str::uuid()];
        $this->postJson("$api/attendance", $mark, $d)->assertOk();
        $this->postJson("$api/attendance", $mark, $d)->assertOk();
        $this->assertDatabaseCount('attendances', 1);
        foreach ($g->helpers as $helper) {
            Attendance::create(['employee_id' => $helper->id, 'shift_id' => $g->shift_id, 'date' => '2027-03-01', 'time' => '08:00', 'type' => 'entry', 'status' => 'present']);
        }
        $this->getJson($path, $d)->assertOk()->assertJsonPath('can_start', true);
        $this->postJson("$path/start", [], $d)->assertOk();
        $samples = ['samples' => [['ms' => 100, 'lat' => -6.77, 'lng' => -79.84], ['ms' => 200, 'lat' => -6.78, 'lng' => -79.85]]];
        $this->postJson("$path/points", $samples, $c)->assertForbidden();
        $this->postJson("$path/points", $samples, $d)->assertOk();
        $this->postJson("$path/points", $samples, $d)->assertOk();
        $this->getJson($path, $c)->assertOk()->assertJsonPath('state', 'En curso')->assertJsonCount(2, 'points')->assertJsonCount(0, 'crew');
        $this->postJson("$path/rate", ['stars' => 4], $c)->assertNotFound();
        $this->postJson("$path/finish", [], $d)->assertUnprocessable();
        $this->postJson("$path/finish", ['observation' => 'Sin incidencias'], $d)->assertOk();
        $this->postJson("$path/points", $samples, $d)->assertConflict();
        $this->postJson("$path/rate", ['stars' => 4, 'comment' => 'Buen servicio'], $c)->assertOk();
        $this->postJson("$path/rate", ['stars' => 5], $c)->assertOk();
        $this->getJson($path, $c)->assertOk()->assertJsonPath('rating.stars', 5)->assertJsonPath('votes', 1)->assertJsonPath('observation', 'Sin incidencias');
        $this->getJson("$api/schedules?month=2027-03&history=1", $d)->assertOk()->assertJsonCount(1, 'items');
        $this->postJson("$api/logout", [], $d)->assertOk();
        $this->getJson($path,$d)->assertUnauthorized();
        $schedule->update(['zone_id' => 9999]);
        $this->getJson($path,$c)->assertNotFound();
    }
}
