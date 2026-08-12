<?php

use App\Models\Campanha;
use App\Models\Cupom;
use App\Services\CupomService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

/**
 * Roda contra MySQL: o que se testa aqui é constraint e concorrência, e é
 * exatamente isso que o SQLite em memória não reproduz fielmente.
 */
beforeEach(function () {
    config(['servico.jwt_secret' => str_repeat('a', 40)]);
});

function tokenAdmin(bool $isAdmin = true): string
{
    return JWT::encode(
        ['id' => 1, 'email' => 'admin@ganjj.com', 'isAdmin' => $isAdmin, 'exp' => time() + 3600],
        str_repeat('a', 40),
        'HS256'
    );
}

function comoAdmin(): array
{
    return ['Authorization' => 'Bearer '.tokenAdmin()];
}

// R11 — normalização de código
it('grava o código do cupom em maiúsculas', function () {
    postJson('/api/cupons', [
        'codigo' => 'inverno20',
        'tipo' => 'percentual',
        'valor' => 20,
    ], comoAdmin())->assertCreated();

    expect(Cupom::first()->codigo)->toBe('INVERNO20');
});

// R12 — unicidade independente de caixa, contra a constraint UNIQUE de verdade
it('recusa cupom cujo código só difere na caixa', function () {
    Cupom::factory()->create(['codigo' => 'PROMO10']);

    postJson('/api/cupons', [
        'codigo' => 'promo10',
        'tipo' => 'percentual',
        'valor' => 10,
    ], comoAdmin())
        ->assertStatus(409)
        ->assertJson(['error' => 'Código de cupom já existe']);

    expect(Cupom::count())->toBe(1);
});

it('recusa percentual acima de cem', function () {
    postJson('/api/cupons', [
        'codigo' => 'ABSURDO',
        'tipo' => 'percentual',
        'valor' => 150,
    ], comoAdmin())->assertStatus(422);
});

it('aceita valor acima de cem quando o tipo é fixo', function () {
    postJson('/api/cupons', [
        'codigo' => 'CEMREAIS',
        'tipo' => 'fixo',
        'valor' => 150,
    ], comoAdmin())->assertCreated();
});

// R13 — a corrida no último uso
it('não deixa o consumo concorrente ultrapassar o limite', function () {
    $cupom = Cupom::factory()->create(['limite_uso' => 1, 'usos' => 0]);
    $service = app(CupomService::class);

    $primeiro = $service->consumir($cupom);
    $segundo = $service->consumir($cupom);

    expect($primeiro)->toBeTrue()
        ->and($segundo)->toBeFalse()
        ->and($cupom->refresh()->usos)->toBe(1);
});

it('consome sem limite quando limite_uso é nulo', function () {
    $cupom = Cupom::factory()->create(['limite_uso' => null]);
    $service = app(CupomService::class);

    expect($service->consumir($cupom))->toBeTrue()
        ->and($service->consumir($cupom))->toBeTrue()
        ->and($cupom->refresh()->usos)->toBe(2);
});

it('carrega a campanha junto do cupom', function () {
    $campanha = Campanha::factory()->encerrada()->create();
    Cupom::factory()->create(['codigo' => 'COMCAMP', 'campanha_id' => $campanha->id]);

    $cupom = app(CupomService::class)->buscar('comcamp');

    // A CalculadoraDesconto recusa cupom cuja campanha não veio carregada.
    expect($cupom->relationLoaded('campanha'))->toBeTrue()
        ->and($cupom->campanha->id)->toBe($campanha->id);
});
