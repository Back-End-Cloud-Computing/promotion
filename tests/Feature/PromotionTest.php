<?php

use App\Domain\Promotions\Entities\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['service.jwt_public_key' => jwtTestPublicKey()]);
});

function asAdminPromotion(): array
{
    return ['Authorization' => 'Bearer '.jwtTestToken(['email' => 'admin@ganjj.com'])];
}

// Mesma regra do cupom: unicidade é conflito (409), contra a constraint UNIQUE
// de verdade — não validação de formato da aplicação.
it('recusa promoção para produto que já tem uma', function () {
    Promotion::factory()->create(['product_id' => 1]);

    postJson('/api/promotions', [
        'product_id' => 1,
        'discount_percentage' => 50,
        'category' => 'Inverno',
    ], asAdminPromotion())
        ->assertStatus(409)
        ->assertJson(['error' => 'Produto já possui promoção']);

    expect(Promotion::count())->toBe(1);
});
