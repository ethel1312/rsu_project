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

/*
|--------------------------------------------------------------------------
| GESTIÓN DE PERSONAL
|--------------------------------------------------------------------------
| except(['show']): los listados usan modal, no hay página de detalle.
*/
Route::resource('employee_types', EmployeeTypeController::class)->except(['show'])->names('admin.employee_types');

Route::resource('employees', EmployeeController::class)->except(['show'])->names('admin.employees');

// Route::resource('contracts', ContractController::class)->except(['show'])->names('admin.contracts');

// Route::resource('vacations', VacationController::class)->except(['show'])->names('admin.vacations');

Route::middleware('auth')->group(function () {
    Route::get('attendances/employees', [AttendanceController::class, 'searchEmployees'])->name('admin.attendances.employees');
    Route::get('attendances/preview', [AttendanceController::class, 'preview'])->name('admin.attendances.preview');
    Route::resource('attendances', AttendanceController::class)->except(['show'])->names('admin.attendances');
});
