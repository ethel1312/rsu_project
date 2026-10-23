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
        Schema::create('vacation_balances', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->year('year');
            $table->unsignedTinyInteger('days_entitled')->default(30);
            $table->unsignedTinyInteger('days_taken')->default(0);

            $table->timestamps();

            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->restrictOnDelete();

            $table->unique(['employee_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacation_balances');
    }
};
