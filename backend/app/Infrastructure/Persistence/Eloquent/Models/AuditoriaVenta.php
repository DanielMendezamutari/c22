<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AuditoriaVenta extends Model
{
    protected $table = 'auditorias_ventas';

    protected $fillable = [
        'turno_id',
        'admin_id',
        'producto_id',
        'stock_inicial',
        'ingresos',
        'traspasos_netos',
        'materia_prima_usada',
        'producto_terminado',
        'bajas',
        'stock_final',
        'consumo_fisico_calculado',
        'ventas_ticket_z',
        'diferencia',
        'resultado',
        'sancion_monto',
        'observaciones',
        'fecha_auditoria',
    ];

    protected $casts = [
        'stock_inicial' => 'decimal:2',
        'ingresos' => 'decimal:2',
        'traspasos_netos' => 'decimal:2',
        'materia_prima_usada' => 'decimal:2',
        'producto_terminado' => 'decimal:2',
        'bajas' => 'decimal:2',
        'stock_final' => 'decimal:2',
        'consumo_fisico_calculado' => 'decimal:2',
        'ventas_ticket_z' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'sancion_monto' => 'decimal:2',
        'fecha_auditoria' => 'datetime',
    ];

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'admin_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function sancion(): HasOne
    {
        return $this->hasOne(SancionInventario::class, 'auditoria_id');
    }
}
