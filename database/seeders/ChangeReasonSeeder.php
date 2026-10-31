<?php

namespace Database\Seeders;

use App\Models\Programming\ChangeReason;
use Illuminate\Database\Seeder;

class ChangeReasonSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Imprevistos' => 'Cambio requerido por imprevistos.',
            'Mantenimiento' => 'Cambio requerido por mantenimiento del vehículo.',
            'Emergencia operativa' => 'Cambio requerido por una emergencia operativa.',
            'Vacaciones' => 'Cambio requerido por vacaciones del personal.',
            'Reprogramación' => 'Ajuste de recursos o turnos de la programación.',
        ] as $name => $description) {
            // Do not overwrite edited reasons or re-create a reason the user deleted.
            if (! ChangeReason::withTrashed()->where('name', $name)->exists()) {
                ChangeReason::create(compact('name', 'description'));
            }
        }
    }
}
