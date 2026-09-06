<?php

namespace App\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalcularAuditoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'turno_id' => 'required|integer|exists:turnos,id',
            'admin_id' => 'nullable|integer|exists:usuarios,id',
            'producto_id' => 'nullable|integer|exists:productos,id',
            'precio_unitario_sancion' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string|max:1000',
            'ventas_ticket_z' => 'required|array',
            'ventas_ticket_z.productos_individuales' => 'nullable|array',
            'ventas_ticket_z.productos_individuales.*.producto_id' => 'required_with:ventas_ticket_z.productos_individuales|integer',
            'ventas_ticket_z.productos_individuales.*.cantidad_vendida' => 'required_with:ventas_ticket_z.productos_individuales|numeric|min:0',
            'ventas_ticket_z.combos' => 'nullable|array',
            'ventas_ticket_z.combos.*.combo_id' => 'required_with:ventas_ticket_z.combos|integer',
            'ventas_ticket_z.combos.*.cantidad_vendida' => 'required_with:ventas_ticket_z.combos|integer|min:0',
        ];
    }
}
