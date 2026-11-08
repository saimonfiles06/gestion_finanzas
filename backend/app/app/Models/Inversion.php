<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'banco_id', 'importe', 'fecha', 'descripcion'])]
class Inversion extends Model
{
    // Laravel deduciría "inversions", por eso se indica la tabla.
    protected $table = 'inversiones';

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
}
?>