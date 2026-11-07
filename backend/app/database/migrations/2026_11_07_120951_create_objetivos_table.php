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
        Schema::create('objetivos', function (Blueprint $table) {
            $table->id();
            //Campo de usuario al que pertenece el objetivo
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('nombre', 255); // Campo de nombre del objetivo
            $table->decimal('cantidad_objetivo', 15, 2); // Campo de cantidad objetivo
            // Campo de fecha objetivo para alcanzar el objetivo
            //Se añade nullable para permitir que el objetivo no tenga fecha límite
            $table->date('fecha_objetivo')->nullable();
            // Campo de saldo actual del objetivo
            $table->decimal('saldo_actual', 15, 2)->default(0.00);
            //Fecha a la que se corresponde el saldo inicial del objetivo
            $table->date('fecha_saldo');
            //Campo para comprobar si el objetivo es el fondo de emergencia; por defecto es falso
            $table->boolean('es_fondo_emergencia')->default(false);
            //Campo para comprobar si el objetivo está configurado; por defecto es falso
            $table->boolean('configurado')->default(false);
            //Campo fecha creación del objetivo; se coge la fecha actual por defecto al crearlo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('objetivos');
    }
};
