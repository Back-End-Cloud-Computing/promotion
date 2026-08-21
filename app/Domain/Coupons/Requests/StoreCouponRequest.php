<?php

namespace App\Domain\Coupons\Requests;

use App\Http\Requests\Concerns\RespondeComErroSimples;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:32'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'gt:0', $this->percentageValueRule()],
            'minimum_value' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cupom percentual acima de 100% não faz sentido de negócio. O tipo já é
     * obrigatório neste request, então basta ler o input diretamente.
     */
    protected function percentageValueRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($this->input('type') === 'percentage' && (float) $value > 100) {
                $fail('O valor percentual não pode ser maior que 100.');
            }
        };
    }
}
