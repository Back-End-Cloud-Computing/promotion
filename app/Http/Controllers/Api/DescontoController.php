<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campanha;
use App\Models\Cupom;
use App\Models\Promocao;
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
     * Resolve o percentual aplicável a cada produto: o maior entre a promoção
     * individual e a campanha vigente da categoria.
     *
     * @param  array<int, int>  $produtoIds
     * @return array<int, int>
     */
    private function descontosPara(array $produtoIds): array
    {
        $promocoes = Promocao::query()
            ->vigente()
            ->whereIn('produto_id', $produtoIds)
            ->get();

        $descontoPorCategoria = $this->descontoPorCategoria();

        $resultado = [];

        foreach ($promocoes as $promocao) {
            $resultado[$promocao->produto_id] = CalculadoraDesconto::maiorDesconto(
                $promocao->desconto_pct,
                $descontoPorCategoria[$promocao->categoria] ?? null,
            );
        }

        return $resultado;
    }

    /**
     * Maior desconto vigente por categoria, olhando as promoções que pertencem a
     * uma campanha ativa.
     *
     * @return array<string, int>
     */
    private function descontoPorCategoria(): array
    {
        return Promocao::query()
            ->where('ativo', true)
            ->whereNotNull('campanha_id')
            ->whereHas('campanha', fn ($q) => Campanha::aplicarVigencia($q))
            ->get()
            ->groupBy('categoria')
            ->map(fn ($grupo) => (int) $grupo->max('desconto_pct'))
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

        $motivo = $this->calculadora->motivoDeRecusa($cupom, (int) round($subtotal * 100));

        return response()->json(array_filter([
            'codigo' => $cupom->codigo,
            'valido' => $motivo === null,
            'tipo' => $cupom->tipo,
            'valor' => $cupom->valor,
            'motivo' => $motivo,
        ], fn ($v) => $v !== null));
    }
}
