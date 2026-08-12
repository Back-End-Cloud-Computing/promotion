<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Segredos compartilhados
    |--------------------------------------------------------------------------
    |
    | JWT_SECRET é o mesmo segredo usado pelo serviço de Cliente, que emite os
    | tokens. Aqui eles são apenas verificados.
    |
    | INTERNAL_SECRET protege as rotas /internal consumidas por Carrinho e
    | Pedido. Nenhum dos dois tem valor padrão de propósito: um default faria o
    | serviço subir inseguro em vez de falhar visivelmente.
    |
    */

    'jwt_secret' => env('JWT_SECRET'),

    'internal_secret' => env('INTERNAL_SECRET'),

];
