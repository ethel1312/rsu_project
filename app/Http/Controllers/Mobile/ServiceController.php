<?php

namespace App\Http\Controllers\Mobile;

use App\Models\Employees\Attendance;
use App\Models\Employees\Employee;
use App\Models\Programming\ProgrammingSchedule;
use App\Models\Programming\ScheduleRun;
use App\Models\Programming\Shift;
use App\Models\Programming\Zone;
use App\Services\Employees\AttendanceService;
use App\Services\Programming\ScheduleChangeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController
{
    public function __construct(private ScheduleChangeService $operations) {}

    private function driver(Request $r): Employee
    {
        abort_unless($r->attributes->get('mobile_role') === 'driver', 403);

        return $r->attributes->get('mobile_person');
    }

    private function today(): string
    {
        return now(Attendance::TIMEZONE)->toDateString();
    }

    private function scope(Request $r)
    {
        $person = $r->attributes->get('mobile_person');

        return $r->attributes->get('mobile_role') === 'driver'
            ? ProgrammingSchedule::where('driver_id', $person->id)
            : ProgrammingSchedule::where('zone_id', $person->zone_id);
    }

    private function occurrence(Request $r, int $id, string $date, bool $lock = false): ProgrammingSchedule
    {
        validator(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->validate();
        $schedule = $this->scope($r)->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
        abort_unless($schedule->runsOn($date), 404);

        return $schedule;
    }

    public function shifts(Request $r)
    {
        $this->driver($r);

        return ['items' => Shift::orderBy('start_time')->get(['id', 'name', 'start_time', 'end_time'])];
    }

    public function mark(Request $r, AttendanceService $service)
    {
        $driver = $this->driver($r);
        $data = $r->validate(['shift_id' => 'required|exists:shifts,id', 'request_id' => 'required|uuid']);
        $mark = DB::transaction(function () use ($driver, $service, $data) {
            Employee::whereKey($driver->id)->lockForUpdate()->firstOrFail();
            $previous = Attendance::where('employee_id', $driver->id)->where('mobile_mark_key', $data['request_id'])->first();
            if ($previous) {
                return $previous;
            }
            $now = now(Attendance::TIMEZONE);
            $mark = $service->save(['employee_id' => $driver->id, 'shift_id' => $data['shift_id'], 'date' => $now->toDateString(),
                'time' => $now->toTimeString(), 'status' => 'present', 'notes' => null]);
            $mark->forceFill(['mobile_mark_key' => $data['request_id']])->save();

            return $mark;
        });

        return ['message' => ($mark->type === 'entry' ? 'Ingreso' : 'Salida').' registrado · '.$mark->shift->name.' · '.$mark->time];
    }

    public function index(Request $r)
    {
        $data = $r->validate(['month' => 'sometimes|date_format:Y-m', 'date' => 'sometimes|date_format:Y-m-d', 'history' => 'sometimes|boolean']);
        $today = $this->today();
        $first = $last = CarbonImmutable::parse($today, Attendance::TIMEZONE);
        if ($r->boolean('history') && isset($data['date'])) {
            $first = $last = CarbonImmutable::parse($data['date'], Attendance::TIMEZONE);
        } elseif ($r->boolean('history')) {
            $first = CarbonImmutable::parse(($data['month'] ?? substr($today, 0, 7)).'-01', Attendance::TIMEZONE);
            $last = $first->endOfMonth();
        }
        $schedules = $this->scope($r)->with(['runs' => fn ($q) => $q->whereDate('service_date', '>=', $first->toDateString())
            ->whereDate('service_date', '<=', $last->toDateString())])
            ->whereDate('start_date', '<=', $last)->whereDate('end_date', '>=', $first)->get();
        $items = [];
        foreach ($schedules as $schedule) {
            $runs = $schedule->runs->keyBy(fn ($run) => $run->service_date->toDateString());
            for ($day = $first; $day->lte($last); $day = $day->addDay()) {
                $date = $day->toDateString();
                if (! $schedule->runsOn($date)) {
                    continue;
                }
                $run = $runs->get($date);
                if ($r->boolean('history') && ! $run?->finished_at) {
                    continue;
                }
                $items[] = $this->summary($schedule, $date, $run);
            }
        }
        $items = collect($items)->sortBy([['date', $r->boolean('history') ? 'desc' : 'asc'], ['start', 'asc']])->values();

        return ['items' => $items, 'today' => $today];
    }

    private function summary($s, string $date, ?ScheduleRun $run): array
    {
        $rating = $run?->finished_at ? DB::table('service_ratings')->where('schedule_run_id', $run->id)->selectRaw('AVG(stars) as average, COUNT(*) as votes')->first() : null;

        return ['id' => $s->id, 'date' => $date, 'zone' => $s->zone_name, 'vehicle' => $s->vehicle_label, 'shift' => $s->shift_name, 'start' => substr($s->shift_start_time, 0, 5),
            'end' => substr($s->shift_end_time, 0, 5), 'state' => $run?->finished_at || $s->status === 'Finalizada' ? 'Finalizado' : ($run?->started_at ? 'En curso' : 'Programado'),
            'weekdays' => $s->days_of_week,
            'started_at' => $run?->started_at?->toIso8601String(),
            'finished_at' => $run?->finished_at?->toIso8601String(),
            'average' => $rating?->average ? round((float) $rating->average, 1) : null, 'votes' => (int) ($rating?->votes ?? 0)];
    }

    public function detail(Request $r, int $schedule, string $date)
    {
        $s = $this->occurrence($r, $schedule, $date);
        $run = $s->runs()->whereDate('service_date', $date)->first();
        $isDriver = $r->attributes->get('mobile_role') === 'driver';
        $crew = [];
        if ($isDriver) {
            foreach (array_merge([$s->driver_id], $s->helper_ids) as $i => $id) {
                $crew[] = ['name' => $i === 0 ? $s->driver_name : ($s->helper_names[$i - 1] ?? 'Ayudante'), 'present' => $this->operations->attended($id, $date, $s->shift_id)];
            }
        }
        $rating = ! $isDriver && $run ? DB::table('service_ratings')->where('schedule_run_id', $run->id)->where('citizen_id', $r->attributes->get('mobile_person')->id)->first(['stars', 'comment']) : null;

        return $this->summary($s, $date, $run) + ['driver' => $s->driver_name, 'helpers' => $s->helper_names, 'crew' => $crew, 'today' => $this->today(),
            'can_start' => $isDriver && $date === $this->today() && in_array($s->status, ['Programada', 'Reprogramado'], true) && ! $run && ! in_array(false, array_column($crew, 'present'), true),
            'polygon' => Zone::find($s->zone_id)?->coordinates ?? [], 'points' => $run?->route_points ?? [],
            'started_at' => $run?->started_at?->toIso8601String(), 'finished_at' => $run?->finished_at?->toIso8601String(),
            'observation' => $run?->observation, 'rating' => $rating];
    }

    public function start(Request $r, int $schedule, string $date)
    {
        $driver = $this->driver($r);

        return DB::transaction(function () use ($r, $driver, $schedule, $date) {
            $this->operations->lock();
            $s = $this->occurrence($r, $schedule, $date, true);
            abort_unless($date === $this->today(), 422, 'Solo puedes iniciar el recorrido de hoy.');
            $this->operations->assertEditable($s, $date);
            $busy = ScheduleRun::whereIn('programming_schedule_id', ProgrammingSchedule::where('driver_id', $driver->id)->select('id'))->whereNotNull('started_at')->whereNull('finished_at')->exists();
            abort_if($busy, 409, 'Ya tienes un recorrido en curso.');
            foreach (array_merge([$driver->id], $s->helper_ids) as $id) {
                abort_unless($this->operations->attended($id, $date, $s->shift_id), 422, 'Falta asistencia del equipo para este turno.');
            }
            $s->runs()->create(['started_at' => now(), 'service_date' => $date, 'route_points' => []]);
            $this->operations->history($s, 'started', null, ['fecha' => $date, 'estado' => 'En curso'], 'Inicio desde la aplicación del conductor.');

            return ['message' => 'Recorrido iniciado'];
        }, 3);
    }

    public function points(Request $r, int $schedule, string $date)
    {
        $this->driver($r);
        $data = $r->validate(['samples' => 'required|array|min:1|max:200', 'samples.*.lat' => 'required|numeric|between:-90,90',
            'samples.*.lng' => 'required|numeric|between:-180,180', 'samples.*.ms' => 'required|integer|min:1']);

        return DB::transaction(function () use ($r, $schedule, $date, $data) {
            $s = $this->occurrence($r, $schedule, $date, true);
            $run = $s->runs()->whereDate('service_date', $date)->lockForUpdate()->firstOrFail();
            abort_if($run->finished_at, 409, 'Recorrido finalizado.');
            $points = $run->route_points ?? [];
            $last = (int) $run->last_sample_ms;
            foreach (collect($data['samples'])->sortBy('ms') as $point) {
                if ((int) $point['ms'] <= $last) {
                    continue;
                }
                $points[] = [(float) $point['lat'], (float) $point['lng']];
                $last = (int) $point['ms'];
            }
            $run->update(['route_points' => $points, 'last_sample_ms' => $last]);

            return ['received_through' => $last];
        }, 3);
    }

    public function finish(Request $r, int $schedule, string $date)
    {
        $this->driver($r);
        $data = $r->validate(['observation' => 'required|string|max:500']);

        return DB::transaction(function () use ($r, $schedule, $date, $data) {
            $this->operations->lock();
            $s = $this->occurrence($r, $schedule, $date, true);
            $run = $s->runs()->whereDate('service_date', $date)->lockForUpdate()->firstOrFail();
            if (! $run->finished_at) {
                abort_if(empty($run->route_points), 422, 'Espera la primera ubicación GPS antes de finalizar.');
                $run->update(['finished_at' => now(), 'observation' => $data['observation']]);
                $this->operations->history($s, 'finished', null, ['fecha' => $date, 'estado' => 'Finalizada'], 'Cierre desde la aplicación del conductor.');
            }

            return ['message' => 'Recorrido finalizado'];
        }, 3);
    }

    public function rate(Request $r, int $schedule, string $date)
    {
        abort_unless($r->attributes->get('mobile_role') === 'citizen', 403);
        $data = $r->validate(['stars' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:500']);
        $s = $this->occurrence($r, $schedule, $date);
        $run = $s->runs()->whereDate('service_date', $date)->whereNotNull('finished_at')->firstOrFail();
        DB::table('service_ratings')->upsert([['citizen_id' => $r->attributes->get('mobile_person')->id, 'schedule_run_id' => $run->id,
            'stars' => $data['stars'], 'comment' => $data['comment'] ?? null, 'created_at' => now(), 'updated_at' => now()]],['schedule_run_id', 'citizen_id'],['stars', 'comment', 'updated_at']);

        return ['message' => 'Valoración guardada'];
    }
}

