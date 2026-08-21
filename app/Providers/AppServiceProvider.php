<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;

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
    }
}
