<?php

namespace Database\Factories;

use App\Domain\Campaigns\Entities\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Campaign '.fake()->word(),
            'description' => fake()->optional()->sentence(),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'active' => true,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
