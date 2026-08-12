<?php

namespace App\Services;

use App\Models\Cupom;

class CupomService
{
    /**
     * Busca pelo código já normalizado, com a campanha carregada — a
     * CalculadoraDesconto recusa cupom cuja campanha não veio junto.
     */
    public function buscar(string $codigo): ?Cupom
    {
        return Cupom::query()
            ->with('campanha')
            ->porCodigo($codigo)
            ->first();
    }

    /**
     * Consome um uso. Devolve false se o limite já estava esgotado.
     *
     * A trava está no próprio WHERE: se duas requisições concorrerem pelo último
     * uso, só uma afeta linha. Resolve a corrida sem SELECT ... FOR UPDATE e sem
     * lock de aplicação.
     */
    public function consumir(Cupom $cupom): bool
    {
        $afetadas = Cupom::query()
            ->whereKey($cupom->getKey())
            ->where(function ($q) {
                $q->whereNull('limite_uso')
                    ->orWhereColumn('usos', '<', 'limite_uso');
            })
            ->increment('usos');

        return $afetadas > 0;
    }
}
