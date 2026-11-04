<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Historical marks have no reliable shift; assign them explicitly through editing.
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->index(['employee_id', 'date', 'shift_id'], 'attendance_employee_date_shift');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendance_employee_date_shift');
            $table->dropForeign(['shift_id']);
            $table->dropColumn('shift_id');
        });
    }
};
