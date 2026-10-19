<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // As Entities vivem em app/Domain/<Modulo>/Entities, mas as factories
        // continuam flat em database/factories — sem isto, o resolver padrão do
        // Laravel tentaria Database\Factories\Domain\<Modulo>\Entities\XFactory.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // O Scramble só serve /docs/api fora de `local` se esta Gate liberar. Não há
        // usuário logado aqui (a API usa JWT, sem sessão), então a decisão é por flag.
        Gate::define('viewApiDocs', fn ($user = null) => (bool) config('app.api_docs_public'));

        // Mesmo formato de erro de authorization/client: "error" é o texto padrão
        // do status HTTP, a mensagem humana vai em "message". Um formato só entre
        // os serviços do ecossistema.
        Response::macro('error', function (int $status, string $message, ?array $fields = null) {
            return response()->json(array_filter([
                'status' => $status,
                'error' => SymfonyResponse::$statusTexts[$status] ?? 'Error',
                'message' => $message,
                'fields' => $fields,
                'timestamp' => now()->toIso8601String(),
            ], fn ($value) => $value !== null), $status);
        });
    }
}
