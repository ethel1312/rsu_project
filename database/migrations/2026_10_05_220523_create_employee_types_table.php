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
        if (Schema::hasTable('employee_types')) {
            if (! Schema::hasColumns('employee_types', ['id', 'name', 'description', 'created_at', 'updated_at'])) {
                throw new RuntimeException('La tabla employee_types importada tiene una estructura incompleta. Revise sus columnas antes de migrar.');
            }

            // La siguiente migración agrega is_default si el respaldo todavía no la contiene.
            return;
        }

        Schema::create('employee_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_types');
    }
};
