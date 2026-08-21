<?php

use App\Domain\Campaigns\Entities\Campaign;
use App\Domain\Coupons\Entities\Coupon;
use App\Domain\Promotions\Entities\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['servico.internal_secret' => str_repeat('c', 40)]);
});

function internal(): array
{
    return ['x-internal-secret' => str_repeat('c', 40)];
}

it('calcula o desconto de um carrinho com promoção e cupom', function () {
    Promotion::factory()->create(['product_id' => 1, 'discount_percentage' => 30]);
    Coupon::factory()->create(['code' => 'INVERNO20', 'type' => 'percentage', 'value' => 20]);

    $r = postJson('/internal/discounts/calculate', [
        'items' => [['product_id' => 1, 'unit_price' => 49.90, 'quantity' => 2]],
        'coupon' => 'INVERNO20',
    ], internal())->assertOk();

    // 49,90 x 0,7 = 34,93 por item -> 69,86; menos 20% -> 55,89
    $r->assertJson([
        'subtotal' => '99.80',
        'promotions_discount' => '29.94',
        'coupon_discount' => '13.97',
        'total' => '55.89',
    ]);
});

it('devolve 200 com o motivo quando o cupom não existe', function () {
    Promotion::factory()->create(['product_id' => 1, 'discount_percentage' => 10]);

    // O carrinho precisa do total mesmo com código inválido: quem errou foi o
    // usuário digitando, não o serviço chamador.
    postJson('/internal/discounts/calculate', [
        'items' => [['product_id' => 1, 'unit_price' => 100, 'quantity' => 1]],
        'coupon' => 'NAOEXISTE',
    ], internal())
        ->assertOk()
        ->assertJson([
            'total' => '90.00',
            'coupon' => ['applied' => false, 'reason' => 'Cupom não encontrado'],
        ]);
});

it('recusa requisição sem itens', function () {
    postJson('/internal/discounts/calculate', ['items' => []], internal())
        ->assertStatus(422)
        ->assertJsonStructure(['error']);
});

it('recusa item com quantidade zero', function () {
    postJson('/internal/discounts/calculate', [
        'items' => [['product_id' => 1, 'unit_price' => 10, 'quantity' => 0]],
    ], internal())->assertStatus(422);
});

it('não deixa o desconto de um produto vazar para outro da mesma categoria', function () {
    $campaign = Campaign::factory()->create();

    // Mesma categoria e mesma campanha, descontos diferentes.
    Promotion::factory()->create([
        'product_id' => 99, 'discount_percentage' => 40,
        'category' => 'Inverno', 'campaign_id' => $campaign->id,
    ]);
    Promotion::factory()->create([
        'product_id' => 1, 'discount_percentage' => 10,
        'category' => 'Inverno', 'campaign_id' => $campaign->id,
    ]);

    postJson('/internal/discounts/calculate', [
        'items' => [['product_id' => 1, 'unit_price' => 100, 'quantity' => 1]],
    ], internal())
        ->assertOk()
        // 10%, o desconto do próprio produto — não os 40% do vizinho.
        ->assertJson(['total' => '90.00']);
});

it('ignora promoção de campanha encerrada', function () {
    $campaign = Campaign::factory()->ended()->create();
    Promotion::factory()->create([
        'product_id' => 1, 'discount_percentage' => 50, 'campaign_id' => $campaign->id,
    ]);

    postJson('/internal/discounts/calculate', [
        'items' => [['product_id' => 1, 'unit_price' => 100, 'quantity' => 1]],
    ], internal())
        ->assertOk()
        ->assertJson(['total' => '100.00']);
});

it('consome um uso do cupom', function () {
    Coupon::factory()->create(['code' => 'USAR', 'usage_limit' => 2]);

    postJson('/internal/coupons/usar/consume', [], internal())
        ->assertOk()
        ->assertJson(['code' => 'USAR', 'usage_count' => 1, 'usage_limit' => 2]);
});

it('recusa consumo de cupom esgotado', function () {
    Coupon::factory()->exhausted()->create(['code' => 'ESGOTADO']);

    postJson('/internal/coupons/ESGOTADO/consume', [], internal())
        ->assertStatus(409)
        ->assertJson(['error' => 'Limite de uso atingido']);
});

it('não consome uso ao apenas calcular', function () {
    Coupon::factory()->create(['code' => 'PREVIEW', 'usage_limit' => 1]);

    // O carrinho recalcula a cada mudança; se isso gastasse uso, um cupom de
    // 500 usos se esgotaria sem nenhuma venda.
    foreach (range(1, 3) as $ignored) {
        postJson('/internal/discounts/calculate', [
            'items' => [['product_id' => 1, 'unit_price' => 100, 'quantity' => 1]],
            'coupon' => 'PREVIEW',
        ], internal())->assertOk();
    }

    expect(Coupon::first()->usage_count)->toBe(0);
});

it('valida cupom publicamente sem consumir uso', function () {
    Coupon::factory()->create(['code' => 'VALIDO', 'minimum_value' => 50]);

    getJson('/api/coupons/valido/validate?subtotal=100')
        ->assertOk()
        ->assertJson(['code' => 'VALIDO', 'valid' => true]);

    getJson('/api/coupons/valido/validate?subtotal=10')
        ->assertOk()
        ->assertJson(['valid' => false, 'reason' => 'Valor mínimo de R$ 50,00 não atingido']);
});
