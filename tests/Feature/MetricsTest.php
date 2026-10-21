<?php

use App\Domain\Coupons\Entities\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['service.internal_secret' => str_repeat('c', 40)]);
    // As métricas ficam no store `file` (o `array` do cluster morre a cada request).
    Cache::store('file')->flush();
});

function calcular(?string $cupom = null)
{
    return postJson('/internal/discounts/calculate', [
        'items' => [['product_id' => 1, 'unit_price' => 100, 'quantity' => 1]],
        'coupon' => $cupom,
    ], ['x-internal-secret' => str_repeat('c', 40)]);
}

it('expõe os três contadores em formato Prometheus, zerados no início', function () {
    get('/metrics')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
        ->assertSee('promocao_calculos_total 0', false)
        ->assertSee('promocao_cupons_aplicados_total 0', false)
        ->assertSee('promocao_cupons_recusados_total{motivo="expirado"} 0', false);
});

it('conta cálculos e cupom aplicado', function () {
    Coupon::factory()->create(['code' => 'OK10', 'type' => 'percentage', 'value' => 10]);

    calcular('OK10')->assertOk();
    calcular()->assertOk();

    get('/metrics')
        ->assertSee('promocao_calculos_total 2', false)
        ->assertSee('promocao_cupons_aplicados_total 1', false);
});

it('separa as recusas por motivo, sem usar a mensagem livre como label', function () {
    Coupon::factory()->create(['code' => 'MIN', 'minimum_value' => 500]);
    Coupon::factory()->exhausted()->create(['code' => 'ESGOTADO']);

    calcular('MIN');
    calcular('ESGOTADO');
    calcular('NAOEXISTE');

    get('/metrics')
        ->assertSee('promocao_cupons_recusados_total{motivo="valor_minimo"} 1', false)
        ->assertSee('promocao_cupons_recusados_total{motivo="limite_uso"} 1', false)
        ->assertSee('promocao_cupons_recusados_total{motivo="nao_encontrado"} 1', false);
});

it('não exige segredo interno nem JWT em /metrics', function () {
    get('/metrics')->assertOk();
});

it('conta requisições por rota normalizada e classe de status, sem o código do cupom no label', function () {
    get('/api/sale')->assertOk();
    get('/api/coupons/ABC123/validate');
    get('/api/coupons/OUTRO99/validate');
    get('/rota-que-nao-existe')->assertNotFound();

    $body = get('/metrics')->assertOk()->getContent();

    expect($body)
        ->toContain('promocao_http_requests_total{metodo="GET",rota="api/sale",classe="2xx"} 1')
        ->toContain('promocao_http_requests_total{metodo="GET",rota="desconhecida",classe="4xx"} 1')
        ->not->toContain('ABC123')
        ->not->toContain('OUTRO99');

    // As duas chamadas de cupom caem na mesma série, qualquer que seja o status.
    preg_match_all('/promocao_http_requests_total\{metodo="GET",rota="api\/coupons\/\{code\}\/validate",classe="\dxx"\} (\d+)/', $body, $m);
    expect(array_sum($m[1]))->toBe(2);
});

it('expõe o histograma de duração com buckets cumulativos, soma e contagem', function () {
    get('/api/sale')->assertOk();

    $body = get('/metrics')->getContent();

    expect($body)
        ->toContain('# TYPE promocao_http_request_duration_seconds histogram')
        ->toContain('promocao_http_request_duration_seconds_bucket{metodo="GET",rota="api/sale",le="+Inf"} 1')
        ->toContain('promocao_http_request_duration_seconds_count{metodo="GET",rota="api/sale"} 1')
        ->toContain('promocao_http_request_duration_seconds_sum{metodo="GET",rota="api/sale"}');
});

it('não conta o próprio /metrics', function () {
    get('/metrics');

    expect(get('/metrics')->getContent())->not->toContain('rota="metrics"');
});
