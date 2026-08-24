<?php

use Firebase\JWT\JWT;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// Unit também estende TestCase: os testes não tocam o banco, mas instanciar um
// Model com atributo datetime pede o grammar da conexão, que só existe com a
// aplicação inicializada. Boot sem banco continua custando milissegundos.
pest()->extend(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Par RSA efêmero para assinar tokens de teste como o serviço de Autorização
 * faria (RS256). Gerado uma vez por processo — keygen custa tempo real.
 *
 * @return array{0: string, 1: string} [chave privada PEM, chave pública PEM]
 */
function jwtTestKeyPair(): array
{
    static $pair;

    if ($pair === null) {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $privateKey);
        $publicKey = openssl_pkey_get_details($resource)['key'];

        $pair = [$privateKey, $publicKey];
    }

    return $pair;
}

function jwtTestPublicKey(): string
{
    return jwtTestKeyPair()[1];
}

function jwtTestToken(array $payload = [], ?string $privateKey = null): string
{
    return JWT::encode(array_merge([
        'sub' => '1',
        'email' => 'user@ganjj.com',
        'role' => 'admin',
        'exp' => time() + 3600,
    ], $payload), $privateKey ?? jwtTestKeyPair()[0], 'RS256');
}
