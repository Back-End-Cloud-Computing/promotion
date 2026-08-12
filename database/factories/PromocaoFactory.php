<?php

namespace Database\Factories;

use App\Models\Promocao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promocao>
 */
class PromocaoFactory extends Factory
{
    protected $model = Promocao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'produto_id' => fake()->unique()->numberBetween(1, 100000),
            'campanha_id' => null,
            'desconto_pct' => fake()->numberBetween(5, 60),
            'categoria' => fake()->randomElement(['Superiores', 'Inferiores', 'Inverno']),
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }

    public function categoria(string $categoria): static
    {
        return $this->state(fn () => ['categoria' => $categoria]);
    }
}
