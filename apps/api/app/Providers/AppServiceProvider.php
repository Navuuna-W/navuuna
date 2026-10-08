<?php

declare(strict_types=1);

namespace App\Providers;

use App\Engine\RerollAfterFindingChanged;
use App\Engine\Weights;
use App\Findings\FlagStateChanged;
use Illuminate\Contracts\Events\Dispatcher;
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
    public function boot(Dispatcher $events): void
    {
        // A finding's move re-rolls its entity, so V2 follows the review (ADR-010 DEC-08).
        $events->listen(FlagStateChanged::class, RerollAfterFindingChanged::class);
    }
}
