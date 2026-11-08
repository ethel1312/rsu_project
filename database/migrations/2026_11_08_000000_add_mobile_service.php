<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', fn (Blueprint $t) => $t->uuid('mobile_mark_key')->nullable()->unique());
        Schema::create('citizens', function (Blueprint $t) {
            $t->id();
            $t->string('dni', 8)->unique();
            $t->string('first_name', 100);
            $t->string('last_name', 100);
            $t->string('email')->unique();
            $t->string('password');
            $t->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('mobile_sessions', function (Blueprint $t) {
            $t->id();
            $t->char('token_hash', 64)->unique();
            $t->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('citizen_id')->nullable()->constrained()->cascadeOnDelete();
            $t->timestamp('expires_at')->index();
        });
        Schema::table('schedule_runs', function (Blueprint $t) {
            $t->string('observation', 500)->nullable();
            $t->unsignedBigInteger('last_sample_ms')->nullable();
        });
        Schema::create('service_ratings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('schedule_run_id')->constrained()->cascadeOnDelete();
            $t->foreignId('citizen_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('stars');
            $t->string('comment', 500)->nullable();
            $t->timestamps();
            $t->unique(['schedule_run_id', 'citizen_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $t) {
            $t->dropUnique(['mobile_mark_key']);
            $t->dropColumn('mobile_mark_key');
        });
        Schema::dropIfExists('service_ratings');
        Schema::table('schedule_runs', fn (Blueprint $t) => $t->dropColumn(['observation', 'last_sample_ms']));
        Schema::dropIfExists('mobile_sessions');
        Schema::dropIfExists('citizens');
    }
};
