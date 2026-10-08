<?php

declare(strict_types=1);

namespace App\Providers;

use App\Engine\Weights;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Read weights.yml once per process. A long-running rollup worker picks up a new file
        // when it is restarted, which every deploy does.
        $this->app->singleton(Weights::class, fn (): Weights => Weights::fromFile((string) config('engine.weights_path')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
