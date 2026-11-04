<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name')->nullable();
            $table->string('dni', 8)->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('license', 50)->nullable();
            $table->string('user_type', 50)->default('Operador');
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_admin')->default(false);
            $table->json('permissions')->nullable();
        });
        $firstId = DB::table('users')->orderBy('id')->value('id');
        if ($firstId) DB::table('users')->where('id', $firstId)->update(['is_admin' => true, 'user_type' => 'Administrador']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['dni']);
            $table->dropColumn(['last_name', 'dni', 'birth_date', 'phone', 'license', 'user_type', 'address', 'is_active', 'is_admin', 'permissions']);
        });
    }
};
