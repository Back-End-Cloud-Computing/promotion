<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RespondeComErroSimples;
use App\Models\Cupom;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCupomRequest extends FormRequest
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
            'codigo' => [
                'sometimes', 'string', 'max:32',
                Rule::unique('cupons', 'codigo')->ignore($this->route('cupom')),
            ],
            'tipo' => ['sometimes', 'in:percentual,fixo'],
            'valor' => ['sometimes', 'numeric', 'gt:0', $this->regraValorPercentual()],
            'valor_minimo' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'limite_uso' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'campanha_id' => ['sometimes', 'nullable', 'exists:campanhas,id'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Atualização parcial: se "tipo" não vier no request, a regra usa o tipo
     * já gravado no cupom em vez de assumir que não é percentual.
     */
    protected function regraValorPercentual(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            /** @var Cupom|null $cupom */
            $cupom = $this->route('cupom');
            $tipo = $this->input('tipo', $cupom?->tipo);

            if ($tipo === 'percentual' && (float) $value > 100) {
                $fail('O valor percentual não pode ser maior que 100.');
            }
        };
    }
}
