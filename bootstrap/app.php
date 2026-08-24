<?php

use App\Domain\Coupons\Exceptions\DuplicateCouponCodeException;
use App\Http\Middleware\VerifyAdmin;
use App\Http\Middleware\VerifyInternalSecret;
use App\Http\Middleware\VerifyJwt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Sem o prefixo /api: estas rotas não passam pelo API Gateway.
            Route::middleware('api')->group(base_path('routes/internal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt' => VerifyJwt::class,
            'admin' => VerifyAdmin::class,
            'internal' => VerifyInternalSecret::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // O resto do sistema responde erro como {"error": "..."}. Sem isto, o
        // Laravel devolveria {"message": ..., "errors": {...}} e cada consumidor
        // precisaria tratar dois formatos.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*', 'internal/*', 'health*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json(['error' => $e->validator->errors()->first()], 422);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json(['error' => 'Recurso não encontrado'], 404);
            }

            if ($e instanceof DuplicateCouponCodeException) {
                return response()->json(['error' => $e->getMessage()], 409);
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json(
                    ['error' => $e->getMessage() ?: 'Erro na requisição'],
                    $e->getStatusCode()
                );
            }

            return null;
        });
    })->create();
