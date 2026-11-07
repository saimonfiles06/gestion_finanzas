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
        Schema::create('inversiones', function (Blueprint $table) {
            $table->id();
            //Campo de usuario al que pertenece la inversión
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            //Campo de banco al que pertenece la inversión
            $table->foreignId('banco_id')->constrained('bancos')->onDelete('cascade');
            //Campo de cantidad de la inversión
            $table->decimal('importe', 15, 2);
            //Campo de fecha de la inversión
            $table->date('fecha');
            //Campo de descripción de la inversión
            $table->string('descripcion', 255)->nullable();
            //Campo de fecha de creación de la inversión; se coge la fecha actual por defecto al crearlo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inversiones');
    }
};
