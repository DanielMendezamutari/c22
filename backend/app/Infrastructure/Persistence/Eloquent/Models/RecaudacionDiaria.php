<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecaudacionDiaria extends Model
{
    protected $table = 'recaudaciones_diarias';

    protected $fillable = [
        'sucursal_id',
        'fecha',
        'planilla_id',
        'voucher_id',
        'monto_sobre_declarado_bs',
        'monto_voucher_banco_bs',
        'diferencia_bs',
        'estado_conciliacion',
        'alerta_whatsapp_enviada',
        'alerta_whatsapp_at',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_sobre_declarado_bs' => 'decimal:2',
        'monto_voucher_banco_bs' => 'decimal:2',
        'diferencia_bs' => 'decimal:2',
        'alerta_whatsapp_enviada' => 'boolean',
        'alerta_whatsapp_at' => 'datetime',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function planilla(): BelongsTo
    {
        return $this->belongsTo(PlanillaCaja::class, 'planilla_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(VoucherDeposito::class, 'voucher_id');
    }
}
