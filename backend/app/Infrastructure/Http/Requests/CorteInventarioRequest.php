<?php

namespace App\Infrastructure\Http\Requests;

use App\Domain\ValueObjects\FraccionLicor;
use Exception;
use Illuminate\Foundation\Http\FormRequest;

class CorteInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sucursal_id' => 'sometimes|required|integer|exists:sucursales,id',
            'barman_id' => 'sometimes|integer|exists:usuarios,id',
            'tipo_turno' => 'sometimes|required|string|in:dia,noche',
            'corte_inicial' => 'sometimes|array',
            'corte_inicial.*.producto_id' => 'required_with:corte_inicial|integer|exists:productos,id',
            'corte_inicial.*.cantidad' => 'required_with:corte_inicial|numeric|min:0',
            'corte_final' => 'sometimes|array',
            'corte_final.*.producto_id' => 'required_with:corte_final|integer|exists:productos,id',
            'corte_final.*.cantidad' => 'required_with:corte_final|numeric|min:0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $cortes = $this->input('corte_inicial') ?? $this->input('corte_final') ?? [];

            foreach ($cortes as $index => $item) {
                if (isset($item['cantidad'])) {
                    $cant = (float) $item['cantidad'];
                    try {
                        // Validar cumplimiento de la Constitución III: Fracciones en múltiplos de 0.25
                        FraccionLicor::desdeDecimal($cant);
                    } catch (Exception $e) {
                        $validator->errors()->add(
                            "cortes.{$index}.cantidad",
                            "La cantidad {$cant} para el producto {$item['producto_id']} no es un múltiplo válido de fracción (0.25, 0.50, 0.75 o entero)."
                        );
                    }
                }
            }
        });
    }
}
