<?php

namespace Database\Factories;

use App\Domain\Coupons\Entities\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PROMO##??')),
            'type' => 'percentage',
            'value' => fake()->numberBetween(5, 50),
            'minimum_value' => 0,
            'usage_limit' => null,
            'usage_count' => 0,
            'campaign_id' => null,
            'active' => true,
        ];
    }

    public function fixed(float $value = 15): static
    {
        return $this->state(fn () => ['type' => 'fixed', 'value' => $value]);
    }

    /**
     * Limite atingido: o próximo consumo deve ser recusado.
     */
    public function exhausted(): static
    {
        return $this->state(fn () => ['usage_limit' => 5, 'usage_count' => 5]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
