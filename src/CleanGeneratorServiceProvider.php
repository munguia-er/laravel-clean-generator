<?php

namespace MunguiaEr\LaravelCleanGenerator;

use Illuminate\Support\ServiceProvider;
use MunguiaEr\LaravelCleanGenerator\Commands\GenerateCleanCrudCommand;

class CleanGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/clean-generator.php', 'clean-generator'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/clean-generator.php' => config_path('clean-generator.php'),
            ], 'clean-generator-config');

            $this->commands([
                GenerateCleanCrudCommand::class,
            ]);
        }
    }
}
