<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Contadores de domínio e métricas HTTP (requisições e latência) no formato texto
 * do Prometheus, sem dependência nova.
 *
 * Ficam no store `file` e não no padrão: o cluster roda CACHE_STORE=array, que
 * morre a cada request e zeraria tudo. O arquivo é por pod, o que é o certo —
 * o Prometheus raspa cada pod e soma; um store compartilhado (MySQL) faria
 * todos os pods devolverem o mesmo total e a soma sairia multiplicada.
 *
 * Os motivos de recusa são um conjunto fixo: usar a mensagem livre ("Valor mínimo
 * de R$ 50,00...") como label criaria uma série nova por valor. Pelo mesmo motivo
 * o status HTTP vira classe (2xx, 4xx...) e a rota é o template, não a URL.
 */
class Metrics
{
    public const UNKNOWN_ROUTE = 'desconhecida';

    // Segundos; cobre de uma consulta rápida até o limite do timeout do gateway.
    private const BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];

    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    private const CLASSES = ['2xx', '3xx', '4xx', '5xx'];

    private const REFUSAL_REASONS = [
        'nao_encontrado', 'inativo', 'expirado', 'nao_vigente', 'limite_uso', 'valor_minimo',
    ];

    public static function calculation(): void
    {
        self::increment('calculos');
    }

    public static function couponApplied(): void
    {
        self::increment('cupons_aplicados');
    }

    /** Recebe a mensagem de recusa do calculador e a reduz ao motivo estável. */
    public static function couponRefused(string $message): void
    {
        $reason = match (true) {
            str_contains($message, 'não encontrado') => 'nao_encontrado',
            str_contains($message, 'expirado') => 'expirado',
            str_contains($message, 'não vigente') => 'nao_vigente',
            str_contains($message, 'Limite de uso') => 'limite_uso',
            str_contains($message, 'Valor mínimo') => 'valor_minimo',
            default => 'inativo',
        };

        self::increment("cupons_recusados.$reason");
    }

    public static function httpRequest(string $method, string $route, int $status, float $seconds): void
    {
        $method = in_array($method, self::METHODS, true) ? $method : 'OTHER';
        $class = intdiv($status, 100).'xx';
        $series = self::seriesKey($method, $route);

        self::increment("http.req.$series.$class");
        self::increment("http.dur.$series.count");
        self::increment("http.dur.$series.sum_us", (int) round($seconds * 1e6));

        foreach (self::BUCKETS as $i => $le) {
            if ($seconds <= $le) {
                self::increment("http.dur.$series.b$i");
            }
        }
    }

    public static function render(): string
    {
        $lines = [
            '# HELP promocao_calculos_total Cálculos de desconto feitos em /internal/discounts/calculate.',
            '# TYPE promocao_calculos_total counter',
            'promocao_calculos_total '.self::value('calculos'),
            '# HELP promocao_cupons_aplicados_total Cupons aplicados com sucesso num cálculo.',
            '# TYPE promocao_cupons_aplicados_total counter',
            'promocao_cupons_aplicados_total '.self::value('cupons_aplicados'),
            '# HELP promocao_cupons_recusados_total Cupons recusados, por motivo.',
            '# TYPE promocao_cupons_recusados_total counter',
        ];

        foreach (self::REFUSAL_REASONS as $reason) {
            $lines[] = sprintf('promocao_cupons_recusados_total{motivo="%s"} %d', $reason, self::value("cupons_recusados.$reason"));
        }

        return implode("\n", [...$lines, ...self::renderHttp()])."\n";
    }

    /**
     * O cache não lista chaves, então as séries saem do conjunto conhecido:
     * as rotas registradas (mais `desconhecida`) x métodos. Só vai para o
     * texto o que já recebeu requisição, para não publicar centenas de zeros.
     *
     * @return array<int, string>
     */
    private static function renderHttp(): array
    {
        $pairs = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'metrics') {
                continue;
            }
            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD' && $method !== 'OPTIONS') {
                    $pairs[] = [$method, $route->uri()];
                }
            }
        }
        foreach ([...self::METHODS, 'OTHER'] as $method) {
            $pairs[] = [$method, self::UNKNOWN_ROUTE];
        }

        $requests = [
            '# HELP promocao_http_requests_total Requisições HTTP, por método, rota (template) e classe de status.',
            '# TYPE promocao_http_requests_total counter',
        ];
        $durations = [
            '# HELP promocao_http_request_duration_seconds Duração das requisições HTTP em segundos.',
            '# TYPE promocao_http_request_duration_seconds histogram',
        ];

        foreach ($pairs as [$method, $route]) {
            $series = self::seriesKey($method, $route);
            $labels = sprintf('metodo="%s",rota="%s"', $method, $route);

            foreach (self::CLASSES as $class) {
                $count = self::value("http.req.$series.$class");
                if ($count > 0) {
                    $requests[] = sprintf('promocao_http_requests_total{%s,classe="%s"} %d', $labels, $class, $count);
                }
            }

            $total = self::value("http.dur.$series.count");
            if ($total === 0) {
                continue;
            }
            foreach (self::BUCKETS as $i => $le) {
                $durations[] = sprintf('promocao_http_request_duration_seconds_bucket{%s,le="%s"} %d', $labels, $le, self::value("http.dur.$series.b$i"));
            }
            $durations[] = sprintf('promocao_http_request_duration_seconds_bucket{%s,le="+Inf"} %d', $labels, $total);
            $durations[] = sprintf('promocao_http_request_duration_seconds_sum{%s} %s', $labels, self::value("http.dur.$series.sum_us") / 1e6);
            $durations[] = sprintf('promocao_http_request_duration_seconds_count{%s} %d', $labels, $total);
        }

        return [...$requests, ...$durations];
    }

    private static function seriesKey(string $method, string $route): string
    {
        return sha1($method.'|'.$route);
    }

    private static function increment(string $name, int $by = 1): void
    {
        $store = Cache::store('file');
        $key = "metrics.$name";

        // add() só grava se a chave não existe; sem isso o primeiro increment()
        // de uma chave ausente depende do driver.
        $store->add($key, 0);
        $store->increment($key, $by);
    }

    private static function value(string $name): int
    {
        return (int) Cache::store('file')->get("metrics.$name", 0);
    }
}
