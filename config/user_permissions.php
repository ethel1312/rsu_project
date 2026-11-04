<?php

return [
    'Gestión de Usuarios' => ['users' => ['label' => 'Usuarios', 'extra' => ['manage' => 'Gestionar permisos']]],
    'Gestión de Vehículos' => [
        'colors' => ['label' => 'Colores'], 'brands' => ['label' => 'Marcas'],
        'models' => ['label' => 'Modelos'], 'vehicle_types' => ['label' => 'Tipos de vehículos'],
        'vehicles' => ['label' => 'Vehículos', 'extra' => ['manage-images' => 'Gestionar imágenes']],
        'maintenances' => ['label' => 'Mantenimientos', 'available' => false],
    ],
    'Gestión de Personal' => [
        'employee_types' => ['label' => 'Tipos de personal'], 'employees' => ['label' => 'Personal'],
        'contracts' => ['label' => 'Contratos'], 'attendances' => ['label' => 'Asistencia'],
        'vacations' => ['label' => 'Vacaciones'],
    ],
    'Gestión de Programación' => [
        'shifts' => ['label' => 'Turnos'], 'zones' => ['label' => 'Zonas', 'extra' => ['map-view' => 'Ver mapa']],
        'ubigeo' => ['label' => 'Ubigeo', 'available' => false],
        'holidays' => ['label' => 'Feriados'], 'personnel-groups' => ['label' => 'Grupos de personal'],
        'schedules' => ['label' => 'Programación'],
        'dashboard' => ['label' => 'Dashboard diario', 'actions' => ['view' => 'Ver', 'edit' => 'Operar']],
    ],
    'Gestión de Cambios' => [
        'reasons' => ['label' => 'Motivos'],
        'changes' => ['label' => 'Cambios de programación', 'actions' => ['view' => 'Ver', 'create' => 'Crear', 'delete' => 'Revertir']],
    ],
];
