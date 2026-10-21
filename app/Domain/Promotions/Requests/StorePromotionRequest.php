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

    // Aceita o id como número ou string (ObjectId do Mongo) e grava sempre string.
    protected function prepareForValidation(): void
    {
        if (is_int($this->input('product_id'))) {
            $this->merge(['product_id' => (string) $this->input('product_id')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'string', 'min:1', 'max:64'],
            'discount_percentage' => ['required', 'integer', 'between:1,100'],
            'category' => ['required', 'in:Superiores,Inferiores,Inverno'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
