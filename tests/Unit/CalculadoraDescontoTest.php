<?php

use App\Models\Campanha;
use App\Models\Cupom;
use App\Services\CalculadoraDesconto;

/**
 * Regras em docs/regras-de-negocio.md. Testes puros: sem banco, sem HTTP.
 */
function item(int $produtoId, string $preco, int $quantidade = 1): array
{
    return [
        'produto_id' => $produtoId,
        'preco_unitario' => $preco,
        'quantidade' => $quantidade,
    ];
}

function cupom(array $atributos = []): Cupom
{
    return new Cupom(array_merge([
        'codigo' => 'TESTE10',
        'tipo' => 'percentual',
        'valor' => 10,
        'valor_minimo' => 0,
        'limite_uso' => null,
        'ativo' => true,
    ], $atributos));
}

function comCampanha(Cupom $cupom, string $inicia, string $termina): Cupom
{
    $cupom->setRelation('campanha', new Campanha([
        'nome' => 'Campanha',
        'inicia_em' => $inicia,
        'termina_em' => $termina,
        'ativo' => true,
    ]));

    return $cupom;
}

// R1 — desconto percentual
it('aplica desconto percentual arredondado em duas casas', function () {
    $r = (new CalculadoraDesconto)->calcular([item(1, '49.90', 2)], [1 => 30]);

    // 49,90 x 0,7 = 34,93 — mesmo resultado do ROUND() do repo base
    expect($r['itens'][0]['preco_com_desconto'])->toBe('34.93')
        ->and($r['itens'][0]['subtotal'])->toBe('69.86')
        ->and($r['subtotal'])->toBe('99.80')
        ->and($r['desconto_promocoes'])->toBe('29.94');
});

// R2 — desconto fixo
it('aplica cupom de valor fixo sobre o subtotal', function () {
    $r = (new CalculadoraDesconto)->calcular(
        [item(1, '100.00')],
        [],
        cupom(['tipo' => 'fixo', 'valor' => 15])
    );

    expect($r['desconto_cupom'])->toBe('15.00')
        ->and($r['total'])->toBe('85.00');
});

// R3 — arredondamento por item, sem resto de ponto flutuante
it('arredonda por item antes de somar', function () {
    $r = (new CalculadoraDesconto)->calcular([item(1, '19.99', 3)], [1 => 15]);

    // 19,99 x 0,85 = 16,9915 -> 16,99 por item; 16,99 x 3 = 50,97
    expect($r['itens'][0]['preco_com_desconto'])->toBe('16.99')
        ->and($r['total'])->toBe('50.97');
});

// R4 — total nunca negativo
it('nunca devolve total negativo', function () {
    $r = (new CalculadoraDesconto)->calcular(
        [item(1, '10.00')],
        [],
        cupom(['tipo' => 'fixo', 'valor' => 50])
    );

    expect($r['total'])->toBe('0.00')
        ->and($r['desconto_cupom'])->toBe('10.00');
});

// R5 — cupom expirado
it('recusa cupom de campanha encerrada', function () {
    $c = comCampanha(cupom(), now()->subDays(30), now()->subDay());
    $r = (new CalculadoraDesconto)->calcular([item(1, '100.00')], [], $c);

    expect($r['cupom']['aplicado'])->toBeFalse()
        ->and($r['cupom']['motivo'])->toBe('Cupom expirado')
        ->and($r['total'])->toBe('100.00');
});

// R6 — cupom ainda não vigente
it('recusa cupom de campanha que ainda não começou', function () {
    $c = comCampanha(cupom(), now()->addDay(), now()->addDays(30));
    $r = (new CalculadoraDesconto)->calcular([item(1, '100.00')], [], $c);

    expect($r['cupom']['motivo'])->toBe('Cupom ainda não vigente');
});

// R7 — limite de uso
it('recusa cupom com limite de uso atingido', function () {
    $c = cupom(['limite_uso' => 5]);
    $c->usos = 5;

    $r = (new CalculadoraDesconto)->calcular([item(1, '100.00')], [], $c);

    expect($r['cupom']['motivo'])->toBe('Limite de uso atingido');
});

it('aceita cupom sem limite de uso definido', function () {
    $c = cupom(['limite_uso' => null]);
    $c->usos = 9999;

    $r = (new CalculadoraDesconto)->calcular([item(1, '100.00')], [], $c);

    expect($r['cupom']['aplicado'])->toBeTrue();
});

// R8 — valor mínimo, comparado contra o subtotal já descontado
it('recusa cupom quando o subtotal não atinge o valor mínimo', function () {
    $r = (new CalculadoraDesconto)->calcular(
        [item(1, '100.00')],
        [1 => 50],
        cupom(['valor_minimo' => 80])
    );

    // subtotal com promoção = 50,00, abaixo do mínimo de 80,00
    expect($r['cupom']['motivo'])->toBe('Valor mínimo de R$ 80,00 não atingido');
});

// R9 — inativo
it('recusa cupom inativo', function () {
    $r = (new CalculadoraDesconto)->calcular(
        [item(1, '100.00')],
        [],
        cupom(['ativo' => false])
    );

    expect($r['cupom']['motivo'])->toBe('Cupom inativo');
});

// R10 — sem cupom informado
it('calcula normalmente quando nenhum cupom é informado', function () {
    $r = (new CalculadoraDesconto)->calcular([item(1, '100.00')], []);

    expect($r['cupom'])->toBeNull()
        ->and($r['desconto_cupom'])->toBe('0.00')
        ->and($r['total'])->toBe('100.00');
});

// R14 — ordem: promoção no item, cupom no subtotal já descontado
it('aplica a promoção no item e o cupom sobre o subtotal descontado', function () {
    $r = (new CalculadoraDesconto)->calcular(
        [item(1, '100.00'), item(2, '50.00')],
        [1 => 50],
        cupom(['tipo' => 'fixo', 'valor' => 25])
    );

    // itens: 50,00 + 50,00 = 100,00; cupom fixo de 25,00 -> 75,00.
    // Se o cupom incidisse antes da promoção o total seria 62,50.
    expect($r['subtotal'])->toBe('150.00')
        ->and($r['desconto_promocoes'])->toBe('50.00')
        ->and($r['desconto_cupom'])->toBe('25.00')
        ->and($r['total'])->toBe('75.00');
});

// R15 — produto sem promoção
it('mantém preço cheio para produto sem promoção', function () {
    $r = (new CalculadoraDesconto)->calcular([item(7, '120.00')], []);

    expect($r['itens'][0]['desconto_pct'])->toBe(0)
        ->and($r['itens'][0]['preco_com_desconto'])->toBe('120.00');
});

// R16 — promoção individual e campanha na mesma categoria
it('usa o maior desconto entre promoção e campanha, nunca a soma', function () {
    expect(CalculadoraDesconto::maiorDesconto(20, 30))->toBe(30)
        ->and(CalculadoraDesconto::maiorDesconto(40, 30))->toBe(40)
        ->and(CalculadoraDesconto::maiorDesconto(20, null))->toBe(20)
        ->and(CalculadoraDesconto::maiorDesconto(null, null))->toBe(0);
});

it('rejeita item com quantidade inválida', function () {
    (new CalculadoraDesconto)->calcular([item(1, '10.00', 0)], []);
})->throws(InvalidArgumentException::class);
