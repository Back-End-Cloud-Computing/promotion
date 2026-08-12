<?php

namespace Database\Seeders;

use App\Models\Campanha;
use App\Models\Cupom;
use App\Models\Promocao;
use Illuminate\Database\Seeder;

/**
 * Cenário de demonstração para exercitar a API no Postman sem cadastrar nada
 * à mão. Os cupons em estado inválido são o ponto: permitem demonstrar cada
 * motivo de recusa sem precisar mexer no relógio do sistema.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $inverno = Campanha::create([
            'nome' => 'Liquida Inverno',
            'descricao' => 'Casacos e jaquetas com desconto.',
            'inicia_em' => now()->subDays(5),
            'termina_em' => now()->addDays(25),
            'ativo' => true,
        ]);

        $encerrada = Campanha::create([
            'nome' => 'Black Friday do ano passado',
            'inicia_em' => now()->subYear(),
            'termina_em' => now()->subYear()->addDays(7),
            'ativo' => true,
        ]);

        $promocoes = [
            ['produto_id' => 1, 'desconto_pct' => 30, 'categoria' => 'Inverno', 'campanha_id' => $inverno->id],
            ['produto_id' => 2, 'desconto_pct' => 40, 'categoria' => 'Inverno', 'campanha_id' => $inverno->id],
            ['produto_id' => 3, 'desconto_pct' => 15, 'categoria' => 'Superiores', 'campanha_id' => null],
            ['produto_id' => 4, 'desconto_pct' => 20, 'categoria' => 'Inferiores', 'campanha_id' => null],
            // Promoção presa a campanha encerrada: não deve aparecer em /api/sale.
            ['produto_id' => 5, 'desconto_pct' => 70, 'categoria' => 'Superiores', 'campanha_id' => $encerrada->id],
            ['produto_id' => 6, 'desconto_pct' => 25, 'categoria' => 'Superiores', 'ativo' => false],
        ];

        foreach ($promocoes as $promocao) {
            Promocao::create($promocao);
        }

        $cupons = [
            // Válido, sem restrição.
            ['codigo' => 'BEMVINDO10', 'tipo' => 'percentual', 'valor' => 10],
            // Válido, de valor fixo.
            ['codigo' => 'MENOS25', 'tipo' => 'fixo', 'valor' => 25],
            // Recusa por valor mínimo.
            ['codigo' => 'FRETE200', 'tipo' => 'percentual', 'valor' => 15, 'valor_minimo' => 200],
            // Recusa por limite atingido.
            ['codigo' => 'ESGOTADO', 'tipo' => 'percentual', 'valor' => 50, 'limite_uso' => 10, 'usos' => 10],
            // Recusa por estar inativo.
            ['codigo' => 'DESLIGADO', 'tipo' => 'percentual', 'valor' => 30, 'ativo' => false],
            // Recusa por campanha encerrada.
            ['codigo' => 'EXPIRADO', 'tipo' => 'percentual', 'valor' => 40, 'campanha_id' => $encerrada->id],
        ];

        foreach ($cupons as $cupom) {
            Cupom::create($cupom);
        }
    }
}
