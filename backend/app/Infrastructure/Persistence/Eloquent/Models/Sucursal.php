<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'codigo',
        'direccion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'sucursal_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'sucursal_actual_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'sucursal_id');
    }
}
