<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una fila estable serializa altas y cambios del calendario, incluso vacío.
        Schema::create('maintenance_calendar_locks', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
        });
        DB::table('maintenance_calendar_locks')->insert(['id' => 1]);

        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });
        Schema::create('maintenance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('observation')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_completed')->nullable();
            $table->timestamps();
            $table->unique(['maintenance_schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_days');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('maintenances');
        Schema::dropIfExists('maintenance_calendar_locks');
    }
};
