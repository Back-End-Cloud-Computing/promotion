<?php

namespace App\Http\Controllers\Api;

use App\Domain\Promotions\Entities\Promotion;
use App\Http\Controllers\Controller;
use App\Models\Campanha;
use App\Models\Cupom;
use App\Services\CalculadoraDesconto;
use App\Services\CupomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Rotas /internal consumidas por Carrinho e Pedido. Contrato em
 * docs/contrato-api.md.
 */
class DescontoController extends Controller
{
    public function __construct(
        private readonly CalculadoraDesconto $calculadora,
        private readonly CupomService $cupons,
    ) {}

    /**
     * Calcula o desconto de uma lista de itens. Idempotente: não consome uso de
     * cupom, então pode ser chamado a cada mudança do carrinho.
     */
    public function calcular(Request $request): JsonResponse
    {
        $validador = Validator::make($request->all(), [
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['required', 'integer', 'min:1'],
            'itens.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'itens.*.quantidade' => ['required', 'integer', 'min:1'],
            'cupom' => ['nullable', 'string', 'max:32'],
        ]);

        if ($validador->fails()) {
            return response()->json(['error' => $validador->errors()->first()], 422);
        }

        $itens = $request->input('itens');
        $codigo = $request->input('cupom');

        $resultado = $this->calculadora->calcular(
            $itens,
            $this->descontosPara(array_column($itens, 'produto_id')),
            $codigo ? $this->cupons->buscar($codigo) : null,
        );

        // Cupom informado que não existe não é erro de requisição: o usuário
        // digitou um código errado, e o carrinho ainda precisa do total.
        if ($codigo && $resultado['cupom'] === null) {
            $resultado['cupom'] = [
                'codigo' => mb_strtoupper(trim($codigo)),
                'aplicado' => false,
                'motivo' => 'Cupom não encontrado',
            ];
        }

        return response()->json($resultado);
    }

    /**
     * Consome um uso do cupom. Chamado pelo Pedido no fechamento — nunca pelo
     * Carrinho, senão cada preview queimaria um uso.
     */
    public function consumir(string $codigo): JsonResponse
    {
        $cupom = $this->cupons->buscar($codigo);

        if ($cupom === null) {
            return response()->json(['error' => 'Cupom não encontrado'], 404);
        }

        if (! $this->cupons->consumir($cupom)) {
            return response()->json(['error' => 'Limite de uso atingido'], 409);
        }

        return response()->json([
            'codigo' => $cupom->codigo,
            'usos' => $cupom->refresh()->usos,
            'limite_uso' => $cupom->limite_uso,
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
     * @param  array<int, int>  $produtoIds
     * @return array<int, int>
     */
    private function descontosPara(array $produtoIds): array
    {
        return Promotion::query()
            ->valid()
            ->whereIn('product_id', $produtoIds)
            ->pluck('discount_percentage', 'product_id')
            ->map(fn ($pct) => (int) $pct)
            ->all();
    }

    /**
     * Validação pública de cupom, sem consumir uso.
     */
    public function validar(Request $request, string $codigo): JsonResponse
    {
        $subtotal = (float) $request->query('subtotal', '0');
        $cupom = $this->cupons->buscar($codigo);

        if ($cupom === null) {
            return response()->json([
                'codigo' => mb_strtoupper(trim($codigo)),
                'valido' => false,
                'motivo' => 'Cupom não encontrado',
            ]);
        }

        [$descontoCentavos, $resumo] = $this->calculadora->resolverCupom($cupom, (int) round($subtotal * 100));

        if (! $resumo['aplicado']) {
            return response()->json([
                'codigo' => $cupom->codigo,
                'valido' => false,
                'motivo' => $resumo['motivo'],
            ]);
        }

        return response()->json([
            'codigo' => $resumo['codigo'],
            'valido' => true,
            'tipo' => $resumo['tipo'],
            'valor' => $resumo['valor'],
            'desconto' => $this->calculadora->paraDecimal($descontoCentavos),
        ]);
    }
}
