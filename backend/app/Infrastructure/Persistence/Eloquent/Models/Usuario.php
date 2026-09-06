<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'apellido',
        'rol',
        'pin_hash',
        'modalidad_cobro',
        'sueldo_base_semanal',
        'sucursal_actual_id',
        'saldo_deudor_acumulado',
        'activo',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected $casts = [
        'sueldo_base_semanal' => 'decimal:2',
        'saldo_deudor_acumulado' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function sucursalActual(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_actual_id');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'barman_id');
    }

    public function sanciones(): HasMany
    {
        return $this->hasMany(SancionInventario::class, 'usuario_id');
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(LiquidacionSemanal::class, 'usuario_id');
    }
}
