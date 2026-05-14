<?php

namespace App\Http\Requests\Operacion;

use Illuminate\Foundation\Http\FormRequest;

class StorePedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'remitente' => ['required', 'array'],
            'remitente.cuit' => ['required', 'string', 'max:32'],
            'remitente.razon_social' => ['required', 'string', 'max:255'],

            'destinatario' => ['required', 'array'],
            'destinatario.cuit' => ['required', 'string', 'max:32'],
            'destinatario.razon_social' => ['required', 'string', 'max:255'],

            'paga' => ['required', 'in:origen,destino'],

            'remito_numero' => ['nullable', 'string', 'max:255'],
            'bultos' => ['required', 'integer', 'min:0'],
            'palets' => ['required', 'integer', 'min:0'],
            'valor_declarado' => ['required', 'numeric', 'min:0'],
            'es_devolucion' => ['sometimes', 'boolean'],
            'cr_importe' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
