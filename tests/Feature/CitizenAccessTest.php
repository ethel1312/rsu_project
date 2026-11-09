<?php

namespace Tests\Feature;

use App\Models\Programming\PersonnelGroup;
use App\Models\Programming\ProgrammingSchedule;
use App\Models\Programming\Zone;
use Carbon\Carbon;
use Database\Seeders\ProgrammingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CitizenAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_timetable_and_residence_change_only_expose_the_correct_zone(): void
    {
        $this->travelTo(Carbon::parse('2027-03-01 00:01','America/Lima'));
        $this->seed(ProgrammingDemoSeeder::class);
        $g=PersonnelGroup::with(['driver','helpers','zone','shift','vehicle'])->firstOrFail();
        $other=Zone::where('id','!=',$g->zone_id)->where('status',true)->firstOrFail();
        $schedule=ProgrammingSchedule::create(['personnel_group_id'=>$g->id,'group_name'=>$g->name,'zone_id'=>$g->zone_id,'zone_name'=>$g->zone->name,
            'shift_id'=>$g->shift_id,'shift_name'=>$g->shift->name,'shift_start_time'=>'08:00','shift_end_time'=>'12:00','vehicle_id'=>$g->vehicle_id,
            'vehicle_label'=>$g->vehicle->plate,'driver_id'=>$g->driver_id,'driver_name'=>$g->driver->full_name,'helper_ids'=>[],'helper_names'=>[],
            'days_of_week'=>[1],'start_date'=>'2027-03-01','end_date'=>'2027-03-22','excluded_dates'=>['2027-03-08'],'status'=>'Programada']);
        $url="/api/mobile/zones/$g->zone_id/timetable";
        $this->getJson($url)->assertOk()->assertJsonCount(3,'items')->assertJsonPath('items.1.date','2027-03-15')
            ->assertJsonMissingPath('items.0.driver')->assertJsonMissingPath('items.0.id');
        $schedule->runs()->create(['service_date'=>'2027-03-01','started_at'=>now(),'finished_at'=>now()]);
        $this->getJson($url)->assertOk()->assertJsonPath('items.0.date','2027-03-15');
        $this->getJson('/api/mobile/profile')->assertUnauthorized();
        $credentials=['email'=>'test@citizen.test','password'=>'PruebaCiudadana123','role'=>'citizen'];
        $this->postJson('/api/mobile/register',$credentials+['first_name'=>'Ana','last_name'=>'Rojas','dni'=>'97654321','zone_id'=>$g->zone_id])->assertCreated();
        $headers=['Authorization'=>'Bearer '.$this->postJson('/api/mobile/login',$credentials)->json('token')];
        $this->getJson('/api/mobile/profile',$headers)->assertOk()->assertJsonMissingPath('password')->assertJsonPath('dni','97654321');
        $this->postJson('/api/mobile/profile',['first_name'=>'Ana María','last_name'=>'Rojas','zone_id'=>$other->id],$headers)
            ->assertOk()->assertJsonPath('first_name','Ana María')->assertJsonPath('zone_id',$other->id);
        $this->getJson('/api/mobile/schedules/'.$schedule->id.'/2027-03-01',$headers)->assertNotFound();
        $other->update(['status'=>false]);
        $this->getJson("/api/mobile/zones/$other->id/timetable")->assertNotFound();
    }

    public function test_password_link_is_single_use_expires_and_revokes_old_sessions(): void
    {
        config(['mail.default'=>'array','app.url'=>'https://rsu.example.test']);
        $this->seed(ProgrammingDemoSeeder::class);
        $credentials=['role'=>'citizen','email'=>'test@citizen.test','password'=>'Antes123456'];
        $this->postJson('/api/mobile/register',$credentials+['dni'=>'99567800','first_name'=>'Luz','last_name'=>'Ruiz','zone_id'=>Zone::firstOrFail()->id])->assertCreated();
        $headers=['Authorization'=>'Bearer '.$this->postJson('/api/mobile/login',$credentials)->json('token')];
        $unknown=$this->postJson('/api/mobile/password/forgot',['email'=>'unknown@test.com'])->assertOk()->json();
        $issue=function () use ($credentials,$unknown) {
            $this->postJson('/api/mobile/password/forgot',['email'=>$credentials['email']])->assertOk()->assertExactJson($unknown);
            $email=Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
            $this->assertStringContainsString('https://rsu.example.test/api/mobile/password/reset#',$email->getTextBody());
            preg_match('/#([A-Za-z0-9]{64})/',$email->getTextBody(),$matches);
            return ['token'=>$matches[1],'password'=>'Nueva123456','password_confirmation'=>'Nueva123456'];
        };
        $expired=$issue();
        $this->travel(31)->minutes();
        $this->postJson('/api/mobile/password/reset',$expired)->assertUnprocessable();
        $fresh=$issue();
        $this->postJson('/api/mobile/password/reset',$fresh)->assertOk();
        $this->postJson('/api/mobile/password/reset',$fresh)->assertUnprocessable();
        $this->getJson('/api/mobile/profile',$headers)->assertUnauthorized();
        $this->postJson('/api/mobile/login',$credentials)->assertUnauthorized();
        $this->postJson('/api/mobile/login',array_merge($credentials,['password'=>$fresh['password']]))->assertOk();
        $this->get('/api/mobile/password/reset')->assertOk()->assertSee('Nueva contraseña');
    }
}
