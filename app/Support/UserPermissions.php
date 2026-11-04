<?php

namespace App\Support;

class UserPermissions
{
    public static function catalog(): array
    {
        $sections = config('user_permissions');
        foreach ($sections as &$modules) {
            foreach ($modules as $key => &$module) {
                $actions = $module['actions'] ?? ['view' => 'Ver', 'create' => 'Crear', 'edit' => 'Editar', 'delete' => 'Eliminar'];
                $module['permissions'] = [];
                foreach ($actions + ($module['extra'] ?? []) as $action => $label) {
                    $module['permissions'][$key.'.'.$action] = $label.' '.$module['label'];
                }
            }
        }
        return $sections;
    }

    public static function keys(): array
    {
        $keys = [];
        foreach (self::catalog() as $modules) {
            foreach ($modules as $module) {
                if ($module['available'] ?? true) $keys = array_merge($keys, array_keys($module['permissions']));
            }
        }
        return $keys;
    }

    // Every admin endpoint must have an explicit module and action mapping.
    public static function forRoute(string $name): array
    {
        $name = preg_replace('/^admin\.(programming\.)?/', '', $name);
        [$module, $action] = array_pad(explode('.', $name, 2), 2, '');
        if ($module === 'users' && str_starts_with($action, 'permissions')) return ['users.manage'];
        if ($module === 'vehicles' && str_starts_with($action, 'images')) return ['vehicles.manage-images'];
        if ($module === 'zones' && in_array($action, ['map', 'map-data'])) return ['zones.map-view'];
        if ($module === 'schedules' && in_array($action, ['start', 'finish', 'route'])) {
            return $action === 'route' ? ['dashboard.view', 'schedules.view'] : ['dashboard.edit'];
        }
        $ability = match ($action) {
            'index', 'show', 'history', 'history.all', 'groups.details' => 'view',
            'create', 'create-mass', 'store', 'import' => 'create',
            'edit', 'update', 'approve', 'reject', 'toggle-status' => 'edit',
            'destroy' => 'delete',
            default => null,
        };
        if ($ability) return [$module.'.'.$ability];
        // Read-only helper endpoints are needed by both creation and editing forms.
        if (in_array($action, ['models', 'employees', 'preview', 'availability', 'balance', 'groups.data', 'holidays'])) {
            return [$module.'.create', $module.'.edit'];
        }
        return [];
    }
}
