<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programming_schedules', function (Blueprint $table) {
            $table->foreignId('parent_schedule_id')->nullable()->constrained('programming_schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programming_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_schedule_id');
        });
    }
};
