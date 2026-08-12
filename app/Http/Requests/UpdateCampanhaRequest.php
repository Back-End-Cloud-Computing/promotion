<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondeComErroSimples;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCampanhaRequest extends FormRequest
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
            'nome' => ['sometimes', 'string', 'max:255'],
            'descricao' => ['sometimes', 'nullable', 'string'],
            'inicia_em' => ['sometimes', 'date'],
            'termina_em' => ['sometimes', 'date', 'after:inicia_em'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
