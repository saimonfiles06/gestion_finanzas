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
        Schema::create('movimientos_bancos', function (Blueprint $table) {
            $table->id();
            //Campo de banco al que pertenece el movimiento
            $table->foreignId('banco_id')->constrained('bancos')->onDelete('cascade');
            //Campo de tipo de movimiento
            //(ingreso, gasto, traspaso_entrada, traspaso_salida)
            $table->enum('tipo', ['ingreso', 'gasto', 'traspaso_entrada', 'traspaso_salida']);
            //Campo de cantidad del movimiento
            $table->decimal('importe', 15, 2);
            //Campo de fecha del movimiento
            $table->date('fecha');
            //Campo de descripción del movimiento
            $table->string('descripcion', 255)->nullable();
            //Campo de fecha de creación del movimiento; se coge la fecha actual por defecto al crearlo
            $table->date('fecha_creacion')->useCurrent();
            //Campo de registro al que pertenece el movimiento (opcional, ya que no todos los movimientos tienen un registro asociado)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_bancos');
    }
};
