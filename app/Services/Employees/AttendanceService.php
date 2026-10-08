<?php

namespace App\Services\Employees;

use App\Models\Employees\Attendance;
use App\Models\Employees\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function save(array $data, ?Attendance $attendance = null): Attendance
    {
        return DB::transaction(function () use ($data, $attendance) {
            // Bloquear el personal también protege la primera marcación del día.
            $employeeId = $data['employee_id'] ?? $attendance?->employee_id;
            $ids = array_unique(array_filter([$employeeId, $attendance?->employee_id]));
            Employee::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();

            $previous = null;
            if ($attendance) {
                $current = Attendance::lockForUpdate()->findOrFail($attendance->id);
                if ($current->employee_id !== $attendance->employee_id) {
                    throw ValidationException::withMessages([
                        'employee_id' => 'La asistencia fue modificada. Vuelva a abrir el formulario.',
                    ]);
                }
                $attendance = $current;
                $data = array_merge([
                    'employee_id' => $current->employee_id,
                    'date' => $current->date->format('Y-m-d'),
                    'status' => $current->status,
                ], $data);
                $previous = [$attendance->employee_id, $attendance->date->format('Y-m-d')];
            }

            $duplicate = Attendance::where('employee_id', $data['employee_id'])
                ->where('date', $data['date'])->where('time', $data['time'])
                ->when($attendance, fn ($query) => $query->where('id', '!=', $attendance->id))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'time' => 'Ya existe una asistencia de este personal en la misma fecha y hora.',
                ]);
            }

            $attendance ??= new Attendance;
            $attendance->fill($data);
            $attendance->type = null;
            $attendance->save();

            if ($previous && $previous !== [(int) $data['employee_id'], $data['date']]) {
                $this->resequence(...$previous);
            }
            $this->resequence((int) $data['employee_id'], $data['date']);

            return $attendance->refresh();
        }, 3);
    }

    public function delete(Attendance $attendance): void
    {
        DB::transaction(function () use ($attendance) {
            Employee::whereKey($attendance->employee_id)->lockForUpdate()->firstOrFail();
            $current = Attendance::lockForUpdate()->findOrFail($attendance->id);
            if ($current->employee_id !== $attendance->employee_id) {
                throw ValidationException::withMessages([
                    'employee_id' => 'La asistencia fue modificada. Actualice el listado e intente nuevamente.',
                ]);
            }
            $employeeId = $current->employee_id;
            $date = $current->date->format('Y-m-d');
            $current->delete();
            $this->resequence($employeeId, $date);
        }, 3);
    }

    public function nextType(int $employeeId, string $date, string $time, ?int $exceptId = null): string
    {
        $count = Attendance::where('employee_id', $employeeId)->where('date', $date)
            ->where('status', 'present')->where('time', '<', $time)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))->count();

        return $count % 2 === 0 ? 'entry' : 'exit';
    }

    private function resequence(int $employeeId, string $date): void
    {
        $records = Attendance::where('employee_id', $employeeId)->where('date', $date)
            ->orderBy('time')->orderBy('id')->lockForUpdate()->get();
        $position = 0;

        foreach ($records as $record) {
            // Una ausencia no es una marcación y no altera la alternancia.
            $type = $record->status === 'present' ? ($position++ % 2 === 0 ? 'entry' : 'exit') : null;
            if ($record->type !== $type) {
                $record->update(['type' => $type]);
            }
        }
    }
}
