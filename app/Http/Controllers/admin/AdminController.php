<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;

class AdminController extends Controller
{
    public function index()
    {
        $routes = [
            'dashboard.view' => 'admin.programming.dashboard.index',
            'users.view' => 'admin.users.index', 'schedules.view' => 'admin.programming.schedules.index',
        ];
        foreach (\App\Support\UserPermissions::catalog() as $modules) {
            foreach ($modules as $key => $module) {
                if (! ($module['available'] ?? true)) continue;
                $prefix = in_array($key, ['shifts', 'zones', 'holidays', 'personnel-groups', 'reasons', 'changes']) ? 'admin.programming.' : 'admin.';
                $routes[$key.'.view'] ??= $prefix.$key.'.index';
            }
        }
        foreach ($routes as $permission => $route) {
            if (request()->user()->hasPermission($permission) && \Illuminate\Support\Facades\Route::has($route)) return redirect()->route($route);
        }
        return view('admin.users.no-access');
    }
}
