<?php

use App\Domain\Campaigns\Entities\Campaign;
use App\Domain\Coupons\Entities\Coupon;
use App\Domain\Coupons\Services\CouponService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

/**
 * Roda contra MySQL: o que se testa aqui é constraint e concorrência, e é
 * exatamente isso que o SQLite em memória não reproduz fielmente.
 */
beforeEach(function () {
    config(['servico.jwt_secret' => str_repeat('a', 40)]);
});

function tokenAdmin(bool $isAdmin = true): string
{
    return JWT::encode(
        ['id' => 1, 'email' => 'admin@ganjj.com', 'isAdmin' => $isAdmin, 'exp' => time() + 3600],
        str_repeat('a', 40),
        'HS256'
    );
}

function asAdmin(): array
{
    return ['Authorization' => 'Bearer '.tokenAdmin()];
}

// R11 — normalização de código
it('grava o código do cupom em maiúsculas', function () {
    postJson('/api/coupons', [
        'code' => 'inverno20',
        'type' => 'percentage',
        'value' => 20,
    ], asAdmin())->assertCreated();

    expect(Coupon::first()->code)->toBe('INVERNO20');
});

// R12 — unicidade independente de caixa, contra a constraint UNIQUE de verdade
it('recusa cupom cujo código só difere na caixa', function () {
    Coupon::factory()->create(['code' => 'PROMO10']);

    postJson('/api/coupons', [
        'code' => 'promo10',
        'type' => 'percentage',
        'value' => 10,
    ], asAdmin())
        ->assertStatus(409)
        ->assertJson(['error' => 'Código de cupom já existe']);

    expect(Coupon::count())->toBe(1);
});

it('recusa percentual acima de cem', function () {
    postJson('/api/coupons', [
        'code' => 'ABSURDO',
        'type' => 'percentage',
        'value' => 150,
    ], asAdmin())->assertStatus(422);
});

it('aceita valor acima de cem quando o tipo é fixo', function () {
    postJson('/api/coupons', [
        'code' => 'CEMREAIS',
        'type' => 'fixed',
        'value' => 150,
    ], asAdmin())->assertCreated();
});

// R13 — a corrida no último uso
it('não deixa o consumo concorrente ultrapassar o limite', function () {
    $coupon = Coupon::factory()->create(['usage_limit' => 1, 'usage_count' => 0]);
    $service = app(CouponService::class);

    $first = $service->consume($coupon);
    $second = $service->consume($coupon);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($coupon->refresh()->usage_count)->toBe(1);
});

it('consome sem limite quando usage_limit é nulo', function () {
    $coupon = Coupon::factory()->create(['usage_limit' => null]);
    $service = app(CouponService::class);

    expect($service->consume($coupon))->toBeTrue()
        ->and($service->consume($coupon))->toBeTrue()
        ->and($coupon->refresh()->usage_count)->toBe(2);
});

it('carrega a campanha junto do cupom', function () {
    $campaign = Campaign::factory()->ended()->create();
    Coupon::factory()->create(['code' => 'COMCAMP', 'campaign_id' => $campaign->id]);

    $coupon = app(CouponService::class)->find('comcamp');

    // A DiscountCalculator recusa cupom cuja campanha não veio carregada.
    expect($coupon->relationLoaded('campaign'))->toBeTrue()
        ->and($coupon->campaign->id)->toBe($campaign->id);
});
