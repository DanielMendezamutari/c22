<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecetaTransformacion extends Model
{
    protected $table = 'recetas_transformacion';

    protected $fillable = [
        'nombre',
        'insumo_origen_id',
        'insumo_secundario_id',
        'producto_destino_id',
        'tarifa_comision_unidad',
        'ratio_referencia_esperado',
        'umbral_desviacion_alerta',
        'activo',
    ];

    protected $casts = [
        'tarifa_comision_unidad' => 'decimal:2',
        'ratio_referencia_esperado' => 'decimal:3',
        'umbral_desviacion_alerta' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function insumoOrigen(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'insumo_origen_id');
    }

    public function insumoSecundario(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'insumo_secundario_id');
    }

    public function productoDestino(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_destino_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'receta_id');
    }
}
