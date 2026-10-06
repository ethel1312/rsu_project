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
        // La base importada desde SQL puede contener ya la tabla del módulo.
        if (Schema::hasTable('attendances')) {
            if (! Schema::hasColumns('attendances', ['id', 'employee_id', 'date', 'time', 'type', 'status', 'notes', 'created_at', 'updated_at'])) {
                throw new RuntimeException('La tabla attendances existente no tiene la estructura esperada. Revise sus columnas antes de migrar.');
            }

            return;
        }

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('date');
            $table->time('time');
            $table->string('type', 10)->nullable();
            $table->string('status', 10)->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date', 'time']);
            $table->index(['date', 'time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
