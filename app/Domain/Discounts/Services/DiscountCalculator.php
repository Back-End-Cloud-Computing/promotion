<?php

namespace App\Domain\Discounts\Services;

use App\Domain\Coupons\Entities\Coupon;
use App\Domain\Coupons\Enums\CouponType;
use InvalidArgumentException;

/**
 * Regra de dinheiro do serviço. Pura: sem banco, sem HTTP — quem busca promoções
 * e cupom é o Controller, que passa tudo pronto.
 *
 * Todo o cálculo roda em centavos inteiros. Percentual é a única operação com
 * ponto flutuante e é arredondada na hora; soma e multiplicação ficam em int,
 * onde não existe erro de representação.
 *
 * Regras e casos de teste em docs/regras-de-negocio.md.
 */
class DiscountCalculator
{
    /**
     * @param  array<int, array{product_id: int, unit_price: string|float, quantity: int}>  $items
     * @param  array<int, int>  $discountByProduct  product_id => percentual já resolvido
     * @return array<string, mixed>
     */
    public function calculate(array $items, array $discountByProduct, ?Coupon $coupon = null): array
    {
        if ($items === []) {
            throw new InvalidArgumentException('A lista de itens não pode ser vazia.');
        }

        $subtotal = 0;
        $subtotalWithDiscount = 0;
        $details = [];

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];

            if ($quantity < 1) {
                throw new InvalidArgumentException('Quantidade deve ser maior que zero.');
            }

            $unitPrice = $this->toCents($item['unit_price']);

            if ($unitPrice < 0) {
                throw new InvalidArgumentException('Preço não pode ser negativo.');
            }

            $pct = (int) ($discountByProduct[$item['product_id']] ?? 0);
            $discountedPrice = $this->applyPercentage($unitPrice, $pct);

            $subtotal += $unitPrice * $quantity;
            $subtotalWithDiscount += $discountedPrice * $quantity;

            $details[] = [
                'product_id' => (int) $item['product_id'],
                'unit_price' => $this->toDecimal($unitPrice),
                'quantity' => $quantity,
                'discount_percentage' => $pct,
                'discounted_price' => $this->toDecimal($discountedPrice),
                'subtotal' => $this->toDecimal($discountedPrice * $quantity),
            ];
        }

        $promotionsDiscount = $subtotal - $subtotalWithDiscount;

        [$couponDiscount, $couponSummary] = $this->resolveCoupon($coupon, $subtotalWithDiscount);

        return [
            'subtotal' => $this->toDecimal($subtotal),
            'promotions_discount' => $this->toDecimal($promotionsDiscount),
            'coupon_discount' => $this->toDecimal($couponDiscount),
            'total' => $this->toDecimal($subtotalWithDiscount - $couponDiscount),
            'items' => $details,
            'coupon' => $couponSummary,
        ];
    }

    /**
     * Devolve o motivo da recusa, ou null se o cupom pode ser aplicado.
     * O subtotal recebido já vem descontado pelas promoções.
     */
    public function rejectionReason(Coupon $coupon, int $subtotalCents): ?string
    {
        if (! $coupon->active) {
            return 'Cupom inativo';
        }

        // A calculadora não toca no banco. Se o cupom tem campanha e ela não veio
        // carregada, falha alto: ignorar em silêncio aceitaria cupom expirado.
        if ($coupon->campaign_id !== null && ! $coupon->relationLoaded('campaign')) {
            throw new InvalidArgumentException(
                'Carregue a relação campaign antes de calcular: Coupon::with("campaign").'
            );
        }

        $campaign = $coupon->relationLoaded('campaign') ? $coupon->getRelation('campaign') : null;

        if ($campaign !== null) {
            if ($campaign->ends_at?->isPast()) {
                return 'Cupom expirado';
            }

            if ($campaign->starts_at?->isFuture()) {
                return 'Cupom ainda não vigente';
            }

            if (! $campaign->active) {
                return 'Cupom inativo';
            }
        }

        if ($coupon->hasReachedLimit()) {
            return 'Limite de uso atingido';
        }

        $minimum = $this->toCents($coupon->minimum_value ?? 0);

        if ($subtotalCents < $minimum) {
            return sprintf('Valor mínimo de R$ %s não atingido', number_format($minimum / 100, 2, ',', '.'));
        }

        return null;
    }

    /**
     * @return array{0: int, 1: array<string, mixed>|null}
     */
    public function resolveCoupon(?Coupon $coupon, int $subtotalCents): array
    {
        if ($coupon === null) {
            return [0, null];
        }

        $base = [
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => $this->toDecimal($this->toCents($coupon->value)),
        ];

        if ($reason = $this->rejectionReason($coupon, $subtotalCents)) {
            return [0, $base + ['applied' => false, 'reason' => $reason]];
        }

        $discount = $coupon->type === CouponType::Percentage
            ? $subtotalCents - $this->applyPercentage($subtotalCents, (int) $coupon->value)
            : $this->toCents($coupon->value);

        // Desconto maior que o subtotal zera o total em vez de deixá-lo negativo.
        $discount = min($discount, $subtotalCents);

        return [$discount, $base + ['applied' => true]];
    }

    private function applyPercentage(int $cents, int $percentage): int
    {
        if ($percentage <= 0) {
            return $cents;
        }

        return (int) round($cents * (100 - min($percentage, 100)) / 100);
    }

    private function toCents(string|float|int $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    public function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
