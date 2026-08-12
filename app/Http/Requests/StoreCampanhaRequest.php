<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondeComErroSimples;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCampanhaRequest extends FormRequest
{
    use RespondeComErroSimples;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'inicia_em' => ['required', 'date'],
            'termina_em' => ['required', 'date', 'after:inicia_em'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
