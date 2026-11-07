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
        Schema::create('aportaciones_objetivos', function (Blueprint $table) {
            $table->id();
            //Campo de usuario al que pertenece la aportación
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            //Campo de objetivo al que pertenece la aportación
            $table->foreignId('objetivo_id')->constrained('objetivos')->onDelete('cascade');
            //Campo de banco al que pertenece la aportación
            $table->foreignId('banco_id')->constrained('bancos')->onDelete('cascade');
            //Campo de cantidad de la aportación
            $table->decimal('importe', 15, 2);
            //Campo de fecha de la aportación
            $table->date('fecha');
            //Campo de descripción de la aportación
            $table->string('descripcion', 255)->nullable();
            //Campo de fecha de creación de la aportación; se coge la fecha actual por defecto al crearlo
            $table->date('fecha_creacion')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aportaciones_objetivos');
    }
};
