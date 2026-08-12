<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondeComErroSimples;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCupomRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:32'],
            'tipo' => ['required', 'in:percentual,fixo'],
            'valor' => ['required', 'numeric', 'gt:0', $this->regraValorPercentual()],
            'valor_minimo' => ['nullable', 'numeric', 'min:0'],
            'limite_uso' => ['nullable', 'integer', 'min:1'],
            'campanha_id' => ['nullable', 'exists:campanhas,id'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cupom percentual acima de 100% não faz sentido de negócio. O tipo já é
     * obrigatório neste request, então basta ler o input diretamente.
     */
    protected function regraValorPercentual(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($this->input('tipo') === 'percentual' && (float) $value > 100) {
                $fail('O valor percentual não pode ser maior que 100.');
            }
        };
    }
}
