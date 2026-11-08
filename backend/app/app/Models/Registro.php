<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'banco_id',
    'categoria_id',
    'tipo',
    'importe',
    'fecha',
    'descripcion',
])]
class Registro extends Model
{
    protected function casts(): array
    {
        return [
            'importe' => 'decimal:2',
            'fecha' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function movimiento(): HasOne
    {
        return $this->hasOne(MovimientoBanco::class);
    }
}
?>