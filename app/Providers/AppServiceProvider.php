<?php

namespace App\Providers;

use App\Services\Ai\PromptTemplateRegistry;
use App\Services\Ai\Tools\Dcp\DcpPromptTemplate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PromptTemplateRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(PromptTemplateRegistry::class)->register(new DcpPromptTemplate);
    }
}
