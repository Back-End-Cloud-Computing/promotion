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
 * Verifica o token emitido pelo serviço de Cliente. Este serviço nunca emite
 * token — só confere a assinatura com o segredo compartilhado, como cada
 * serviço faz no projeto de referência da equipe.
 */
class VerificaJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extrairToken($request);

        if ($token === null) {
            return response()->json(['error' => 'Token de autenticação não fornecido'], 401);
        }

        $segredo = config('servico.jwt_secret');

        if (empty($segredo)) {
            // Sem segredo não há verificação possível. Deixar passar transformaria
            // uma falha de configuração em brecha de autenticação.
            return response()->json(['error' => 'Serviço sem JWT_SECRET configurado'], 500);
        }

        try {
            // O algoritmo é fixado aqui e nunca lido do token: aceitar o "alg" do
            // próprio token é o que permite o ataque de "alg: none".
            $payload = JWT::decode($token, new Key($segredo, 'HS256'));
        } catch (ExpiredException) {
            return response()->json(['error' => 'Token expirado'], 401);
        } catch (SignatureInvalidException) {
            return response()->json(['error' => 'Assinatura do token inválida'], 401);
        } catch (\Throwable) {
            return response()->json(['error' => 'Token inválido'], 401);
        }

        $request->attributes->set('usuario', [
            'id' => $payload->id ?? null,
            'email' => $payload->email ?? null,
            'isAdmin' => (bool) ($payload->isAdmin ?? false),
        ]);

        return $next($request);
    }

    private function extrairToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        // O frontend da equipe autentica por cookie httpOnly; o serviço aceita os dois.
        return $request->cookie('accessToken');
    }
}
