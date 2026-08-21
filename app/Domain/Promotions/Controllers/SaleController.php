<?php

namespace App\Domain\Promotions\Controllers;

use App\Domain\Promotions\Entities\Promotion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mantém o endpoint `GET /api/sale` do projeto de referência da equipe, para
 * que quem já consome não precise mudar.
 *
 * Diferença inevitável: lá o endpoint fazia JOIN com `produto` e devolvia nome,
 * preço e preco_sale. Aqui o catálogo vive em outro serviço e outro banco, então
 * a resposta traz só os dados da promoção. Quem precisa do preço final chama
 * POST /internal/discounts/calculate com os preços em mãos.
 */
class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('categoria');

        $promotions = Promotion::query()
            ->valid()
            ->when(
                $category && $category !== 'Todos',
                fn ($q) => $q->where('category', $category)
            )
            ->orderByDesc('discount_percentage')
            ->get()
            ->map(fn (Promotion $p) => [
                'produto_id' => $p->product_id,
                'desconto_pct' => $p->discount_percentage,
                'categoria' => $p->category,
                'ativo' => $p->active,
            ]);

        return response()->json($promotions);
    }
}
