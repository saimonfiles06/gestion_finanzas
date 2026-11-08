<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'nombre',
    'saldo_inicial',
    'fecha_inicial',
    'permite_ingresos',
    'permite_gastos',
    'eliminado',
])]
class Banco extends Model
{
    protected function casts(): array
    {
        return [
            'saldo_inicial' => 'decimal:2',
            'fecha_inicial' => 'date:Y-m-d',
            'permite_ingresos' => 'boolean',
            'permite_gastos' => 'boolean',
            'eliminado' => 'boolean',
        ];
    }
 
    /**
     * Bancos no eliminados (el borrado es lógico: se conserva el historial).
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('eliminado', false);
    }
 
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
 
    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class);
    }
 
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoBanco::class);
    }
 
    public function aportacionesObjetivos(): HasMany
    {
        return $this->hasMany(AportacionObjetivo::class);
    }
 
    public function inversiones(): HasMany
    {
        return $this->hasMany(Inversion::class);
    }
}
?>