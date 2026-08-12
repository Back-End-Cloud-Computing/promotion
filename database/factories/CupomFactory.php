<?php

namespace Database\Factories;

use App\Models\Cupom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cupom>
 */
class CupomFactory extends Factory
{
    protected $model = Cupom::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('PROMO##??')),
            'tipo' => 'percentual',
            'valor' => fake()->numberBetween(5, 50),
            'valor_minimo' => 0,
            'limite_uso' => null,
            'usos' => 0,
            'campanha_id' => null,
            'ativo' => true,
        ];
    }

    public function fixo(float $valor = 15): static
    {
        return $this->state(fn () => ['tipo' => 'fixo', 'valor' => $valor]);
    }

    /**
     * Limite atingido: o próximo consumo deve ser recusado.
     */
    public function esgotado(): static
    {
        return $this->state(fn () => ['limite_uso' => 5, 'usos' => 5]);
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
