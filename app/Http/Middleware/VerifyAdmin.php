<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roda sempre depois de VerifyJwt, que preenche o atributo `user`.
 */
class VerifyAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('user');

        if (! is_array($user) || $user['isAdmin'] !== true) {
            return response()->json(['error' => 'Acesso restrito a administradores'], 403);
        }

        return $next($request);
    }
}
