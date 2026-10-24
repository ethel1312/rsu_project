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
        if (Schema::hasTable('employees')) {
            if (! Schema::hasColumns('employees', [
                'id', 'dni', 'first_name', 'last_name', 'birth_date', 'phone', 'email',
                'status', 'password', 'address', 'image_path', 'employee_type_id', 'created_at', 'updated_at',
            ])) {
                throw new RuntimeException('La tabla employees importada tiene una estructura incompleta. Revise sus columnas antes de migrar.');
            }

            return;
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->string('dni', 8)->unique();            
            $table->string('first_name', 100);                
            $table->string('last_name', 100);                 
            $table->date('birth_date');                      
            $table->string('phone', 9)->nullable();         
            $table->string('email', 100)->unique();
            $table->boolean('status')->default(true);        
            $table->string('password');                      
            $table->string('address');                        
            $table->string('image_path')->nullable();    
            
            $table->timestamps();

            $table->unsignedBigInteger('employee_type_id'); 

            $table->foreign('employee_type_id')
                ->references('id')
                ->on('employee_types')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
