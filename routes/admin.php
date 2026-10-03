<?php

use App\Http\Controllers\admin\AdminController;
use App\Http\Controllers\admin\BrandController;
use App\Http\Controllers\admin\BrandmdelController;
use App\Http\Controllers\admin\ColorController;
use App\Http\Controllers\admin\VehicleTypeController;
use App\Http\Controllers\admin\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AdminController::class, 'index']);

Route::resource('brands', BrandController::class)->names('admin.brands');

Route::resource('models',BrandmdelController::class)->names('admin.models');

Route::resource('colors',ColorController::class)->names('admin.colors');

Route::resource('vehicle_types',VehicleTypeController::class)->names('admin.vehicle_types');

Route::resource('vehicles', VehicleController::class)->names('admin.vehicles');

Route::get('vehicles/{id}/images', [VehicleController::class, 'getImages'])->name('admin.vehicles.images');
Route::post('vehicles/{id}/images', [VehicleController::class, 'uploadImage'])->name('admin.vehicles.images.upload');
Route::post('vehicles/images/{image_id}/profile', [VehicleController::class, 'setProfileImage'])->name('admin.vehicles.images.profile');
Route::delete('vehicles/images/{image_id}', [VehicleController::class, 'deleteImage'])->name('admin.vehicles.images.destroy');