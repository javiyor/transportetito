<?php

namespace App\Http\Requests\Operacion;

use Illuminate\Foundation\Http\FormRequest;

class StoreManifiestoIngresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'deposito_id' => ['required', 'integer', 'exists:depositos,id'],

            'fecha' => ['required', 'date'],

            'transporte' => ['nullable', 'string', 'max:255'],
            'chofer' => ['nullable', 'string', 'max:255'],
            'patente_camion' => ['nullable', 'string', 'max:32'],
            'patente_acoplado' => ['nullable', 'string', 'max:32'],

            'ciudad_origen' => ['nullable', 'string', 'max:255'],
            'ciudad_destino' => ['nullable', 'string', 'max:255'],

            'valor_asegurado' => ['nullable', 'numeric', 'min:0'],
            'gastos_envio' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
