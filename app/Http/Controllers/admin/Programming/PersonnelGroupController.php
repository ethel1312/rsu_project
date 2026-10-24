<?php

namespace App\Http\Controllers\admin\Programming;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Programming\PersonnelGroup;
use App\Models\Programming\Shift;
use App\Models\Programming\Zone;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class PersonnelGroupController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::of(PersonnelGroup::with([
                'shift:id,name,start_time,end_time', 'zone:id,name', 'vehicle:id,name,plate',
                'driver:id,first_name,last_name',
                'helpers' => fn ($query) => $query->select('employees.id', 'first_name', 'last_name'),
            ])->select('personnel_groups.*'))
                ->addColumn('shift_label', fn ($group) => $group->shift->name.' ('.substr($group->shift->start_time, 0, 5).'–'.substr($group->shift->end_time, 0, 5).')')
                ->addColumn('vehicle_label', fn ($group) => $group->vehicle->plate.' · '.$group->vehicle->name)
                ->addColumn('driver_label', fn ($group) => $group->driver->full_name)
                ->addColumn('helpers_label', fn ($group) => $group->helpers->map->full_name->implode(', '))
                ->addColumn('days_label', fn ($group) => $group->days_label)
                ->addColumn('status_badge', fn ($group) => $group->status ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>')
                ->editColumn('created_at', fn ($group) => $group->created_at?->timezone('America/Lima')->format('d/m/Y H:i'))
                ->addColumn('actions', fn ($group) => view('admin.programming.personnel-groups.template.actions', compact('group'))->render())
                ->rawColumns(['status_badge', 'actions'])
                ->make(true);
        }

        return view('admin.programming.personnel-groups.index');
    }

    public function create()
    {
        return view('admin.programming.personnel-groups.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $group = PersonnelGroup::create(collect($data)->except('helpers')->all());
            $group->helpers()->sync($data['helpers']);
        });

        return response()->json(['message' => 'Grupo de personal registrado exitosamente.']);
    }

    public function edit(PersonnelGroup $personnelGroup)
    {
        $personnelGroup->load(['zone', 'driver', 'helpers']);

        return view('admin.programming.personnel-groups.edit', $this->formData($personnelGroup));
    }

    public function update(Request $request, PersonnelGroup $personnelGroup)
    {
        $data = $this->validated($request, $personnelGroup);
        DB::transaction(function () use ($personnelGroup, $data) {
            $personnelGroup->update(collect($data)->except('helpers')->all());
            $personnelGroup->helpers()->sync($data['helpers']);
        });

        return response()->json(['message' => 'Grupo de personal actualizado exitosamente.']);
    }

    public function destroy(PersonnelGroup $personnelGroup)
    {
        $personnelGroup->delete();

        return response()->json(['message' => 'Grupo de personal eliminado correctamente.']);
    }

    private function formData(?PersonnelGroup $group = null): array
    {
        $drivers = $this->employeesForType('Conductor')->orderBy('last_name')->get();
        $helpers = $this->employeesForType('Ayudante')->orderBy('last_name')->get();
        $zones = Zone::where('status', true)->orderBy('name')->get();

        // Mostrar la selección previa aunque se haya inactivado, para poder reemplazarla.
        if ($group) {
            if (! $zones->contains('id', $group->zone_id)) {
                $zones->push($group->zone);
            }
            if (! $drivers->contains('id', $group->driver_id)) {
                $drivers->push($group->driver);
            }
            $helpers = $helpers->merge($group->helpers)->unique('id');
        }

        return [
            'group' => $group,
            'shifts' => Shift::orderBy('start_time')->get(),
            'zones' => $zones,
            'vehicles' => Vehicle::orderBy('plate')->get(),
            'drivers' => $drivers,
            'helpers' => $helpers,
            'weekdays' => PersonnelGroup::WEEKDAYS,
        ];
    }

    private function employeesForType(string $type)
    {
        return Employee::with('employeeType')->where('status', true)
            ->whereHas('employeeType', fn ($query) => $query->where('name', $type));
    }

    private function eligibleEmployeeRule(string $type)
    {
        return Rule::exists('employees', 'id')->where(fn ($query) => $query
            ->where('status', true)
            ->whereIn('employee_type_id', DB::table('employee_types')->select('id')->where('name', $type)));
    }

    private function validated(Request $request, ?PersonnelGroup $group = null): array
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('personnel_groups', 'name')->ignore($group)],
            'shift_id' => ['required', 'integer', Rule::exists('shifts', 'id')],
            'zone_id' => ['required', 'integer', Rule::exists('zones', 'id')->where('status', true)],
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')],
            'days_of_week' => ['required', 'array', 'min:1', 'max:7'],
            'days_of_week.*' => ['required', 'integer', 'distinct', Rule::in(array_keys(PersonnelGroup::WEEKDAYS))],
            'driver_id' => ['required', 'integer', $this->eligibleEmployeeRule('Conductor')],
            'helpers' => ['required', 'array', 'min:1'],
            'helpers.*' => ['required', 'integer', 'distinct', 'different:driver_id', $this->eligibleEmployeeRule('Ayudante')],
            'status' => ['sometimes', 'required', 'boolean'],
        ], [
            'name.required' => 'El nombre del grupo es obligatorio.',
            'name.max' => 'El nombre del grupo no debe superar los 150 caracteres.',
            'name.unique' => 'Ya existe un grupo de personal con ese nombre.',
            'shift_id.required' => 'Seleccione un turno.',
            'shift_id.exists' => 'El turno seleccionado ya no existe. Seleccione otro turno.',
            'zone_id.required' => 'Seleccione una zona activa.',
            'zone_id.exists' => 'La zona seleccionada está inactiva o ya no existe. Seleccione otra zona.',
            'vehicle_id.required' => 'Seleccione un vehículo.',
            'vehicle_id.exists' => 'El vehículo seleccionado ya no existe. Seleccione otro vehículo.',
            'days_of_week.required' => 'Seleccione al menos un día de trabajo.',
            'days_of_week.min' => 'Seleccione al menos un día de trabajo.',
            'days_of_week.max' => 'Seleccione como máximo los siete días de la semana.',
            'days_of_week.*.in' => 'Seleccione días de la semana válidos.',
            'days_of_week.*.distinct' => 'Los días de trabajo no pueden repetirse.',
            'driver_id.required' => 'Seleccione un conductor activo.',
            'driver_id.exists' => 'El conductor debe estar activo y ser del tipo Conductor. Seleccione otro conductor.',
            'helpers.required' => 'Seleccione al menos un ayudante activo.',
            'helpers.min' => 'Seleccione al menos un ayudante activo.',
            'helpers.*.exists' => 'Todos los ayudantes deben estar activos y ser del tipo Ayudante.',
            'helpers.*.distinct' => 'Un ayudante no puede aparecer más de una vez en el grupo.',
            'helpers.*.different' => 'El conductor no puede figurar también como ayudante.',
            'status.boolean' => 'Seleccione un estado válido para el grupo.',
        ], [
            'name' => 'nombre del grupo', 'shift_id' => 'turno', 'zone_id' => 'zona',
            'vehicle_id' => 'vehículo', 'days_of_week' => 'días de trabajo',
            'days_of_week.*' => 'día de trabajo', 'driver_id' => 'conductor',
            'helpers' => 'ayudantes', 'helpers.*' => 'ayudante', 'status' => 'estado',
        ]);

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        $occupants = count($data['helpers']) + 1;
        if ($occupants > $vehicle->occupant_capacity) {
            throw ValidationException::withMessages([
                'helpers' => "El vehículo {$vehicle->plate} admite {$vehicle->occupant_capacity} personas en total. Seleccionó {$occupants} (1 conductor y ".count($data['helpers']).' ayudantes). Reduzca los ayudantes o seleccione otro vehículo.',
            ]);
        }

        $data['days_of_week'] = collect($data['days_of_week'])->map(fn ($day) => (int) $day)->sort()->values()->all();
        $data['helpers'] = array_map('intval', $data['helpers']);
        $data['status'] = $data['status'] ?? $group?->status ?? true;

        return $data;
    }
}
