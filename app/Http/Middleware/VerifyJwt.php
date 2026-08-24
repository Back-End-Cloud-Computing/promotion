<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica o token emitido pelo serviço de Autorização. Este serviço nunca emite
 * token — só confere a assinatura com a chave pública RS256 do Autorização.
 */
class VerifyJwt
{
    private const ISSUER = 'ganjj-authorization';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return response()->error(401, 'Token de autenticação não fornecido');
        }

        $publicKey = config('service.jwt_public_key');

        if (empty($publicKey)) {
            // Sem chave não há verificação possível. Deixar passar transformaria
            // uma falha de configuração em brecha de autenticação.
            return response()->error(500, 'Serviço sem JWT_PUBLIC_KEY configurado');
        }

        try {
            // O algoritmo é fixado aqui e nunca lido do token: aceitar o "alg" do
            // próprio token é o que permite o ataque de "alg: none".
            $payload = JWT::decode($token, new Key($publicKey, 'RS256'));
        } catch (ExpiredException) {
            return response()->error(401, 'Token expirado');
        } catch (SignatureInvalidException) {
            return response()->error(401, 'Assinatura do token inválida');
        } catch (\Throwable) {
            return response()->error(401, 'Token inválido');
        }

        // Mesma validação que authorization/examples/verify_token.py documenta
        // como o contrato esperado para qualquer serviço consumidor.
        if (($payload->iss ?? null) !== self::ISSUER) {
            return response()->error(401, 'Emissor do token inesperado');
        }

        if (($payload->typ ?? null) !== 'access') {
            return response()->error(401, 'Token não é um token de acesso');
        }

        $request->attributes->set('user', [
            'id' => $payload->sub ?? null,
            'email' => $payload->email ?? null,
            'isAdmin' => ($payload->role ?? null) === 'ADMIN',
        ]);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        // O frontend da equipe autentica por cookie httpOnly; o serviço aceita os dois.
        return $request->cookie('accessToken');
    }
}
