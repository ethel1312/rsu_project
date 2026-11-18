<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained('maintenances')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->enum('type', ['Preventivo', 'Limpieza', 'Reparación']);
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->index(['day_of_week', 'start_time', 'end_time']);
            $table->index(['vehicle_id', 'day_of_week']);
            $table->index(['employee_id', 'day_of_week']);
        });

        Schema::create('maintenance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_schedule_id')->constrained('maintenance_schedules')->cascadeOnDelete();
            $table->date('maintenance_date');
            $table->text('observation')->nullable();
            $table->string('image_path')->nullable();
            $table->enum('status', ['Pendiente', 'Realizado', 'No realizado'])->default('Pendiente');
            $table->timestamps();
            $table->unique(['maintenance_schedule_id', 'maintenance_date']);
            $table->index(['maintenance_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_days');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('maintenances');
    }
};
