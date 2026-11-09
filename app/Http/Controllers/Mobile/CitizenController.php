<?php

namespace App\Http\Controllers\Mobile;

use App\Models\Employees\Attendance;
use App\Models\Programming\ProgrammingSchedule;
use App\Models\Programming\Zone;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CitizenController
{
    public function timetable(Zone $zone)
    {
        abort_unless($zone->status, 404);
        $now = CarbonImmutable::now(Attendance::TIMEZONE);
        $today = $now->toDateString();
        $rows = [];
        $schedules = ProgrammingSchedule::where('zone_id', $zone->id)->whereDate('end_date', '>=', $today)
            ->whereIn('status', ['Programada','Reprogramado'])->with(['runs' => fn ($q) => $q->whereDate('service_date', $today)])->get();
        foreach ($schedules as $schedule) {
            if (!array_intersect($schedule->days_of_week ?? [], range(1,7))) continue;
            $first = max($today, $schedule->start_date->toDateString());
            $end = $schedule->end_date->toDateString();
            $added = 0;
            for ($day = CarbonImmutable::parse($first, Attendance::TIMEZONE); $day->toDateString() <= $end && $added < 8; $day = $day->addDay()) {
                $date = $day->toDateString();
                if (!$schedule->runsOn($date)) continue;
                if ($date === $today && $schedule->runs->contains(fn ($run) => $run->finished_at !== null)) continue;
                $until = $day->setTimeFromTimeString($schedule->shift_end_time);
                if ($schedule->shift_end_time <= $schedule->shift_start_time) $until = $until->addDay();
                if ($until->lte($now)) continue;
                $rows[] = ['date'=>$date, 'shift'=>$schedule->shift_name, 'start'=>substr($schedule->shift_start_time,0,5),
                    'end'=>substr($schedule->shift_end_time,0,5), 'until_ms'=>$until->getTimestampMs()];
                $added++;
            }
        }
        return ['zone_id'=>$zone->id,'zone'=>$zone->name,'updated_ms'=>$now->getTimestampMs(),
            'items'=>collect($rows)->sortBy([['date','asc'],['start','asc']])->unique(fn ($row)=>json_encode($row))->take(8)->values()];
    }

    public function profile(Request $request)
    {
        abort_unless($request->attributes->get('mobile_role') === 'citizen',403);
        $citizen = $request->attributes->get('mobile_person');
        if ($request->isMethod('post')) {
            $changes = $request->validate(['first_name'=>'required|string|max:100','last_name'=>'required|string|max:100',
                'zone_id'=>['required','integer',Rule::exists('zones','id')->where('status',true)]]);
            DB::table('citizens')->where('id',$citizen->id)->update($changes+['updated_at'=>now()]);
            $citizen = DB::table('citizens')->find($citizen->id);
        }
        return collect((array)$citizen)->only(['first_name','last_name','zone_id','dni','email'])->all();
    }

    public function forgot(Request $request)
    {
        $email = $request->validate(['email'=>'required|email|max:255'])['email'];
        abort_if(app()->isProduction() && in_array(config('mail.default'), ['log','array']), 503, 'El correo de recuperación aún no está configurado.');
        $citizen = DB::table('citizens')->where('email',$email)->first();
        if ($citizen) {
            $token = Str::random(64);
            DB::table('citizen_password_resets')->upsert([['citizen_id'=>$citizen->id,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addMinutes(30)]], ['citizen_id'], ['token_hash','expires_at']);
            $link = rtrim(config('app.url'),'/').'/api/mobile/password/reset#'.$token;
            Mail::raw("Para cambiar tu contraseña de RSU Ciudadano abre este enlace en los próximos 30 minutos:\n\n".$link,
                fn ($message) => $message->to($citizen->email)->subject('Recupera tu acceso a RSU'));
        }
        return ['message'=>'Si existe una cuenta ciudadana con ese correo, recibirás un enlace para cambiar tu contraseña.'];
    }

    public function reset(Request $request)
    {
        $data=$request->validate(['token'=>'required|string|size:64','password'=>'required|string|min:8|max:72|confirmed']);
        return DB::transaction(function () use ($data) {
            $record = DB::table('citizen_password_resets')->where('token_hash',hash('sha256',$data['token']))
                ->where('expires_at','>',now())->lockForUpdate()->first();
            abort_unless($record,422,'El enlace venció o ya fue utilizado. Solicita uno nuevo.');
            DB::table('citizens')->where('id',$record->citizen_id)->update(['password'=>Hash::make($data['password']),'updated_at'=>now()]);
            DB::table('mobile_sessions')->where('citizen_id',$record->citizen_id)->delete();
            DB::table('citizen_password_resets')->where('citizen_id',$record->citizen_id)->delete();
            return ['message'=>'Contraseña actualizada. Ya puedes ingresar desde la app.'];
        },3);
    }
}
