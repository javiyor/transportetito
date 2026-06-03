<?php

namespace App\Http\Requests\Operacion;

use Illuminate\Foundation\Http\FormRequest;

class ImportCargaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'since' => ['required', 'date'],
            'deposito_id' => ['required', 'integer', 'exists:depositos,id'],
        ];
    }
}
