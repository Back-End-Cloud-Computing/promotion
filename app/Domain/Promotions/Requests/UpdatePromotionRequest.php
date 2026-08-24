<?php

namespace App\Domain\Promotions\Requests;

use App\Http\Requests\Concerns\RespondsWithSimpleError;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    use RespondsWithSimpleError;

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
            'product_id' => [
                'sometimes', 'integer', 'min:1',
                Rule::unique('promotions', 'product_id')->ignore($this->route('promotion')),
            ],
            'discount_percentage' => ['sometimes', 'integer', 'between:1,100'],
            'category' => ['sometimes', 'in:Superiores,Inferiores,Inverno'],
            'campaign_id' => ['sometimes', 'nullable', 'exists:campaigns,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
