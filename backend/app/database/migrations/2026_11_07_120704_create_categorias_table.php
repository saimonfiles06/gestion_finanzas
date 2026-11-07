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
        Schema::create('categorias', function (Blueprint $table) {
            $table->id(); // Campo de ID categoría autoincremental
            //Campo de usuario al que pertenece la categoría
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('nombre', 255); // Campo de nombre de la categoría
            $table->enum('tipo', ['ingreso', 'gasto']); // Campo de tipo de categoría (ingreso o gasto)
            $table->timestamps(); // Campo de fecha de creación de la categoría; se coge la fecha actual por defecto al crearla
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
