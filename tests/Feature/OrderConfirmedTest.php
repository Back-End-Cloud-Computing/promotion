<?php

use App\Console\Commands\ConsumeOrderConfirmed;
use App\Domain\Coupons\Entities\Coupon;
use App\Domain\Coupons\Entities\ProcessedOrder;
use App\Domain\Coupons\Exceptions\InvalidOrderConfirmedException;
use App\Domain\Coupons\Services\OrderConfirmedHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;
use PhpAmqpLib\Message\AMQPMessage;

uses(RefreshDatabase::class);

function orderConfirmed(array $overrides = []): array
{
    return array_merge([
        'schema_version' => 1,
        'event_id' => 'e82a1f56-f69f-4dc4-9f10-3a05b3e90fac',
        'event_type' => 'OrderConfirmed',
        'occurred_at' => '2026-10-25T12:00:00Z',
        'order_id' => 'adc8aacb-51a2-40d1-a8c0-e7ef5464f8aa',
        'coupon_code' => 'INVERNO20',
        'items' => [['product_id' => '6ad83139dbb96e49bc0aef29', 'quantity' => 2]],
    ], $overrides);
}

function handleOrder(array $payload): string
{
    return app(OrderConfirmedHandler::class)->handle($payload);
}

it('(a) consome um uso do cupom do pedido', function () {
    $coupon = Coupon::factory()->create(['code' => 'INVERNO20', 'usage_count' => 0]);

    expect(handleOrder(orderConfirmed()))->toBe(OrderConfirmedHandler::CONSUMED)
        ->and($coupon->refresh()->usage_count)->toBe(1);
});

it('(b) não consome de novo quando o mesmo pedido chega duas vezes', function () {
    $coupon = Coupon::factory()->create(['code' => 'INVERNO20', 'usage_count' => 0]);

    handleOrder(orderConfirmed());
    $outro = orderConfirmed(['event_id' => '0b0b0b0b-0000-4000-8000-000000000000']);

    expect(handleOrder($outro))->toBe(OrderConfirmedHandler::DUPLICATE)
        ->and($coupon->refresh()->usage_count)->toBe(1);
});

it('(c) trata pedido sem cupom como no-op', function () {
    $coupon = Coupon::factory()->create(['code' => 'INVERNO20', 'usage_count' => 0]);

    expect(handleOrder(orderConfirmed(['coupon_code' => null])))->toBe(OrderConfirmedHandler::NO_COUPON)
        ->and($coupon->refresh()->usage_count)->toBe(0);
});

it('(d) rejeita versão ou payload inválido sem consumir', function (array $overrides) {
    $coupon = Coupon::factory()->create(['code' => 'INVERNO20', 'usage_count' => 0]);

    expect(fn () => handleOrder(orderConfirmed($overrides)))->toThrow(InvalidOrderConfirmedException::class)
        ->and($coupon->refresh()->usage_count)->toBe(0);
})->with([
    'versão 2' => [['schema_version' => 2]],
    'sem order_id' => [['order_id' => null]],
    'order_id não UUID' => [['order_id' => '123']],
    'evento errado' => [['event_type' => 'OrderCreated']],
]);

it('(e) confirma sem consumir quando o cupom não existe ou está esgotado', function () {
    $esgotado = Coupon::factory()->create(['code' => 'ESGOTADO', 'usage_limit' => 1, 'usage_count' => 1]);

    expect(handleOrder(orderConfirmed(['coupon_code' => 'NAOEXISTE'])))->toBe(OrderConfirmedHandler::COUPON_NOT_FOUND);

    $outroPedido = ['order_id' => '1c1c1c1c-0000-4000-8000-000000000000', 'coupon_code' => 'ESGOTADO'];
    expect(handleOrder(orderConfirmed($outroPedido)))->toBe(OrderConfirmedHandler::LIMIT_REACHED)
        ->and($esgotado->refresh()->usage_count)->toBe(1);
});

it('(f) não grava nada quando o banco falha no meio', function () {
    $coupon = Coupon::factory()->create(['code' => 'INVERNO20', 'usage_count' => 0]);
    // Falha depois do incremento do cupom, na mesma transação.
    ProcessedOrder::creating(fn () => throw new RuntimeException('banco caiu'));

    expect(fn () => handleOrder(orderConfirmed()))->toThrow(RuntimeException::class, 'banco caiu')
        ->and($coupon->refresh()->usage_count)->toBe(0)
        ->and(ProcessedOrder::count())->toBe(0);
});

function amqpMessage(string $body): AMQPMessage
{
    $message = Mockery::mock(AMQPMessage::class);
    $message->shouldReceive('getBody')->andReturn($body);

    return $message;
}

it('confirma a mensagem depois de processar', function () {
    Coupon::factory()->create(['code' => 'INVERNO20', 'usage_count' => 0]);
    $message = amqpMessage(json_encode(orderConfirmed()));
    $message->shouldReceive('ack')->once();

    app(ConsumeOrderConfirmed::class)->process($message);

    expect(ProcessedOrder::count())->toBe(1);
});

it('manda payload inválido para a DLQ sem reentrega', function (string $body) {
    $message = amqpMessage($body);
    $message->shouldReceive('reject')->once()->with(false);

    app(ConsumeOrderConfirmed::class)->process($message);
})->with([
    'JSON quebrado' => ['{"schema_version":'],
    'não é objeto' => ['"texto"'],
    'fora do contrato' => [fn () => json_encode(orderConfirmed(['schema_version' => 2]))],
]);

it('devolve para a fila quando o banco falha', function () {
    Sleep::fake();
    Coupon::factory()->create(['code' => 'INVERNO20', 'usage_count' => 0]);
    ProcessedOrder::creating(fn () => throw new RuntimeException('banco caiu'));
    $message = amqpMessage(json_encode(orderConfirmed()));
    $message->shouldReceive('nack')->once()->with(true);

    app(ConsumeOrderConfirmed::class)->process($message);
});
