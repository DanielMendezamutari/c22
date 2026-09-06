<?php

namespace App\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngresoMercaderiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid_local' => 'required|string',
            'turno_id' => 'required|integer|exists:turnos,id',
            'producto_id' => 'required|integer|exists:productos,id',
            'cantidad' => 'required|numeric|min:0.01',
            'foto' => 'nullable|file|image|max:10240', // Max 10MB
            'foto_base64' => 'nullable|string',
            'observaciones' => 'nullable|string|max:500',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->hasFile('foto') && empty($this->input('foto_base64')) && empty($this->input('foto_path'))) {
                $validator->errors()->add('foto', 'La evidencia fotográfica de la nota de entrega es obligatoria.');
            }
        });
    }
}
