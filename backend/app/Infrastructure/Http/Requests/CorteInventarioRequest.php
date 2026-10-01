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
            'es_suplencia' => 'sometimes|boolean',
            'realizado_por_usuario_id' => 'sometimes|integer|exists:usuarios,id',
            'corte_inicial' => 'sometimes|array',
            'corte_inicial.*.producto_id' => 'nullable|integer',
            'corte_inicial.*.cantidad' => 'required_with:corte_inicial|numeric|min:0',
            'corte_inicial.*.es_provisional' => 'nullable|boolean',
            'corte_inicial.*.nombre_provisional' => 'nullable|string|max:150',
            'corte_inicial.*.es_licor' => 'nullable|boolean',
            'corte_final' => 'sometimes|array',
            'corte_final.*.producto_id' => 'nullable|integer',
            'corte_final.*.cantidad' => 'required_with:corte_final|numeric|min:0',
            'corte_final.*.es_provisional' => 'nullable|boolean',
            'corte_final.*.nombre_provisional' => 'nullable|string|max:150',
            'corte_final.*.es_licor' => 'nullable|boolean',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $cortes = $this->input('corte_inicial') ?? $this->input('corte_final') ?? [];

            foreach ($cortes as $index => $item) {
                $esProvisional = (bool) ($item['es_provisional'] ?? false);
                if ($esProvisional) {
                    if (empty($item['nombre_provisional']) || trim($item['nombre_provisional']) === '') {
                        $validator->errors()->add(
                            "cortes.{$index}.nombre_provisional",
                            "Debe especificar un nombre para el producto provisional no catalogado."
                        );
                    }
                } else {
                    if (empty($item['producto_id'])) {
                        $validator->errors()->add(
                            "cortes.{$index}.producto_id",
                            "El producto_id es obligatorio para productos del catálogo oficial."
                        );
                    }
                }

                if (isset($item['cantidad'])) {
                    $cant = (float) $item['cantidad'];
                    try {
                        // Validar cumplimiento de la Constitución III: Fracciones en múltiplos de 0.25
                        FraccionLicor::desdeDecimal($cant);
                    } catch (Exception $e) {
                        $validator->errors()->add(
                            "cortes.{$index}.cantidad",
                            "La cantidad {$cant} no es un múltiplo válido de fracción (0.25, 0.50, 0.75 o entero)."
                        );
                    }
                }
            }
        });
    }
}
