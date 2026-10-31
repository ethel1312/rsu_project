<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('schedule_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_uuid')->index();
            $table->foreignId('programming_schedule_id')->constrained()->restrictOnDelete();
            $table->date('service_date')->index();
            $table->string('type', 20);
            $table->string('source', 20);
            $table->foreignId('change_reason_id')->constrained()->restrictOnDelete();
            $table->string('reason_name', 150);
            $table->text('detail')->nullable();
            $table->json('before_data');
            $table->json('after_data');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('schedule_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programming_schedule_id')->constrained()->restrictOnDelete();
            $table->date('service_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('route_points')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['programming_schedule_id', 'service_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_runs');
        Schema::dropIfExists('schedule_changes');
        Schema::dropIfExists('change_reasons');
    }
};
