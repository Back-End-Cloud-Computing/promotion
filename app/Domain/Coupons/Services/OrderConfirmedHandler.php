<?php

namespace App\Domain\Coupons\Services;

use App\Domain\Coupons\Entities\ProcessedOrder;
use App\Domain\Coupons\Exceptions\InvalidOrderConfirmedException;
use App\Support\Metrics;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Processa o evento OrderConfirmed do Order (contrato em docs/contrato-api.md).
 *
 * O incremento do cupom e o registro do `order_id` acontecem na mesma
 * transação: se o registro falhar, o incremento volta junto; se o pedido já
 * foi processado, a unicidade do `order_id` desfaz o incremento repetido.
 */
class OrderConfirmedHandler
{
    public const CONSUMED = 'consumed';

    public const DUPLICATE = 'duplicate';

    public const NO_COUPON = 'no_coupon';

    public const COUPON_NOT_FOUND = 'coupon_not_found';

    public const LIMIT_REACHED = 'limit_reached';

    public function __construct(private readonly CouponService $coupons) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidOrderConfirmedException payload fora do contrato
     */
    public function handle(array $payload): string
    {
        $this->validate($payload);

        $code = $payload['coupon_code'];

        try {
            return DB::transaction(function () use ($payload, $code) {
                $outcome = $this->consume($code);

                ProcessedOrder::create([
                    'order_id' => $payload['order_id'],
                    'coupon_code' => $code,
                    'outcome' => $outcome,
                ]);

                return $outcome;
            });
        } catch (UniqueConstraintViolationException) {
            return self::DUPLICATE;
        }
    }

    private function consume(?string $code): string
    {
        if ($code === null) {
            return self::NO_COUPON;
        }

        $coupon = $this->coupons->find($code);

        if ($coupon === null) {
            Metrics::couponRefused('Cupom não encontrado');

            return self::COUPON_NOT_FOUND;
        }

        if (! $this->coupons->consume($coupon)) {
            Metrics::couponRefused('Limite de uso atingido');

            return self::LIMIT_REACHED;
        }

        return self::CONSUMED;
    }

    /** @param  array<string, mixed>  $payload */
    private function validate(array $payload): void
    {
        $validator = Validator::make($payload, [
            'schema_version' => ['required', 'integer', 'in:1'],
            'event_type' => ['required', 'in:OrderConfirmed'],
            'order_id' => ['required', 'uuid'],
            'coupon_code' => ['present', 'nullable', 'string', 'max:32'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            throw new InvalidOrderConfirmedException($validator->errors()->toJson());
        }
    }
}
