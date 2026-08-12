<?php

use App\Models\Promocao;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['servico.jwt_secret' => str_repeat('a', 40)]);
});

function comoAdminPromocao(): array
{
    $token = JWT::encode(
        ['id' => 1, 'email' => 'admin@ganjj.com', 'isAdmin' => true, 'exp' => time() + 3600],
        str_repeat('a', 40),
        'HS256'
    );

    return ['Authorization' => 'Bearer '.$token];
}

// Mesma regra do cupom: unicidade é conflito (409), contra a constraint UNIQUE
// de verdade — não validação de formato da aplicação.
it('recusa promoção para produto que já tem uma', function () {
    Promocao::factory()->create(['produto_id' => 1]);

    postJson('/api/promocoes', [
        'produto_id' => 1,
        'desconto_pct' => 50,
        'categoria' => 'Inverno',
    ], comoAdminPromocao())
        ->assertStatus(409)
        ->assertJson(['error' => 'Produto já possui promoção']);

    expect(Promocao::count())->toBe(1);
});
