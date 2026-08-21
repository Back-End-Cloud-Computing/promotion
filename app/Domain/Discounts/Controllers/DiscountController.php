<?php

namespace App\Domain\Discounts\Controllers;

use App\Domain\Coupons\Services\CouponService;
use App\Domain\Discounts\Services\DiscountCalculator;
use App\Domain\Promotions\Entities\Promotion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Rotas /internal consumidas por Carrinho e Pedido. Contrato em
 * docs/contrato-api.md.
 */
class DiscountController extends Controller
{
    public function __construct(
        private readonly DiscountCalculator $calculator,
        private readonly CouponService $coupons,
    ) {}

    /**
     * Calcula o desconto de uma lista de itens. Idempotente: não consome uso de
     * cupom, então pode ser chamado a cada mudança do carrinho.
     */
    public function calculate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'coupon' => ['nullable', 'string', 'max:32'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $items = $request->input('items');
        $code = $request->input('coupon');

        $result = $this->calculator->calculate(
            $items,
            $this->discountsFor(array_column($items, 'product_id')),
            $code ? $this->coupons->find($code) : null,
        );

        // Cupom informado que não existe não é erro de requisição: o usuário
        // digitou um código errado, e o carrinho ainda precisa do total.
        if ($code && $result['coupon'] === null) {
            $result['coupon'] = [
                'code' => mb_strtoupper(trim($code)),
                'applied' => false,
                'reason' => 'Cupom não encontrado',
            ];
        }

        return response()->json($result);
    }

    /**
     * Consome um uso do cupom. Chamado pelo Pedido no fechamento — nunca pelo
     * Carrinho, senão cada preview queimaria um uso.
     */
    public function consume(string $code): JsonResponse
    {
        $coupon = $this->coupons->find($code);

        if ($coupon === null) {
            return response()->json(['error' => 'Cupom não encontrado'], 404);
        }

        if (! $this->coupons->consume($coupon)) {
            return response()->json(['error' => 'Limite de uso atingido'], 409);
        }

        return response()->json([
            'code' => $coupon->code,
            'usage_count' => $coupon->refresh()->usage_count,
            'usage_limit' => $coupon->usage_limit,
        ]);
    }

    /**
     * Percentual vigente de cada produto.
     *
     * O desconto é sempre o da promoção do próprio produto. Campanha agrupa e
     * define vigência, mas não carrega percentual — então não existe "desconto
     * de categoria" para disputar com o do produto. Derivar um a partir da maior
     * promoção da categoria faria um produto de 30% ser cobrado a 40% só porque
     * outro item da mesma categoria está mais barato.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, int>
     */
    private function discountsFor(array $productIds): array
    {
        return Promotion::query()
            ->valid()
            ->whereIn('product_id', $productIds)
            ->pluck('discount_percentage', 'product_id')
            ->map(fn ($pct) => (int) $pct)
            ->all();
    }

    /**
     * Validação pública de cupom, sem consumir uso.
     */
    public function validate(Request $request, string $code): JsonResponse
    {
        $subtotal = (float) $request->query('subtotal', '0');
        $coupon = $this->coupons->find($code);

        if ($coupon === null) {
            return response()->json([
                'code' => mb_strtoupper(trim($code)),
                'valid' => false,
                'reason' => 'Cupom não encontrado',
            ]);
        }

        [$discountCents, $summary] = $this->calculator->resolveCoupon($coupon, (int) round($subtotal * 100));

        if (! $summary['applied']) {
            return response()->json([
                'code' => $coupon->code,
                'valid' => false,
                'reason' => $summary['reason'],
            ]);
        }

        return response()->json([
            'code' => $summary['code'],
            'valid' => true,
            'type' => $summary['type'],
            'value' => $summary['value'],
            'discount' => $this->calculator->toDecimal($discountCents),
        ]);
    }
}
