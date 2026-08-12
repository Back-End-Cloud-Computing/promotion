<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promocao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mantém o endpoint `GET /api/sale` do projeto de referência da equipe, para
 * que quem já consome não precise mudar.
 *
 * Diferença inevitável: lá o endpoint fazia JOIN com `produto` e devolvia nome,
 * preço e preco_sale. Aqui o catálogo vive em outro serviço e outro banco, então
 * a resposta traz só os dados da promoção. Quem precisa do preço final chama
 * POST /internal/descontos/calcular com os preços em mãos.
 */
class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categoria = $request->query('categoria');

        $promocoes = Promocao::query()
            ->vigente()
            ->when(
                $categoria && $categoria !== 'Todos',
                fn ($q) => $q->where('categoria', $categoria)
            )
            ->orderByDesc('desconto_pct')
            ->get()
            ->map(fn (Promocao $p) => [
                'produto_id' => $p->produto_id,
                'desconto_pct' => $p->desconto_pct,
                'categoria' => $p->categoria,
                'ativo' => $p->ativo,
            ]);

        return response()->json($promocoes);
    }
}
