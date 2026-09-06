<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionSemanal extends Model
{
    protected $table = 'liquidaciones_semanales';

    protected $fillable = [
        'usuario_id',
        'semana_ano',
        'fecha_inicio',
        'fecha_fin',
        'total_turnos',
        'total_comisiones_brutas',
        'total_sanciones_deducidas',
        'total_neto_a_pagar',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'total_turnos' => 'integer',
        'total_comisiones_brutas' => 'decimal:2',
        'total_sanciones_deducidas' => 'decimal:2',
        'total_neto_a_pagar' => 'decimal:2',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
