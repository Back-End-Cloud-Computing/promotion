<?php

namespace App\Domain\Promotions\Enums;

/**
 * Os valores ficam em português porque espelham a categorização real do
 * catálogo do serviço de Produto (outro microsserviço, Python/MongoDB) — não
 * são um identificador nosso para traduzir.
 */
enum ProductCategory: string
{
    case Superiores = 'Superiores';
    case Inferiores = 'Inferiores';
    case Inverno = 'Inverno';
}
