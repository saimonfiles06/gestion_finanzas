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
        Schema::create('bancos', function (Blueprint $table) {
            $table->id()->autoIncrement(); // Campo de ID banco autoincremental
            //Campo de usuario al que pertenece el banco
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
             // Campo de nombre del banco único, evita nombres duplicados
            $table->string('nombre', 255)->unique();
            $table->decimal('saldo_inicial', 15, 2)->default(0.00);
            //Fecha a la que se corresponde el saldo inicial
            $table->date('fecha_inicial');
            //Campos de permisos para ingresos y gastos; por defecto se permite ambos
            $table->boolean('permite_ingresos')->default(true);
            $table->boolean('permite_gastos')->default(true);
            $table->boolean('eliminado')->default(false);
            //Fecha de creación del banco; se coge la fecha actual por defecto al crearlo
            $table->date('fecha_creacion')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bancos');
    }
};
