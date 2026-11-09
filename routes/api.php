<?php

use App\Http\Controllers\Mobile\AccessController;
use App\Http\Controllers\Mobile\CitizenController;
use App\Http\Controllers\Mobile\ServiceController;
use App\Http\Middleware\MobileSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');

Route::prefix('mobile')->group(function () {
    Route::get('zones/{zone}/timetable', [CitizenController::class, 'timetable'])->whereNumber('zone')->middleware('throttle:60,1');
    Route::post('password/forgot', [CitizenController::class, 'forgot'])->middleware('throttle:5,1');
    Route::view('password/reset','mobile.reset');
    Route::post('password/reset', [CitizenController::class, 'reset'])->middleware('throttle:10,1');
    Route::post('login', [AccessController::class, 'login'])->middleware('throttle:10,1');
    Route::post('register', [AccessController::class, 'register'])->middleware('throttle:5,1');
    Route::get('zones', [AccessController::class, 'zones'])->middleware('throttle:60,1');
    Route::middleware(MobileSession::class)->where(['schedule' => '[0-9]+', 'date' => '[0-9]{4}-[0-9]{2}-[0-9]{2}'])->group(function () {
        Route::post('logout', [AccessController::class, 'logout']);
        Route::match(['get','post'], 'profile', [CitizenController::class, 'profile']);
        Route::get('shifts', [ServiceController::class, 'shifts']);
        Route::get('attendance/preview', [ServiceController::class, 'attendancePreview']);
        Route::post('attendance', [ServiceController::class, 'mark'])->middleware('throttle:10,1');
        Route::get('schedules', [ServiceController::class, 'index']);
        Route::get('schedules/{schedule}/{date}', [ServiceController::class, 'detail']);
        foreach (['start', 'points', 'finish', 'rate', 'incident'] as $action) {
            Route::post("schedules/{schedule}/{date}/$action", [ServiceController::class, $action]);
        }
    });
});
