<?php

use function Pest\Laravel\get;

it('identifica o pod que atendeu no header X-Served-By', function () {
    get('/up')->assertHeader('X-Served-By', gethostname());
});

it('inclui o header também nas respostas de erro', function () {
    get('/api/rota-inexistente')->assertNotFound()->assertHeader('X-Served-By', gethostname());
});
