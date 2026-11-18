<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_types')) {
            return;
        }

        DB::table('employee_types')->updateOrInsert(
            ['name' => 'Mantenimiento'],
            [
                'description' => 'Personal asignado a los mantenimientos programados de vehículos.',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_types')) {
            return;
        }

        $ids = DB::table('employee_types')->where('name', 'Mantenimiento')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        if (Schema::hasTable('employees')) {
            $ids = $ids->diff(DB::table('employees')->whereIn('employee_type_id', $ids)->pluck('employee_type_id'));
        }

        if ($ids->isNotEmpty()) {
            DB::table('employee_types')->whereIn('id', $ids)->delete();
        }
    }
};
