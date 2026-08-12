<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondeComErroSimples;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePromocaoRequest extends FormRequest
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
            'produto_id' => ['required', 'integer', 'min:1'],
            'desconto_pct' => ['required', 'integer', 'between:1,100'],
            'categoria' => ['required', 'in:Superiores,Inferiores,Inverno'],
            'campanha_id' => ['nullable', 'exists:campanhas,id'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
