<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VoucherDeposito extends Model
{
    protected $table = 'vouchers_deposito';

    protected $fillable = [
        'banco_nombre',
        'nro_operacion',
        'fecha_deposito',
        'monto_depositado_bs',
        'titular_cuenta',
        'foto_url',
        'recaudador_usuario_id',
        'datos_ocr_json',
        'estado_ocr',
    ];

    protected $casts = [
        'fecha_deposito' => 'datetime',
        'monto_depositado_bs' => 'decimal:2',
        'datos_ocr_json' => 'array',
    ];

    public function recaudador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recaudador_usuario_id');
    }

    public function recaudacion(): HasOne
    {
        return $this->hasOne(RecaudacionDiaria::class, 'voucher_id');
    }
}
