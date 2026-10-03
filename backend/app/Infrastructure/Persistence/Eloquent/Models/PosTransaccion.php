<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosTransaccion extends Model
{
    protected $table = 'pos_transacciones';

    protected $fillable = [
        'sucursal_id',
        'turno_id',
        'pos_detalle_id',
        'pos_cuenta_id',
        'fecha_hora',
        'pos_producto_id',
        'pos_nombre_producto',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'metodo_pago',
        'estado_mapeo',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }
}
