<?php

namespace App\Services;

use App\Models\Cupom;
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
class CalculadoraDesconto
{
    /**
     * @param  array<int, array{produto_id: int, preco_unitario: string|float, quantidade: int}>  $itens
     * @param  array<int, int>  $descontoPorProduto  produto_id => percentual já resolvido
     * @return array<string, mixed>
     */
    public function calcular(array $itens, array $descontoPorProduto, ?Cupom $cupom = null): array
    {
        if ($itens === []) {
            throw new InvalidArgumentException('A lista de itens não pode ser vazia.');
        }

        $subtotal = 0;
        $subtotalComDesconto = 0;
        $detalhes = [];

        foreach ($itens as $item) {
            $quantidade = (int) $item['quantidade'];

            if ($quantidade < 1) {
                throw new InvalidArgumentException('Quantidade deve ser maior que zero.');
            }

            $precoUnitario = $this->paraCentavos($item['preco_unitario']);

            if ($precoUnitario < 0) {
                throw new InvalidArgumentException('Preço não pode ser negativo.');
            }

            $pct = (int) ($descontoPorProduto[$item['produto_id']] ?? 0);
            $precoComDesconto = $this->aplicarPercentual($precoUnitario, $pct);

            $subtotal += $precoUnitario * $quantidade;
            $subtotalComDesconto += $precoComDesconto * $quantidade;

            $detalhes[] = [
                'produto_id' => (int) $item['produto_id'],
                'preco_unitario' => $this->paraDecimal($precoUnitario),
                'quantidade' => $quantidade,
                'desconto_pct' => $pct,
                'preco_com_desconto' => $this->paraDecimal($precoComDesconto),
                'subtotal' => $this->paraDecimal($precoComDesconto * $quantidade),
            ];
        }

        $descontoPromocoes = $subtotal - $subtotalComDesconto;

        [$descontoCupom, $resumoCupom] = $this->resolverCupom($cupom, $subtotalComDesconto);

        return [
            'subtotal' => $this->paraDecimal($subtotal),
            'desconto_promocoes' => $this->paraDecimal($descontoPromocoes),
            'desconto_cupom' => $this->paraDecimal($descontoCupom),
            'total' => $this->paraDecimal($subtotalComDesconto - $descontoCupom),
            'itens' => $detalhes,
            'cupom' => $resumoCupom,
        ];
    }

    /**
     * Devolve o motivo da recusa, ou null se o cupom pode ser aplicado.
     * O subtotal recebido já vem descontado pelas promoções.
     */
    public function motivoDeRecusa(Cupom $cupom, int $subtotalCentavos): ?string
    {
        if (! $cupom->ativo) {
            return 'Cupom inativo';
        }

        // A calculadora não toca no banco. Se o cupom tem campanha e ela não veio
        // carregada, falha alto: ignorar em silêncio aceitaria cupom expirado.
        if ($cupom->campanha_id !== null && ! $cupom->relationLoaded('campanha')) {
            throw new InvalidArgumentException(
                'Carregue a relação campanha antes de calcular: Cupom::with("campanha").'
            );
        }

        $campanha = $cupom->relationLoaded('campanha') ? $cupom->getRelation('campanha') : null;

        if ($campanha !== null) {
            if ($campanha->termina_em?->isPast()) {
                return 'Cupom expirado';
            }

            if ($campanha->inicia_em?->isFuture()) {
                return 'Cupom ainda não vigente';
            }

            if (! $campanha->ativo) {
                return 'Cupom inativo';
            }
        }

        if ($cupom->atingiuLimite()) {
            return 'Limite de uso atingido';
        }

        $minimo = $this->paraCentavos($cupom->valor_minimo ?? 0);

        if ($subtotalCentavos < $minimo) {
            return sprintf('Valor mínimo de R$ %s não atingido', number_format($minimo / 100, 2, ',', '.'));
        }

        return null;
    }

    /**
     * @return array{0: int, 1: array<string, mixed>|null}
     */
    public function resolverCupom(?Cupom $cupom, int $subtotalCentavos): array
    {
        if ($cupom === null) {
            return [0, null];
        }

        $base = [
            'codigo' => $cupom->codigo,
            'tipo' => $cupom->tipo,
            'valor' => $this->paraDecimal($this->paraCentavos($cupom->valor)),
        ];

        if ($motivo = $this->motivoDeRecusa($cupom, $subtotalCentavos)) {
            return [0, $base + ['aplicado' => false, 'motivo' => $motivo]];
        }

        $desconto = $cupom->tipo === 'percentual'
            ? $subtotalCentavos - $this->aplicarPercentual($subtotalCentavos, (int) $cupom->valor)
            : $this->paraCentavos($cupom->valor);

        // Desconto maior que o subtotal zera o total em vez de deixá-lo negativo.
        $desconto = min($desconto, $subtotalCentavos);

        return [$desconto, $base + ['aplicado' => true]];
    }

    private function aplicarPercentual(int $centavos, int $percentual): int
    {
        if ($percentual <= 0) {
            return $centavos;
        }

        return (int) round($centavos * (100 - min($percentual, 100)) / 100);
    }

    private function paraCentavos(string|float|int $valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    public function paraDecimal(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }
}
