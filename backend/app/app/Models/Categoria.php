<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'nombre', 'tipo'])]
class Categoria extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class);
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class);
    }
}
