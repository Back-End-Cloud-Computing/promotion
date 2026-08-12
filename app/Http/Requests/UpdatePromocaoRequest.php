<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondeComErroSimples;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromocaoRequest extends FormRequest
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
            'produto_id' => [
                'sometimes', 'integer', 'min:1',
                Rule::unique('promocoes', 'produto_id')->ignore($this->route('promocao')),
            ],
            'desconto_pct' => ['sometimes', 'integer', 'between:1,100'],
            'categoria' => ['sometimes', 'in:Superiores,Inferiores,Inverno'],
            'campanha_id' => ['sometimes', 'nullable', 'exists:campanhas,id'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
