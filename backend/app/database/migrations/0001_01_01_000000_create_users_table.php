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
        Schema::create('users', function (Blueprint $table) {
            // Campo de ID usuario autoincremental,
            // no hace falta especificar autoIncrement() ya que es implícito en el método id()    
            $table->id();
            $table->string('email', 255)->unique(); // Campo de correo electrónico único
            $table->string('password', 255); // Campo de contraseña
            // Campo de preferencias de modo
            $table->enum('preferencias_modo', ['claro', 'oscuro'])->default('claro');
            // Campo de fecha de registro; se coge la fecha actual por defecto al registrarse
            $table->timestamp('fecha_registro')->useCurrent();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
