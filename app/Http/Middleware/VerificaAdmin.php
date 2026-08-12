<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roda sempre depois de VerificaJwt, que preenche o atributo `usuario`.
 */
class VerificaAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->attributes->get('usuario');

        if (! is_array($usuario) || $usuario['isAdmin'] !== true) {
            return response()->json(['error' => 'Acesso restrito a administradores'], 403);
        }

        return $next($request);
    }
}
