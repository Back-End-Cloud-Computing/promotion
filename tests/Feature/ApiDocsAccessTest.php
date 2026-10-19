<?php

use function Pest\Laravel\get;

// APP_ENV=testing: fora de `local`, então vale a Gate viewApiDocs do Scramble.
it('nega a documentação da API por padrão fora de local', function () {
    config(['app.api_docs_public' => false]);

    get('/docs/api.json')->assertForbidden();
});

it('libera a documentação quando API_DOCS_PUBLIC está ligada', function () {
    config(['app.api_docs_public' => true]);

    get('/docs/api.json')->assertOk();
});
