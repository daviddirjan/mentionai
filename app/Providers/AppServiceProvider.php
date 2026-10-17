<?php

namespace App\Providers;

use App\Contracts\AnalysisMaintenance;
use App\Contracts\AnalysisStarter;
use App\Contracts\ProviderRegistry;
use App\Services\Ai\DefaultProviderRegistry;
use App\Services\Analysis\DefaultAnalysisMaintenance;
use App\Services\Analysis\DefaultAnalysisStarter;
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
