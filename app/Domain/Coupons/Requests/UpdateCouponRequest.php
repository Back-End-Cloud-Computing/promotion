<?php

namespace App\Domain\Coupons\Requests;

use App\Domain\Coupons\Entities\Coupon;
use App\Http\Requests\Concerns\RespondeComErroSimples;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
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
            'code' => [
                'sometimes', 'string', 'max:32',
                Rule::unique('coupons', 'code')->ignore($this->route('coupon')),
            ],
            'type' => ['sometimes', 'in:percentage,fixed'],
            'value' => ['sometimes', 'numeric', 'gt:0', $this->percentageValueRule()],
            'minimum_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'campaign_id' => ['sometimes', 'nullable', 'exists:campaigns,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Atualização parcial: se "type" não vier no request, a regra usa o tipo
     * já gravado no cupom em vez de assumir que não é percentual.
     */
    protected function percentageValueRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            /** @var Coupon|null $coupon */
            $coupon = $this->route('coupon');
            $type = $this->input('type') ?? $coupon?->type?->value;

            if ($type === 'percentage' && (float) $value > 100) {
                $fail('O valor percentual não pode ser maior que 100.');
            }
        };
    }
}
