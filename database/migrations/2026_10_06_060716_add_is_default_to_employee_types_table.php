<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employee_types', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_types', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_types', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
