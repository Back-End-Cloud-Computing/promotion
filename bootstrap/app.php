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
        // O resto do sistema responde erro em {status, error, message, fields?,
        // timestamp} (ver Response::macro('error', ...) em AppServiceProvider).
        // Sem isto, o Laravel devolveria {"message": ..., "errors": {...}} e cada
        // consumidor precisaria tratar dois formatos.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*', 'internal/*', 'health*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                $fields = collect($e->validator->errors()->toArray())->map(fn ($messages) => $messages[0])->all();

                return response()->error(422, 'Há campos inválidos na requisição.', $fields);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->error(404, 'Recurso não encontrado');
            }

            if ($e instanceof DuplicateCouponCodeException) {
                return response()->error(409, $e->getMessage());
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->error($e->getStatusCode(), $e->getMessage() ?: 'Erro na requisição');
            }

            return null;
        });
    })->create();
