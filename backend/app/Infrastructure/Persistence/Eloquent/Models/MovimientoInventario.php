<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'uuid_local',
        'turno_id',
        'sucursal_id',
        'producto_id',
        'tipo_movimiento',
        'cantidad',
        'foto_path',
        'receta_id',
        'ratio_calculado',
        'observaciones',
        'fecha_movimiento',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'ratio_calculado' => 'decimal:3',
        'fecha_movimiento' => 'datetime',
    ];

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function receta(): BelongsTo
    {
        return $this->belongsTo(RecetaTransformacion::class, 'receta_id');
    }
}
