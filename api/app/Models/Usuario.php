<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Usuario extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * MUY IMPORTANTE para Spatie con tu guard de API
     */
    protected $guard_name = 'api';

    protected $table = 'usuarios';

    protected $fillable = ['nombre', 'email', 'password', 'avatar'];

    protected $hidden = ['password'];

    /**
     * En Laravel 10/11/12 podés usar el cast "hashed" para hash automático
     */
    protected $casts = [
        'password' => 'hashed',
        'email_verified_at' => 'datetime', // por si lo agregás más adelante
    ];

    // ===== JWT =====
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // (Opcional) helpers de relaciones útiles para reportes:
    // public function ventas() { return $this->hasMany(Venta::class, 'usuario_id'); }
}
