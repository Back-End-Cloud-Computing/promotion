<?php

namespace App\Domain\Coupons\Resources;

use App\Domain\Coupons\Entities\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Coupon */
class CouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'minimum_value' => $this->minimum_value,
            'usage_limit' => $this->usage_limit,
            'usage_count' => $this->usage_count,
            'campaign_id' => $this->campaign_id,
            'active' => $this->active,
            'created_at' => $this->created_at,
        ];
    }
}
