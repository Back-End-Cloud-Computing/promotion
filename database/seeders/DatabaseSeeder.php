<?php

namespace Database\Seeders;

use App\Domain\Campaigns\Entities\Campaign;
use App\Domain\Coupons\Entities\Coupon;
use App\Domain\Promotions\Entities\Promotion;
use Illuminate\Database\Seeder;

/**
 * Cenário de demonstração para exercitar a API no Postman sem cadastrar nada
 * à mão. Os cupons em estado inválido são o ponto: permitem demonstrar cada
 * motivo de recusa sem precisar mexer no relógio do sistema.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $winter = Campaign::create([
            'name' => 'Liquida Inverno',
            'description' => 'Casacos e jaquetas com desconto.',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
            'active' => true,
        ]);

        $ended = Campaign::create([
            'name' => 'Black Friday do ano passado',
            'starts_at' => now()->subYear(),
            'ends_at' => now()->subYear()->addDays(7),
            'active' => true,
        ]);

        $promotions = [
            ['product_id' => 1, 'discount_percentage' => 30, 'category' => 'Inverno', 'campaign_id' => $winter->id],
            ['product_id' => 2, 'discount_percentage' => 40, 'category' => 'Inverno', 'campaign_id' => $winter->id],
            ['product_id' => 3, 'discount_percentage' => 15, 'category' => 'Superiores', 'campaign_id' => null],
            ['product_id' => 4, 'discount_percentage' => 20, 'category' => 'Inferiores', 'campaign_id' => null],
            // Promoção presa a campanha encerrada: não deve aparecer em /api/sale.
            ['product_id' => 5, 'discount_percentage' => 70, 'category' => 'Superiores', 'campaign_id' => $ended->id],
            ['product_id' => 6, 'discount_percentage' => 25, 'category' => 'Superiores', 'active' => false],
        ];

        foreach ($promotions as $promotion) {
            Promotion::create($promotion);
        }

        $coupons = [
            // Válido, sem restrição.
            ['code' => 'BEMVINDO10', 'type' => 'percentage', 'value' => 10],
            // Válido, de valor fixo.
            ['code' => 'MENOS25', 'type' => 'fixed', 'value' => 25],
            // Recusa por valor mínimo.
            ['code' => 'FRETE200', 'type' => 'percentage', 'value' => 15, 'minimum_value' => 200],
            // Recusa por limite atingido.
            ['code' => 'ESGOTADO', 'type' => 'percentage', 'value' => 50, 'usage_limit' => 10, 'usage_count' => 10],
            // Recusa por estar inativo.
            ['code' => 'DESLIGADO', 'type' => 'percentage', 'value' => 30, 'active' => false],
            // Recusa por campanha encerrada.
            ['code' => 'EXPIRADO', 'type' => 'percentage', 'value' => 40, 'campaign_id' => $ended->id],
        ];

        foreach ($coupons as $coupon) {
            Coupon::create($coupon);
        }
    }
}
