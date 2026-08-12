<?php

namespace Database\Factories;

use App\Models\Campanha;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campanha>
 */
class CampanhaFactory extends Factory
{
    protected $model = Campanha::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Campanha '.fake()->word(),
            'descricao' => fake()->optional()->sentence(),
            'inicia_em' => now()->subDay(),
            'termina_em' => now()->addDays(30),
            'ativo' => true,
        ];
    }

    public function encerrada(): static
    {
        return $this->state(fn () => [
            'inicia_em' => now()->subDays(60),
            'termina_em' => now()->subDay(),
        ]);
    }

    public function futura(): static
    {
        return $this->state(fn () => [
            'inicia_em' => now()->addDay(),
            'termina_em' => now()->addDays(30),
        ]);
    }

    public function inativa(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
