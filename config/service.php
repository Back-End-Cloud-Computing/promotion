<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Segredos compartilhados
    |--------------------------------------------------------------------------
    |
    | JWT_PUBLIC_KEY é a chave pública RS256 (PEM) do serviço de Autorização,
    | que emite os tokens. Aqui eles são apenas verificados.
    |
    | INTERNAL_SECRET protege as rotas /internal consumidas por Carrinho e
    | Pedido. Nenhum dos dois tem valor padrão de propósito: um default faria o
    | serviço subir inseguro em vez de falhar visivelmente.
    |
    */

    'jwt_public_key' => env('JWT_PUBLIC_KEY'),

    'internal_secret' => env('INTERNAL_SECRET'),

];
