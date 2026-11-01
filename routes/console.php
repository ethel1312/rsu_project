<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Explicit setup/recovery command; public registration never grants administrator access.
\Illuminate\Support\Facades\Artisan::command('users:make-admin {email}', function () {
    $user = \App\Models\User::where('email', $this->argument('email'))->first();
    if (! $user) { $this->error('No existe un usuario con ese correo.'); return 1; }
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'user_type' => 'Administrador'])->save();
    $this->info('Administrador habilitado: '.$user->email);
})->purpose('Designar explícitamente un administrador del sistema');
