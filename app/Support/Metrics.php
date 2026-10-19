<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Três contadores de domínio no formato texto do Prometheus, sem dependência nova.
 *
 * Ficam no store `file` e não no padrão: o cluster roda CACHE_STORE=array, que
 * morre a cada request e zeraria tudo. O arquivo é por pod, o que é o certo —
 * o Prometheus raspa cada pod e soma; um store compartilhado (MySQL) faria
 * todos os pods devolverem o mesmo total e a soma sairia multiplicada.
 *
 * Os motivos de recusa são um conjunto fixo: usar a mensagem livre ("Valor mínimo
 * de R$ 50,00...") como label criaria uma série nova por valor.
 */
class Metrics
{
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

        return implode("\n", $lines)."\n";
    }

    private static function increment(string $name): void
    {
        $store = Cache::store('file');
        $key = "metrics.$name";

        // add() só grava se a chave não existe; sem isso o primeiro increment()
        // de uma chave ausente depende do driver.
        $store->add($key, 0);
        $store->increment($key);
    }

    private static function value(string $name): int
    {
        return (int) Cache::store('file')->get("metrics.$name", 0);
    }
}
