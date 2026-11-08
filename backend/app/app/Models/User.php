<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['email', 'password', 'preferencia_modo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function bancos(): HasMany
    {
        return $this->hasMany(Banco::class);
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class);
    }

    public function objetivos(): HasMany
    {
        return $this->hasMany(Objetivo::class);
    }

    public function aportacionesObjetivos(): HasMany
    {
        return $this->hasMany(AportacionObjetivo::class);
    }

    public function inversiones(): HasMany
    {
        return $this->hasMany(Inversion::class);
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class);
    }
}
