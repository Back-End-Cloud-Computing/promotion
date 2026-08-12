<?php

use App\Models\Campanha;
use App\Models\Cupom;
use App\Models\Promocao;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['servico.internal_secret' => str_repeat('c', 40)]);
});

function interno(): array
{
    return ['x-internal-secret' => str_repeat('c', 40)];
}

it('calcula o desconto de um carrinho com promoção e cupom', function () {
    Promocao::factory()->create(['produto_id' => 1, 'desconto_pct' => 30]);
    Cupom::factory()->create(['codigo' => 'INVERNO20', 'tipo' => 'percentual', 'valor' => 20]);

    $r = postJson('/internal/descontos/calcular', [
        'itens' => [['produto_id' => 1, 'preco_unitario' => 49.90, 'quantidade' => 2]],
        'cupom' => 'INVERNO20',
    ], interno())->assertOk();

    // 49,90 x 0,7 = 34,93 por item -> 69,86; menos 20% -> 55,89
    $r->assertJson([
        'subtotal' => '99.80',
        'desconto_promocoes' => '29.94',
        'desconto_cupom' => '13.97',
        'total' => '55.89',
    ]);
});

it('devolve 200 com o motivo quando o cupom não existe', function () {
    Promocao::factory()->create(['produto_id' => 1, 'desconto_pct' => 10]);

    // O carrinho precisa do total mesmo com código inválido: quem errou foi o
    // usuário digitando, não o serviço chamador.
    postJson('/internal/descontos/calcular', [
        'itens' => [['produto_id' => 1, 'preco_unitario' => 100, 'quantidade' => 1]],
        'cupom' => 'NAOEXISTE',
    ], interno())
        ->assertOk()
        ->assertJson([
            'total' => '90.00',
            'cupom' => ['aplicado' => false, 'motivo' => 'Cupom não encontrado'],
        ]);
});

it('recusa requisição sem itens', function () {
    postJson('/internal/descontos/calcular', ['itens' => []], interno())
        ->assertStatus(422)
        ->assertJsonStructure(['error']);
});

it('recusa item com quantidade zero', function () {
    postJson('/internal/descontos/calcular', [
        'itens' => [['produto_id' => 1, 'preco_unitario' => 10, 'quantidade' => 0]],
    ], interno())->assertStatus(422);
});

it('não deixa o desconto de um produto vazar para outro da mesma categoria', function () {
    $campanha = Campanha::factory()->create();

    // Mesma categoria e mesma campanha, descontos diferentes.
    Promocao::factory()->create([
        'produto_id' => 99, 'desconto_pct' => 40,
        'categoria' => 'Inverno', 'campanha_id' => $campanha->id,
    ]);
    Promocao::factory()->create([
        'produto_id' => 1, 'desconto_pct' => 10,
        'categoria' => 'Inverno', 'campanha_id' => $campanha->id,
    ]);

    postJson('/internal/descontos/calcular', [
        'itens' => [['produto_id' => 1, 'preco_unitario' => 100, 'quantidade' => 1]],
    ], interno())
        ->assertOk()
        // 10%, o desconto do próprio produto — não os 40% do vizinho.
        ->assertJson(['total' => '90.00']);
});

it('ignora promoção de campanha encerrada', function () {
    $campanha = Campanha::factory()->encerrada()->create();
    Promocao::factory()->create([
        'produto_id' => 1, 'desconto_pct' => 50, 'campanha_id' => $campanha->id,
    ]);

    postJson('/internal/descontos/calcular', [
        'itens' => [['produto_id' => 1, 'preco_unitario' => 100, 'quantidade' => 1]],
    ], interno())
        ->assertOk()
        ->assertJson(['total' => '100.00']);
});

it('consome um uso do cupom', function () {
    Cupom::factory()->create(['codigo' => 'USAR', 'limite_uso' => 2]);

    postJson('/internal/cupons/usar/consumir', [], interno())
        ->assertOk()
        ->assertJson(['codigo' => 'USAR', 'usos' => 1, 'limite_uso' => 2]);
});

it('recusa consumo de cupom esgotado', function () {
    Cupom::factory()->esgotado()->create(['codigo' => 'ESGOTADO']);

    postJson('/internal/cupons/ESGOTADO/consumir', [], interno())
        ->assertStatus(409)
        ->assertJson(['error' => 'Limite de uso atingido']);
});

it('não consome uso ao apenas calcular', function () {
    Cupom::factory()->create(['codigo' => 'PREVIEW', 'limite_uso' => 1]);

    // O carrinho recalcula a cada mudança; se isso gastasse uso, um cupom de
    // 500 usos se esgotaria sem nenhuma venda.
    foreach (range(1, 3) as $ignored) {
        postJson('/internal/descontos/calcular', [
            'itens' => [['produto_id' => 1, 'preco_unitario' => 100, 'quantidade' => 1]],
            'cupom' => 'PREVIEW',
        ], interno())->assertOk();
    }

    expect(Cupom::first()->usos)->toBe(0);
});

it('valida cupom publicamente sem consumir uso', function () {
    Cupom::factory()->create(['codigo' => 'VALIDO', 'valor_minimo' => 50]);

    getJson('/api/cupons/valido/validar?subtotal=100')
        ->assertOk()
        ->assertJson(['codigo' => 'VALIDO', 'valido' => true]);

    getJson('/api/cupons/valido/validar?subtotal=10')
        ->assertOk()
        ->assertJson(['valido' => false, 'motivo' => 'Valor mínimo de R$ 50,00 não atingido']);
});
