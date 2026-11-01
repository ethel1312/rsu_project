<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_changes', function (Blueprint $table) {
            $table->string('previous_status', 20)->nullable();
        });
        Schema::table('programming_schedule_histories', function (Blueprint $table) {
            $table->date('service_date')->nullable()->index();
            $table->string('source', 30)->nullable();
            $table->uuid('operation_uuid')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('programming_schedule_histories', function (Blueprint $table) {
            $table->dropIndex(['service_date']);
            $table->dropIndex(['operation_uuid']);
            $table->dropColumn(['service_date', 'source', 'operation_uuid']);
        });
        Schema::table('schedule_changes', fn (Blueprint $table) => $table->dropColumn('previous_status'));
    }
};
