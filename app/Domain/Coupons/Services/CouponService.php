<?php

namespace App\Domain\Coupons\Services;

use App\Domain\Coupons\Entities\Coupon;

class CouponService
{
    /**
     * Busca pelo código já normalizado, com a campanha carregada — a
     * DiscountCalculator recusa cupom cuja campanha não veio junto.
     */
    public function find(string $code): ?Coupon
    {
        return Coupon::query()
            ->with('campaign')
            ->byCode($code)
            ->first();
    }

    /**
     * Consome um uso. Devolve false se o limite já estava esgotado.
     *
     * A trava está no próprio WHERE: se duas requisições concorrerem pelo último
     * uso, só uma afeta linha. Resolve a corrida sem SELECT ... FOR UPDATE e sem
     * lock de aplicação.
     */
    public function consume(Coupon $coupon): bool
    {
        $affected = Coupon::query()
            ->whereKey($coupon->getKey())
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->increment('usage_count');

        return $affected > 0;
    }
}
