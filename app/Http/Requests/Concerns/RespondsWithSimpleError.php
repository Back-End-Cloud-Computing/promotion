<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * O resto do sistema responde erro em {status, error, message, fields, timestamp},
 * não no formato padrão do Laravel ({"message": ..., "errors": {...}}). Este trait
 * alinha os FormRequests da API a esse contrato.
 */
trait RespondsWithSimpleError
{
    protected function failedValidation(Validator $validator): void
    {
        $fields = collect($validator->errors()->toArray())->map(fn ($messages) => $messages[0])->all();

        throw new HttpResponseException(response()->error(422, 'Há campos inválidos na requisição.', $fields));
    }
}
