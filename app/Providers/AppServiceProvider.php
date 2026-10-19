<?php

namespace App\Providers;

use App\Contracts\AnalysisMaintenance;
use App\Contracts\AnalysisStarter;
use App\Contracts\AuditNarrator;
use App\Contracts\AuditReportBuilder;
use App\Contracts\AuditStarter;
use App\Contracts\ProviderRegistry;
use App\Contracts\RecommendationBuilder;
use App\Contracts\ReportRenderer;
use App\Contracts\SiteAuditor;
use App\Services\Ai\DefaultProviderRegistry;
use App\Services\Analysis\DefaultAnalysisMaintenance;
use App\Services\Analysis\DefaultAnalysisStarter;
use App\Services\Audit\AiAuditNarrator;
use App\Services\Audit\DefaultAuditReportBuilder;
use App\Services\Audit\DefaultAuditStarter;
use App\Services\Audit\DefaultSiteAuditor;
use App\Services\Audit\DompdfReportRenderer;
use App\Services\Audit\RuleRecommendationBuilder;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Contract bindings. Implementations live in app/Services (Backend & AI ownership).
     */
    public array $bindings = [
        AnalysisStarter::class => DefaultAnalysisStarter::class,
        AnalysisMaintenance::class => DefaultAnalysisMaintenance::class,
        ProviderRegistry::class => DefaultProviderRegistry::class,
        AuditStarter::class => DefaultAuditStarter::class,
        SiteAuditor::class => DefaultSiteAuditor::class,
        RecommendationBuilder::class => RuleRecommendationBuilder::class,
        AuditNarrator::class => AiAuditNarrator::class,
        AuditReportBuilder::class => DefaultAuditReportBuilder::class,
        ReportRenderer::class => DompdfReportRenderer::class,
    ];

    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
