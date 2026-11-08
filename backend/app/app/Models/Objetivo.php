<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'nombre',
    'cantidad_objetivo',
    'fecha_objetivo',
    'saldo_actual',
    'fecha_saldo',
    'es_fondo_emergencia',
    'configurado',
])]
class Objetivo extends Model
{
    protected function casts(): array
    {
        return [
            'cantidad_objetivo' => 'decimal:2',
            'fecha_objetivo' => 'date:Y-m-d',
            'saldo_actual' => 'decimal:2',
            'fecha_saldo' => 'date:Y-m-d',
            'es_fondo_emergencia' => 'boolean',
            'configurado' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aportaciones(): HasMany
    {
        return $this->hasMany(AportacionObjetivo::class);
    }
}
?>