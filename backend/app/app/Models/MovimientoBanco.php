<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'banco_id',
    'registro_id',
    'tipo',
    'importe',
    'fecha',
    'descripcion',
])]
class MovimientoBanco extends Model
{
    // Laravel deduciría "movimiento_bancos", por eso se indica la tabla.
    protected $table = 'movimientos_bancos';

    protected function casts(): array
    {
        return [
            'importe' => 'decimal:2',
            'fecha' => 'date:Y-m-d',
        ];
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    /**
     * Es nulo en los traspasos entre cuentas.
     */
    public function registro(): BelongsTo
    {
        return $this->belongsTo(Registro::class);
    }
}
?>