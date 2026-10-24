<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programming_schedules')) {
            Schema::create('programming_schedules', function (Blueprint $table) {
                $table->id();
                $table->uuid('batch_uuid')->nullable()->index();
                // Snapshots keep generated schedules stable if a template changes later.
                $table->unsignedBigInteger('personnel_group_id')->nullable()->index();
                $table->string('group_name', 150);
                $table->unsignedBigInteger('shift_id')->index();
                $table->string('shift_name', 150);
                $table->time('shift_start_time');
                $table->time('shift_end_time');
                $table->unsignedBigInteger('zone_id')->index();
                $table->string('zone_name', 150);
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->string('vehicle_label', 200);
                $table->unsignedBigInteger('driver_id')->index();
                $table->string('driver_name', 200);
                $table->json('helper_ids');
                $table->json('helper_names');
                $table->json('days_of_week');
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status', 20)->default('Programada');
                $table->timestamp('finished_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'start_date', 'end_date'], 'ps_status_dates_idx');
                $table->index(['vehicle_id', 'start_date', 'end_date'], 'ps_vehicle_dates_idx');
                $table->index(['driver_id', 'start_date', 'end_date'], 'ps_driver_dates_idx');
            });
        }

        if (! Schema::hasTable('programming_schedule_histories')) {
            Schema::create('programming_schedule_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('programming_schedule_id')->nullable()
                    ->constrained('programming_schedules')->nullOnDelete();
                $table->string('schedule_label', 350);
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 30);
                $table->json('before_data')->nullable();
                $table->json('after_data')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('programming_schedule_histories');
        Schema::dropIfExists('programming_schedules');
    }
};
