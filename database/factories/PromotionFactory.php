<?php

namespace Database\Factories;

use App\Domain\Promotions\Entities\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => fake()->unique()->numberBetween(1, 100000),
            'campaign_id' => null,
            'discount_percentage' => fake()->numberBetween(5, 60),
            'category' => fake()->randomElement(['Superiores', 'Inferiores', 'Inverno']),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }

    public function category(string $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }
}
