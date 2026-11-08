<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'objetivo_id',
    'banco_id',
    'importe',
    'fecha',
    'descripcion',
])]
class AportacionObjetivo extends Model
{
    // Laravel deduciría "aportacion_objetivos", por eso se indica la tabla.
    protected $table = 'aportaciones_objetivos';

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

    public function objetivo(): BelongsTo
    {
        return $this->belongsTo(Objetivo::class);
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }
}
?>