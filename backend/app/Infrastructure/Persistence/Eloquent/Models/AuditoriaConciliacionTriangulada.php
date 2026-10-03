<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaConciliacionTriangulada extends Model
{
    protected $table = 'auditorias_conciliacion_triangulada';

    protected $fillable = [
        'turno_id',
        'sucursal_id',
        'total_pos_ventas_bs',
        'total_planilla_efectivo_bs',
        'total_voucher_deposito_bs',
        'diferencia_caja_bs',
        'responsable_caja_usuario_id',
        'botellas_vendidas_pos',
        'botellas_consumidas_inventario',
        'diferencia_botellas',
        'responsable_barra_usuario_id',
        'estado_semaforo',
        'observaciones',
    ];

    protected $casts = [
        'total_pos_ventas_bs' => 'decimal:2',
        'total_planilla_efectivo_bs' => 'decimal:2',
        'total_voucher_deposito_bs' => 'decimal:2',
        'diferencia_caja_bs' => 'decimal:2',
        'botellas_vendidas_pos' => 'decimal:2',
        'botellas_consumidas_inventario' => 'decimal:2',
        'diferencia_botellas' => 'decimal:2',
    ];

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function responsableCaja(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_caja_usuario_id');
    }

    public function responsableBarra(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_barra_usuario_id');
    }
}
