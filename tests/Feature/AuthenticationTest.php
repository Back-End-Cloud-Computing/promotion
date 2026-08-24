<?php

use App\Http\Middleware\VerifyJwt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'service.jwt_public_key' => jwtTestPublicKey(),
        'service.internal_secret' => 'segredo-interno-de-teste',
    ]);
});

it('recusa rota admin sem token', function () {
    getJson('/api/coupons')
        ->assertStatus(401)
        ->assertJson(['message' => 'Token de autenticação não fornecido']);
});

it('recusa token assinado por outra chave', function () {
    openssl_pkey_export(
        openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]),
        $outraChavePrivada
    );

    getJson('/api/coupons', ['Authorization' => 'Bearer '.jwtTestToken([], $outraChavePrivada)])
        ->assertStatus(401)
        ->assertJson(['message' => 'Assinatura do token inválida']);
});

it('recusa token expirado', function () {
    getJson('/api/coupons', ['Authorization' => 'Bearer '.jwtTestToken(['exp' => time() - 60])])
        ->assertStatus(401)
        ->assertJson(['message' => 'Token expirado']);
});

it('recusa usuário sem privilégio de admin', function () {
    getJson('/api/coupons', ['Authorization' => 'Bearer '.jwtTestToken(['role' => 'CLIENTE'])])
        ->assertStatus(403)
        ->assertJson(['message' => 'Acesso restrito a administradores']);
});

it('aceita token de admin válido', function () {
    getJson('/api/coupons', ['Authorization' => 'Bearer '.jwtTestToken()])->assertOk();
});

it('aceita token enviado por cookie', function () {
    // Exercita o middleware direto: os helpers de cookie do TestCase não
    // repassam o cookie de forma confiável em rota sob /api.
    $request = Request::create('/api/coupons', 'GET', cookies: ['accessToken' => jwtTestToken()]);

    $response = (new VerifyJwt)->handle($request, fn ($r) => response()->json([
        'user' => $r->attributes->get('user'),
    ]));

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true)['user']['email'])->toBe('user@ganjj.com');
});

it('recusa quando JWT_PUBLIC_KEY não está configurada', function () {
    config(['service.jwt_public_key' => null]);

    getJson('/api/coupons', ['Authorization' => 'Bearer '.jwtTestToken()])
        ->assertStatus(500)
        ->assertJson(['message' => 'Serviço sem JWT_PUBLIC_KEY configurado']);
});

it('recusa rota interna sem o segredo compartilhado', function () {
    postJson('/internal/discounts/calculate', ['items' => []])
        ->assertStatus(403)
        ->assertJson(['message' => 'Segredo interno inválido']);
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

it('recusa token com emissor inesperado', function () {
    getJson('/api/coupons', ['Authorization' => 'Bearer '.jwtTestToken(['iss' => 'outro-servico'])])
        ->assertStatus(401)
        ->assertJson(['message' => 'Emissor do token inesperado']);
});

it('recusa token que não é de acesso', function () {
    // Um refresh token é assinado pela mesma chave, mas nunca deveria autenticar
    // uma requisição — só serve para pedir um access token novo ao authorization.
    getJson('/api/coupons', ['Authorization' => 'Bearer '.jwtTestToken(['typ' => 'refresh'])])
        ->assertStatus(401)
        ->assertJson(['message' => 'Token não é um token de acesso']);
});

it('valida o token sem fazer nenhuma chamada de rede', function () {
    // A verificação é local por design (chave pública já carregada via env, sem
    // JWKS): se algum dia isso mudar sem querer, este teste estoura na hora.
    Http::preventStrayRequests();

    getJson('/api/coupons', ['Authorization' => 'Bearer '.jwtTestToken()])->assertOk();
});
