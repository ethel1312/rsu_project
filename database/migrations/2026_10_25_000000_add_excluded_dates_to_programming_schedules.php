<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programming_schedules', function (Blueprint $table) {
            $table->json('excluded_dates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('programming_schedules', function (Blueprint $table) {
            $table->dropColumn('excluded_dates');
        });
    }
};
