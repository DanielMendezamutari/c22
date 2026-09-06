<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'nombre',
        'codigo_barra',
        'tipo',
        'unidad_medida',
        'es_transformable',
        'activo',
    ];

    protected $casts = [
        'es_transformable' => 'boolean',
        'activo' => 'boolean',
    ];

    public function recetasComoInsumo(): HasMany
    {
        return $this->hasMany(RecetaTransformacion::class, 'insumo_origen_id');
    }

    public function recetasComoDestino(): HasMany
    {
        return $this->hasMany(RecetaTransformacion::class, 'producto_destino_id');
    }

    public function combos(): HasMany
    {
        return $this->hasMany(RecetaCombo::class, 'producto_terminado_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'producto_id');
    }
}
