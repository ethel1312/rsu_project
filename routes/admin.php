<?php

use App\Http\Controllers\admin\AdminController;
use App\Http\Controllers\admin\BrandController;
use App\Http\Controllers\admin\BrandmdelController;
use App\Http\Controllers\admin\ColorController;
use App\Http\Controllers\admin\VehicleTypeController;
use App\Http\Controllers\admin\VehicleController;

use App\Http\Controllers\admin\Employees\EmployeeTypeController;
use App\Http\Controllers\admin\Employees\EmployeeController;
use App\Http\Controllers\admin\Employees\ContractController;
use App\Http\Controllers\admin\Employees\VacationController;
use App\Http\Controllers\admin\Employees\AttendanceController;
use App\Http\Controllers\admin\Programming\ZoneController;
use App\Http\Controllers\admin\Programming\ShiftController;
use App\Http\Controllers\admin\Programming\HolidayController;
use App\Http\Controllers\admin\Programming\PersonnelGroupController;
use App\Http\Controllers\admin\Programming\ProgrammingScheduleController;

use Illuminate\Support\Facades\Route;

Route::get('/', [AdminController::class, 'index']);

Route::resource('brands', BrandController::class)->names('admin.brands');

Route::resource('models',BrandmdelController::class)->names('admin.models');

Route::resource('colors',ColorController::class)->names('admin.colors');

Route::resource('vehicle_types',VehicleTypeController::class)->names('admin.vehicle_types');

Route::resource('vehicles', VehicleController::class)->names('admin.vehicles');

Route::get('vehicles/models/{brandId}', [VehicleController::class, 'getModelsByBrand'])
    ->name('admin.vehicles.models');

Route::get('vehicles/{id}/images', [VehicleController::class, 'getImages'])->name('admin.vehicles.images');
Route::post('vehicles/{id}/images', [VehicleController::class, 'uploadImage'])->name('admin.vehicles.images.upload');
Route::post('vehicles/images/{image_id}/profile', [VehicleController::class, 'setProfileImage'])->name('admin.vehicles.images.profile');
Route::delete('vehicles/images/{image_id}', [VehicleController::class, 'deleteImage'])->name('admin.vehicles.images.destroy');


//contratoss
// Contratos
Route::get('contracts/employees', [ContractController::class, 'searchEmployees'])->name('admin.contracts.employees');
Route::resource('contracts', ContractController::class)->names('admin.contracts');
Route::patch('contracts/{contract}/toggle-status', [ContractController::class, 'toggleStatus'])->name('admin.contracts.toggle-status');

/*
|--------------------------------------------------------------------------
| GESTIÓN DE PERSONAL
|--------------------------------------------------------------------------
| except(['show']): los listados usan modal, no hay página de detalle.
*/
Route::resource('employee_types', EmployeeTypeController::class)->except(['show'])->names('admin.employee_types');

Route::resource('employees', EmployeeController::class)->except(['show'])->names('admin.employees');

// Route::resource('contracts', ContractController::class)->except(['show'])->names('admin.contracts');

Route::middleware('auth')->group(function () {
    Route::get('attendances/employees', [AttendanceController::class, 'searchEmployees'])->name('admin.attendances.employees');
    Route::get('attendances/preview', [AttendanceController::class, 'preview'])->name('admin.attendances.preview');
    Route::resource('attendances', AttendanceController::class)->except(['show'])->names('admin.attendances');
});

//VACACIONES
Route::get(
    'vacations/balance/{employeeId}',
    [VacationController::class, 'balanceSummary']
)->name('admin.vacations.balance');

Route::resource('vacations', VacationController::class)
    ->except(['show'])
    ->names('admin.vacations');

Route::patch('vacations/{id}/approve', [VacationController::class, 'approve'])
    ->name('admin.vacations.approve');

Route::patch('vacations/{id}/reject', [VacationController::class, 'reject'])
    ->name('admin.vacations.reject');

Route::get(
    'vacations/employees',
    [VacationController::class, 'searchEmployees']
)->name('admin.vacations.employees');

// ZONAAS
Route::get('zones/map', [ZoneController::class, 'map'])
    ->middleware('auth')
    ->name('admin.programming.zones.map');
Route::get('zones/{zone}/map-data', [ZoneController::class, 'mapData'])
    ->middleware('auth')
    ->name('admin.programming.zones.map-data');
Route::resource('zones', ZoneController::class)->names('admin.programming.zones');

//Turnos
Route::resource('shifts', ShiftController::class)
    ->except(['show'])
    ->middleware('auth')
    ->names('admin.programming.shifts');

Route::middleware('auth')->group(function () {
    Route::post('holidays/import', [HolidayController::class, 'import'])
        ->name('admin.programming.holidays.import');
    Route::patch('holidays/{holiday}/toggle-status', [HolidayController::class, 'toggleStatus'])
        ->name('admin.programming.holidays.toggle-status');
    Route::resource('holidays', HolidayController::class)
        ->except(['show'])->names('admin.programming.holidays');

    Route::post('personnel-groups/availability', [PersonnelGroupController::class, 'availability'])
        ->name('admin.programming.personnel-groups.availability');
    Route::resource('personnel-groups', PersonnelGroupController::class)
        ->parameters(['personnel-groups' => 'personnelGroup'])
        ->except(['show'])->names('admin.programming.personnel-groups');
});

Route::middleware('auth')->prefix('programming/schedules')->name('admin.programming.schedules.')->group(function () {
    Route::get('/', [ProgrammingScheduleController::class, 'index'])->name('index');
    Route::get('/holidays', [ProgrammingScheduleController::class, 'holidays'])->name('holidays');
    Route::get('/create/mass', [ProgrammingScheduleController::class, 'createMass'])->name('create-mass');
    Route::get('/create', [ProgrammingScheduleController::class, 'create'])->name('create');
    Route::get('/groups/{personnelGroup}/data', [ProgrammingScheduleController::class, 'groupData'])->name('groups.data');
    Route::get('/groups/{personnelGroup}/details', [ProgrammingScheduleController::class, 'groupDetails'])->name('groups.details');
    Route::get('/history', [ProgrammingScheduleController::class, 'allHistory'])->name('history.all');
    Route::post('/availability', [ProgrammingScheduleController::class, 'validateAvailability'])->name('availability');
    Route::post('/', [ProgrammingScheduleController::class, 'store'])->name('store');
    Route::get('/{schedule}/history', [ProgrammingScheduleController::class, 'history'])->name('history');
    Route::get('/{schedule}/edit', [ProgrammingScheduleController::class, 'edit'])->name('edit');
    Route::put('/{schedule}', [ProgrammingScheduleController::class, 'update'])->name('update');
    Route::patch('/{schedule}/finish', [ProgrammingScheduleController::class, 'finish'])->name('finish');
    Route::delete('/{schedule}', [ProgrammingScheduleController::class, 'destroy'])->name('destroy');
});

