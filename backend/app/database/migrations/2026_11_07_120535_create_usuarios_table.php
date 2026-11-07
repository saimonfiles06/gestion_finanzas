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
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id()->autoIncrement(); // Campo de ID usuario autoincremental
            $table->string('email', 255)->unique(); // Campo de correo electrónico único
            $table->string('password', 255); // Campo de contraseña
            // Campo de preferencias de modo
            $table->enum('preferencias_modo', ['claro', 'oscuro'])->default('claro');
            // Campo de fecha de registro; se coge la fecha actual por defecto al registrarse
            $table->timestamp('fecha_registro')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
