<?php

namespace Database\Seeders;

use App\Enums\AnalysisStatus;
use App\Enums\ContactMessageStatus;
use App\Enums\LeadStatus;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use App\Models\Company;
use App\Models\ContactMessage;
use App\Models\Lead;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample data for the admin dashboard in DEMO mode. Run explicitly:
 * php artisan db:seed --class=DemoDataSeeder
 * Everything it creates with an is_demo flag is marked as demo. It adds new rows on each run.
 */
class DemoDataSeeder extends Seeder
{
    /** @var list<array{string, string, string, string}> name, domain, market, language */
    private const COMPANIES = [
        ['Nordlicht Software', 'nordlicht-software.example', 'DE', 'de'],
        ['Bucharest Web Studio', 'bucharestwebstudio.example', 'RO', 'ro'],
        ['Lumen Dental Clinic', 'lumendental.example', 'RO', 'ro'],
        ['Casa Verde Hotel', 'casaverde-hotel.example', 'ES', 'es'],
        ['Atelier Beaumont', 'atelier-beaumont.example', 'FR', 'fr'],
        ['Rijnstad Accountants', 'rijnstad-accountants.example', 'NL', 'nl'],
        ['Porto Legal Partners', 'portolegal.example', 'PT', 'pt'],
        ['Krakow Fitness Hub', 'krakowfitness.example', 'PL', 'pl'],
        ['Milano Design Group', 'milanodesign.example', 'IT', 'it'],
        ['Thames Roofing Co', 'thamesroofing.example', 'GB', 'en'],
        ['Cluj Cloud Systems', 'cluj-cloud.example', 'RO', 'en'],
        ['Brightside Marketing', 'brightside-marketing.example', 'US', 'en'],
    ];

    public function run(): void
    {
        $this->call(AiProviderSeeder::class);

        $analyses = collect(self::COMPANIES)->map(function (array $c, int $i) {
            [$name, $domain, $market, $language] = $c;
            $company = Company::firstOrCreate(['domain' => $domain], [
                'name' => $name,
                'normalized_name' => Str::lower(Str::ascii($name)),
                'brand_variations' => [],
            ]);

            $factory = Analysis::factory()->demo()->state([
                'company_id' => $company->id,
                'company_name' => $name,
                'domain' => $domain,
                'website_url' => 'https://'.$domain,
                'market' => $market,
                'language' => $language,
                'created_at' => now()->subDays($i * 2 + 1)->subHours($i),
            ]);

            return match (true) {
                $i === 5, $i === 10 => $factory->failed()->create(),
                $i === 11 => $factory->create(['status' => AnalysisStatus::QueryingProviders, 'started_at' => now()->subMinute()]),
                default => $this->completed($factory, $i),
            };
        });

        $analyses->push($this->audit($analyses[1]));

        $analyses->each(fn (Analysis $analysis) => $this->usageLogs($analysis));

        $this->leads($analyses);
        $this->contactMessages();
    }

    private function completed($factory, int $index): Analysis
    {
        $analysis = $factory->completed()->create();
        $scores = [0, 18.18, 27.27, 36.36, 45.45, 54.55, 63.64, 72.73, 81.82, 9.09, 90.91, 100];
        $analysis->update(['visibility_score' => $scores[($index * 5) % count($scores)]]);

        return $analysis;
    }

    /** A completed demo GEO audit started from a completed RO demo check. */
    private function audit(Analysis $check): Analysis
    {
        return Analysis::factory()->audit()->demo()->create([
            'parent_analysis_id' => $check->id,
            'company_id' => $check->company_id,
            'company_name' => $check->company_name,
            'domain' => $check->domain,
            'website_url' => $check->website_url,
            'market' => $check->market,
            'language' => $check->language,
            'created_at' => $check->created_at->copy()->addHour(),
        ]);
    }

    private function usageLogs(Analysis $analysis): void
    {
        $at = $analysis->created_at;

        foreach ($analysis->responses as $response) {
            ApiUsageLog::create([
                'analysis_id' => $analysis->id,
                'ai_response_id' => $response->id,
                'ai_provider_id' => $response->ai_provider_id,
                'provider' => $response->provider,
                'model' => $response->model,
                'operation' => 'query',
                'status' => $response->status->value,
                'is_demo' => true,
                'input_tokens' => $response->input_tokens,
                'output_tokens' => $response->output_tokens,
                'search_calls' => $response->search_calls,
                'estimated_cost' => $response->estimated_cost,
                'duration_ms' => $response->duration_ms,
                'error_code' => $response->error_code,
                'created_at' => $at->copy()->addSeconds(random_int(5, 90)),
            ]);
        }
    }

    private function leads($analyses): void
    {
        $completed = $analyses->filter(fn (Analysis $a) => $a->status === AnalysisStatus::Completed)->values();

        foreach (LeadStatus::cases() as $i => $status) {
            Lead::factory()->create([
                'status' => $status,
                'analysis_id' => $completed[$i % $completed->count()]->id,
                'notes' => $status === LeadStatus::New ? null : 'Demo note: follow-up '.$status->label().'.',
                'created_at' => now()->subDays($i * 3 + 1),
            ]);
        }

        Lead::factory()->count(3)->create(['created_at' => now()->subHours(5)]);
    }

    private function contactMessages(): void
    {
        $statuses = [
            [ContactMessageStatus::New, null],
            [ContactMessageStatus::New, null],
            [ContactMessageStatus::InProgress, now()->subDay()],
            [ContactMessageStatus::Replied, now()->subDays(3)],
            [ContactMessageStatus::Archived, now()->subDays(10)],
        ];

        foreach ($statuses as $i => [$status, $readAt]) {
            ContactMessage::factory()->create([
                'status' => $status,
                'read_at' => $readAt,
                'created_at' => now()->subDays($i * 2),
            ]);
        }
    }
}
