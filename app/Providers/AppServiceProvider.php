<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (\App\Support\UserPermissions::keys() as $permission) {
            \Illuminate\Support\Facades\Gate::define($permission, fn ($user) => $user->hasPermission($permission));
        }
        $filterMenu = function (array $items) use (&$filterMenu): array {
            foreach ($items as &$item) {
                if (isset($item['route'])) {
                    $abilities = \App\Support\UserPermissions::forRoute($item['route']);
                    if ($abilities !== []) $item['can'] = $abilities[0];
                }
                if (isset($item['submenu'])) $item['submenu'] = $filterMenu($item['submenu']);
            }
            return $items;
        };
        config(['adminlte.menu' => $filterMenu(config('adminlte.menu', []))]);
    }
}
