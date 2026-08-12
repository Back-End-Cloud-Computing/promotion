<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * O resto do sistema responde erro como {"error": "mensagem"}, não no formato
 * padrão do Laravel ({"message": ..., "errors": {...}}). Este trait alinha os
 * FormRequests da API a esse contrato.
 */
trait RespondeComErroSimples
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first(),
        ], 422));
    }
}
