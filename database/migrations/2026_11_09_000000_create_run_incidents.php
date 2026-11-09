<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('run_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_run_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->string('category', 32);
            $table->string('note', 500);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('location_ms')->nullable();
            $table->timestamps();
            $table->unique(['schedule_run_id','request_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('run_incidents');
    }
};
