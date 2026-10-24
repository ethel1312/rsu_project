<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('employees')->restrictOnDelete();
            $table->json('days_of_week');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('personnel_group_helpers', function (Blueprint $table) {
            $table->foreignId('personnel_group_id')->constrained('personnel_groups')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->primary(['personnel_group_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_group_helpers');
        Schema::dropIfExists('personnel_groups');
    }
};
