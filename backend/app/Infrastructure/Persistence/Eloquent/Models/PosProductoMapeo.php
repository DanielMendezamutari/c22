<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosProductoMapeo extends Model
{
    protected $table = 'pos_producto_mapeo';

    protected $fillable = [
        'sucursal_id',
        'pos_producto_id',
        'pos_nombre_producto',
        'c22_producto_id',
        'c22_combo_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'c22_producto_id');
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(RecetaCombo::class, 'c22_combo_id');
    }
}
