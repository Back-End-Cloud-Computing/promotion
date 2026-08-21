<?php

namespace App\Domain\Promotions\Resources;

use App\Domain\Promotions\Entities\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Promotion */
class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'campaign_id' => $this->campaign_id,
            'discount_percentage' => $this->discount_percentage,
            'category' => $this->category,
            'active' => $this->active,
            'created_at' => $this->created_at,
        ];
    }
}
