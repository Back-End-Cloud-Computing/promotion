<?php

namespace App\Domain\Promotions\Requests;

use App\Http\Requests\Concerns\RespondsWithSimpleError;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'min:1'],
            'discount_percentage' => ['required', 'integer', 'between:1,100'],
            'category' => ['required', 'in:Superiores,Inferiores,Inverno'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
