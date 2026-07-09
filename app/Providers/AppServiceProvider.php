<?php

namespace App\Providers;

use App\Services\Ai\PromptTemplateRegistry;
use App\Services\Ai\Tools\Assessment\AssessmentPromptTemplate;
use App\Services\Ai\Tools\Dcp\DcpPromptTemplate;
use App\Services\Ai\Tools\Email\EmailPromptTemplate;
use App\Services\Ai\Tools\Proposal\ProposalPromptTemplate;
use App\Services\Ai\Tools\Spec\SpecAmendPromptTemplate;
use App\Services\Ai\Tools\Spec\SpecCompilePromptTemplate;
use App\Services\Ai\Tools\Suggest\BrandingSuggestionPromptTemplate;
use App\Services\Ai\Tools\Suggest\ContentSocialSuggestionPromptTemplate;
use App\Services\Ai\Tools\Suggest\GrowthSuggestionPromptTemplate;
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
        $registry->register(new ContentSocialSuggestionPromptTemplate);
        $registry->register(new GrowthSuggestionPromptTemplate);
        $registry->register(new SpecCompilePromptTemplate);
        $registry->register(new SpecAmendPromptTemplate);
        $registry->register(new AssessmentPromptTemplate);
        $registry->register(new ProposalPromptTemplate);
        $registry->register(new EmailPromptTemplate);
    }
}
