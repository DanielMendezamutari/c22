<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GastoCajaChica extends Model
{
    protected $table = 'gastos_caja_chica';

    protected $fillable = [
        'sucursal_id',
        'turno_id',
        'planilla_id',
        'fecha',
        'concepto',
        'categoria',
        'monto_bs',
        'es_reposicion_de_ventas',
        'foto_comprobante_url',
        'estado_comprobante',
        'emparejado_ocr',
        'aprobado_por_id',
        'aprobado_at',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_bs' => 'decimal:2',
        'es_reposicion_de_ventas' => 'boolean',
        'emparejado_ocr' => 'boolean',
        'aprobado_at' => 'datetime',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function planilla(): BelongsTo
    {
        return $this->belongsTo(PlanillaCaja::class, 'planilla_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobado_por_id');
    }
}
