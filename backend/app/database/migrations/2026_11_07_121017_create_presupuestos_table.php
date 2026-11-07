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
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            //Campo de usuario al que pertenece el presupuesto
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            //Campo de categoría a la que pertenece el presupuesto
            $table->foreignId('categoria_id')->constrained('categorias')->onDelete('cascade');
            //Campo cantidad límite del presupuesto
            $table->decimal('cantidad_limite', 15, 2);
            //Periodo del presupuesto (mensual, anual)
            $table->enum('periodo', ['mensual', 'anual']);
            //Fecha de creación del presupuesto; se coge la fecha actual por defecto al crearlo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presupuestos');
    }
};
