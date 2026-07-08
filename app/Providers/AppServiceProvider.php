<?php

namespace App\Providers;

use App\Services\Ai\PromptTemplateRegistry;
use App\Services\Ai\Tools\Dcp\DcpPromptTemplate;
use App\Services\Ai\Tools\Suggest\BrandingSuggestionPromptTemplate;
use App\Services\Ai\Tools\Suggest\ServiceSuggestionPromptTemplate;
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
        $registry = $this->app->make(PromptTemplateRegistry::class);
        $registry->register(new DcpPromptTemplate);
        $registry->register(new ServiceSuggestionPromptTemplate);
        $registry->register(new BrandingSuggestionPromptTemplate);
    }
}
