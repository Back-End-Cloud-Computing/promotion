<?php

use App\Http\Middleware\VerifyJwt;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// Baixa entropia de propósito: um segredo realista aqui dispara o gitleaks.
function segredo(): string
{
    return str_repeat('a', 40);
}

beforeEach(function () {
    config([
        'service.jwt_secret' => segredo(),
        'service.internal_secret' => 'segredo-interno-de-teste',
    ]);
});

function token(array $payload = [], ?string $segredo = null): string
{
    return JWT::encode(array_merge([
        'id' => 1,
        'email' => 'user@ganjj.com',
        'isAdmin' => true,
        'exp' => time() + 3600,
    ], $payload), $segredo ?? segredo(), 'HS256');
}

it('recusa rota admin sem token', function () {
    getJson('/api/coupons')
        ->assertStatus(401)
        ->assertJson(['error' => 'Token de autenticação não fornecido']);
});

it('recusa token assinado com outro segredo', function () {
    getJson('/api/coupons', ['Authorization' => 'Bearer '.token([], str_repeat('b', 40))])
        ->assertStatus(401)
        ->assertJson(['error' => 'Assinatura do token inválida']);
});

it('recusa token expirado', function () {
    getJson('/api/coupons', ['Authorization' => 'Bearer '.token(['exp' => time() - 60])])
        ->assertStatus(401)
        ->assertJson(['error' => 'Token expirado']);
});

it('recusa usuário sem privilégio de admin', function () {
    getJson('/api/coupons', ['Authorization' => 'Bearer '.token(['isAdmin' => false])])
        ->assertStatus(403)
        ->assertJson(['error' => 'Acesso restrito a administradores']);
});

it('aceita token de admin válido', function () {
    getJson('/api/coupons', ['Authorization' => 'Bearer '.token()])->assertOk();
});

it('aceita token enviado por cookie', function () {
    // Exercita o middleware direto: os helpers de cookie do TestCase não
    // repassam o cookie de forma confiável em rota sob /api.
    $request = Request::create('/api/coupons', 'GET', cookies: ['accessToken' => token()]);

    $response = (new VerifyJwt)->handle($request, fn ($r) => response()->json([
        'user' => $r->attributes->get('user'),
    ]));

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true)['user']['email'])->toBe('user@ganjj.com');
});

it('recusa rota interna sem o segredo compartilhado', function () {
    postJson('/internal/discounts/calculate', ['items' => []])
        ->assertStatus(403)
        ->assertJson(['error' => 'Segredo interno inválido']);
});

it('recusa rota interna com segredo errado', function () {
    postJson('/internal/discounts/calculate', ['items' => []], ['x-internal-secret' => 'errado'])
        ->assertStatus(403);
});

it('deixa os health checks abertos', function () {
    // O kubelet não envia header de autenticação.
    getJson('/health')->assertOk()->assertJson(['status' => 'ok']);
    getJson('/health/ready')->assertOk()->assertJson(['status' => 'ready']);
});
