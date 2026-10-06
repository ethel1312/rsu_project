<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\admin\Employees\AttendanceClockController;

Route::get('/employees/attendances/clock', [AttendanceClockController::class, 'create'])
    ->name('employees.attendances.clock');
Route::post('/employees/attendances/clock', [AttendanceClockController::class, 'store'])
    ->middleware('throttle:30,1')->name('employees.attendances.mark');

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return redirect('/admin');
    })->name('dashboard');
});


