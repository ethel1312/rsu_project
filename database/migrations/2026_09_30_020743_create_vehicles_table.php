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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 100)->unique();
            $table->string('plate', 20)->unique(); 
            $table->text('description')->nullable();
            $table->integer('year');
            
            $table->integer('load_capacity');
            $table->integer('fuel_capacity');
            $table->integer('compact_capacity');
            $table->integer('occupant_capacity');
            $table->timestamps();
            
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('color_id');
            
            $table->foreign('type_id')->references('id')->on('vehicletypes');
            $table->foreign('brand_id')->references('id')->on('brands');
            $table->foreign('model_id')->references('id')->on('brandmodels');
            $table->foreign('color_id')->references('id')->on('colors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
