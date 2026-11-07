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
        Schema::create('registros', function (Blueprint $table) {
            $table->id();
            //Campo de usuario al que pertenece el registro
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            //Campo de banco al que pertenece el registro
            $table->foreignId('banco_id')->constrained('bancos')->onDelete('cascade');
            //Campo de categoría al que pertenece el registro
            $table->foreignId('categoria_id')->constrained('categorias')->onDelete('cascade');
            //Tipo de registro (ingreso o gasto)
            $table->enum('tipo', ['ingreso', 'gasto']);
            //Campo de cantidad del registro
            $table->decimal('importe', 15, 2);
            //Campo de fecha del registro
            $table->date('fecha');
            //Campo de descripción del registro
            $table->string('descripcion', 255)->nullable();
            //Campo de fecha de creación del registro; se coge la fecha actual por defecto al crearlo
            $table->date('fecha_creacion')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registros');
    }
};
