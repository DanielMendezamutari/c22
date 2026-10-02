<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlanillaCaja extends Model
{
    protected $table = 'planillas_caja';

    protected $fillable = [
        'sucursal_id',
        'turno_id',
        'fecha_operativa',
        'foto_url',
        'total_ventas_declaradas_bs',
        'total_gastos_declarados_bs',
        'monto_sobre_efectivo_bs',
        'cajero_nombre',
        'datos_ocr_json',
        'estado_ocr',
        'auditado_por_id',
        'observaciones',
    ];

    protected $casts = [
        'fecha_operativa' => 'date',
        'total_ventas_declaradas_bs' => 'decimal:2',
        'total_gastos_declarados_bs' => 'decimal:2',
        'monto_sobre_efectivo_bs' => 'decimal:2',
        'datos_ocr_json' => 'array',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function auditadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'auditado_por_id');
    }

    public function recaudacion(): HasOne
    {
        return $this->hasOne(RecaudacionDiaria::class, 'planilla_id');
    }
}
