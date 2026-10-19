<?php

namespace App\Services\Audit;

use App\Contracts\AuditStarter;
use App\Contracts\ProviderRegistry;
use App\Data\AnalysisStartResult;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisTier;
use App\Exceptions\AnalysisRejected;
use App\Models\Analysis;
use App\Models\Lead;
use App\Services\Analysis\StartLimits;

/**
 * Starts a GEO audit from a completed free check. Same IP / daily / budget limits and reuse rules as the free check,
 * but no per-domain free quota: the lead's email is the price of the audit.
 */
class DefaultAuditStarter implements AuditStarter
{
    public function __construct(
        private readonly StartLimits $limits,
        private readonly ProviderRegistry $registry,
    ) {}

    public function start(Analysis $check, Lead $lead, string $ip): AnalysisStartResult
    {
        if ($check->isAudit() || $check->status !== AnalysisStatus::Completed) {
            throw new AnalysisRejected(AnalysisRejected::NOT_ELIGIBLE);
        }

        if (! settings('audit_enabled')) {
            throw new AnalysisRejected(AnalysisRejected::DISABLED);
        }
        $this->limits->assertEnabled();
        $this->limits->assertIpAllowed($ip);

        $configHash = $this->limits->configHash(
            $check->domain, $check->market, $check->language, $check->location, (int) settings('audit_query_count'), ['tier:audit'],
        );

        $result = $this->limits->locked($check->domain, function () use ($check, $ip, $configHash): AnalysisStartResult {
            if (($reusable = $this->limits->reusable($configHash)) !== null) {
                return new AnalysisStartResult($reusable, true);
            }

            $this->limits->assertDailyAndBudget();

            $audit = Analysis::create([
                'company_id' => $check->company_id,
                'parent_analysis_id' => $check->id,
                'tier' => AnalysisTier::Audit,
                'company_name' => $check->company_name,
                'website_url' => $check->website_url,
                'domain' => $check->domain,
                'market' => $check->market,
                'location' => $check->location,
                'language' => $check->language,
                'industry' => $check->industry,
                'interface_locale' => $check->interface_locale,
                'status' => AnalysisStatus::Pending,
                'is_demo' => $this->registry->isMockMode(),
                'config_hash' => $configHash,
                'ip_hash' => $this->limits->ipHash($ip),
            ]);

            $this->limits->hitIp($ip);

            return new AnalysisStartResult($audit->refresh(), false);
        });

        $lead->update(['analysis_id' => $result->analysis->id]);

        return $this->limits->dispatch($result);
    }
}
