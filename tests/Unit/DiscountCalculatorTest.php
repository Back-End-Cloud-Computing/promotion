<?php

use App\Domain\Campaigns\Entities\Campaign;
use App\Domain\Coupons\Entities\Coupon;
use App\Domain\Discounts\Services\DiscountCalculator;

/**
 * Regras em docs/regras-de-negocio.md. Testes puros: sem banco, sem HTTP.
 */
function item(int $productId, string $price, int $quantity = 1): array
{
    return [
        'product_id' => $productId,
        'unit_price' => $price,
        'quantity' => $quantity,
    ];
}

function coupon(array $attributes = []): Coupon
{
    return new Coupon(array_merge([
        'code' => 'TESTE10',
        'type' => 'percentage',
        'value' => 10,
        'minimum_value' => 0,
        'usage_limit' => null,
        'active' => true,
    ], $attributes));
}

function withCampaign(Coupon $coupon, string $startsAt, string $endsAt): Coupon
{
    $coupon->setRelation('campaign', new Campaign([
        'name' => 'Campaign',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'active' => true,
    ]));

    return $coupon;
}

// R1 — desconto percentual
it('aplica desconto percentual arredondado em duas casas', function () {
    $r = (new DiscountCalculator)->calculate([item(1, '49.90', 2)], [1 => 30]);

    // 49,90 x 0,7 = 34,93 — mesmo resultado do ROUND() do repo base
    expect($r['items'][0]['discounted_price'])->toBe('34.93')
        ->and($r['items'][0]['subtotal'])->toBe('69.86')
        ->and($r['subtotal'])->toBe('99.80')
        ->and($r['promotions_discount'])->toBe('29.94');
});

// R2 — desconto fixo
it('aplica cupom de valor fixo sobre o subtotal', function () {
    $r = (new DiscountCalculator)->calculate(
        [item(1, '100.00')],
        [],
        coupon(['type' => 'fixed', 'value' => 15])
    );

    expect($r['coupon_discount'])->toBe('15.00')
        ->and($r['total'])->toBe('85.00');
});

// R3 — arredondamento por item, sem resto de ponto flutuante
it('arredonda por item antes de somar', function () {
    $r = (new DiscountCalculator)->calculate([item(1, '19.99', 3)], [1 => 15]);

    // 19,99 x 0,85 = 16,9915 -> 16,99 por item; 16,99 x 3 = 50,97
    expect($r['items'][0]['discounted_price'])->toBe('16.99')
        ->and($r['total'])->toBe('50.97');
});

// R4 — total nunca negativo
it('nunca devolve total negativo', function () {
    $r = (new DiscountCalculator)->calculate(
        [item(1, '10.00')],
        [],
        coupon(['type' => 'fixed', 'value' => 50])
    );

    expect($r['total'])->toBe('0.00')
        ->and($r['coupon_discount'])->toBe('10.00');
});

// R5 — cupom expirado
it('recusa cupom de campanha encerrada', function () {
    $c = withCampaign(coupon(), now()->subDays(30), now()->subDay());
    $r = (new DiscountCalculator)->calculate([item(1, '100.00')], [], $c);

    expect($r['coupon']['applied'])->toBeFalse()
        ->and($r['coupon']['reason'])->toBe('Cupom expirado')
        ->and($r['total'])->toBe('100.00');
});

// R6 — cupom ainda não vigente
it('recusa cupom de campanha que ainda não começou', function () {
    $c = withCampaign(coupon(), now()->addDay(), now()->addDays(30));
    $r = (new DiscountCalculator)->calculate([item(1, '100.00')], [], $c);

    expect($r['coupon']['reason'])->toBe('Cupom ainda não vigente');
});

// R7 — limite de uso
it('recusa cupom com limite de uso atingido', function () {
    $c = coupon(['usage_limit' => 5]);
    $c->usage_count = 5;

    $r = (new DiscountCalculator)->calculate([item(1, '100.00')], [], $c);

    expect($r['coupon']['reason'])->toBe('Limite de uso atingido');
});

it('aceita cupom sem limite de uso definido', function () {
    $c = coupon(['usage_limit' => null]);
    $c->usage_count = 9999;

    $r = (new DiscountCalculator)->calculate([item(1, '100.00')], [], $c);

    expect($r['coupon']['applied'])->toBeTrue();
});

// R8 — valor mínimo, comparado contra o subtotal já descontado
it('recusa cupom quando o subtotal não atinge o valor mínimo', function () {
    $r = (new DiscountCalculator)->calculate(
        [item(1, '100.00')],
        [1 => 50],
        coupon(['minimum_value' => 80])
    );

    // subtotal com promoção = 50,00, abaixo do mínimo de 80,00
    expect($r['coupon']['reason'])->toBe('Valor mínimo de R$ 80,00 não atingido');
});

// R9 — inativo
it('recusa cupom inativo', function () {
    $r = (new DiscountCalculator)->calculate(
        [item(1, '100.00')],
        [],
        coupon(['active' => false])
    );

    expect($r['coupon']['reason'])->toBe('Cupom inativo');
});

// R10 — sem cupom informado
it('calcula normalmente quando nenhum cupom é informado', function () {
    $r = (new DiscountCalculator)->calculate([item(1, '100.00')], []);

    expect($r['coupon'])->toBeNull()
        ->and($r['coupon_discount'])->toBe('0.00')
        ->and($r['total'])->toBe('100.00');
});

// R14 — ordem: promoção no item, cupom no subtotal já descontado
it('aplica a promoção no item e o cupom sobre o subtotal descontado', function () {
    $r = (new DiscountCalculator)->calculate(
        [item(1, '100.00'), item(2, '50.00')],
        [1 => 50],
        coupon(['type' => 'fixed', 'value' => 25])
    );

    // itens: 50,00 + 50,00 = 100,00; cupom fixo de 25,00 -> 75,00.
    // Se o cupom incidisse antes da promoção o total seria 62,50.
    expect($r['subtotal'])->toBe('150.00')
        ->and($r['promotions_discount'])->toBe('50.00')
        ->and($r['coupon_discount'])->toBe('25.00')
        ->and($r['total'])->toBe('75.00');
});

// R15 — produto sem promoção
it('mantém preço cheio para produto sem promoção', function () {
    $r = (new DiscountCalculator)->calculate([item(7, '120.00')], []);

    expect($r['items'][0]['discount_percentage'])->toBe(0)
        ->and($r['items'][0]['discounted_price'])->toBe('120.00');
});

it('rejeita item com quantidade inválida', function () {
    (new DiscountCalculator)->calculate([item(1, '10.00', 0)], []);
})->throws(InvalidArgumentException::class);
