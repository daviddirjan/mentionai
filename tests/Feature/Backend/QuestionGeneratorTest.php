<?php

namespace Tests\Feature\Backend;

use App\Models\Analysis;
use App\Models\Company;
use App\Models\PromptTemplate;
use App\Services\Analysis\QuestionGenerator;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PromptTemplateSeeder::class);
    }

    private function analysis(array $attributes = [], array $company = []): Analysis
    {
        $company = Company::factory()->create($company + ['name' => 'Axiontic SRL', 'domain' => 'axiontic.ro']);

        return Analysis::factory()->create($attributes + ['company_id' => $company->id, 'market' => 'RO', 'language' => 'en', 'industry' => 'Software Development']);
    }

    public function test_generates_the_configured_number_of_questions_and_records_config(): void
    {
        settings()->set('query_count', 2);
        $analysis = $this->analysis();

        $questions = app(QuestionGenerator::class)->generate($analysis);

        $this->assertCount(2, $questions);
        $this->assertSame([1, 2], $analysis->questions()->pluck('position')->all());
        $this->assertNotNull($questions[0]->template_snapshot);
        $this->assertNotNull($questions[0]->prompt_template_id);
        $config = $analysis->refresh()->query_config;
        $this->assertSame(2, $config['query_count']);
        $this->assertCount(2, $config['template_ids']);
    }

    public function test_generation_is_idempotent(): void
    {
        $analysis = $this->analysis();
        app(QuestionGenerator::class)->generate($analysis);
        app(QuestionGenerator::class)->generate($analysis);

        $this->assertSame(3, $analysis->questions()->count());
    }

    public function test_disabled_templates_are_not_used(): void
    {
        PromptTemplate::query()->update(['is_enabled' => false]);
        PromptTemplate::query()->first()->update(['is_enabled' => true]);

        $this->assertCount(1, app(QuestionGenerator::class)->generate($this->analysis()));
    }

    public function test_falls_back_to_a_builtin_template_when_none_enabled(): void
    {
        PromptTemplate::query()->update(['is_enabled' => false]);

        $questions = app(QuestionGenerator::class)->generate($this->analysis());

        $this->assertCount(1, $questions);
        $this->assertNull($questions[0]->prompt_template_id);
    }

    public function test_missing_values_fall_back_to_generic_words_and_market_country(): void
    {
        $analysis = $this->analysis(['industry' => null, 'website_inspection' => ['fetched' => false]]);

        $texts = app(QuestionGenerator::class)->generate($analysis)->pluck('question')->all();

        $this->assertStringContainsString('the best companies in Romania', $texts[0]);
        $this->assertStringContainsString('businesses in Romania', $texts[1]); // {service} -> industry -> "businesses"
        $this->assertStringContainsString('in Romania', $texts[2]);            // {city} -> market
    }

    public function test_uses_inspection_service_and_city_when_no_industry_given(): void
    {
        $analysis = $this->analysis(['industry' => null, 'website_inspection' => ['fetched' => true, 'services' => ['ERP Development'], 'city' => 'Timisoara']]);

        $texts = app(QuestionGenerator::class)->generate($analysis)->pluck('question')->implode(' | ');

        $this->assertStringContainsString('ERP Development', $texts);
        $this->assertStringContainsString('Timisoara', $texts);
    }

    public function test_visitor_location_fills_the_local_question_ahead_of_the_scraped_city(): void
    {
        $analysis = $this->analysis(['location' => 'Cluj-Napoca', 'website_inspection' => ['city' => 'Timisoara']]);

        $texts = app(QuestionGenerator::class)->generate($analysis)->pluck('question');

        $this->assertSame('I need a trustworthy Software Development provider in Cluj-Napoca, Romania. Which companies would you suggest?', $texts[2]);
        $this->assertStringContainsString('in Romania', $texts[0]);
        $this->assertStringNotContainsString('Timisoara', $texts->implode(' '));
    }

    public function test_location_is_localized_and_not_doubled_with_the_country(): void
    {
        $terms = QuestionGenerator::brandTerms('Zorbix', 'zorbix.example');
        $local = 'I need a trustworthy {industry} provider in {city}. Which companies would you suggest?';

        $this->assertStringContainsString('în Iași, România.', QuestionGenerator::build($local, 'ro', 'RO', 'IT', [], 0, $terms, 'Iași'));
        $this->assertStringContainsString('in Cluj, Romania.', QuestionGenerator::build($local, 'en', 'RO', 'IT', [], 0, $terms, 'Cluj, Romania'));
        $this->assertStringContainsString('in Romania.', QuestionGenerator::build($local, 'en', 'RO', 'IT', [], 0, $terms, 'Zorbix'));
    }

    public function test_questions_are_written_in_the_analysis_language(): void
    {
        $expect = ['ro' => 'România', 'de' => 'Deutschland', 'es' => 'España', 'fr' => 'France', 'it' => 'Italia', 'pt' => 'Portugal', 'nl' => 'Nederland', 'pl' => 'Polska'];
        $markets = ['ro' => 'RO', 'de' => 'DE', 'es' => 'ES', 'fr' => 'FR', 'it' => 'IT', 'pt' => 'PT', 'nl' => 'NL', 'pl' => 'PL'];

        foreach ($expect as $lang => $country) {
            $analysis = $this->analysis(['language' => $lang, 'market' => $markets[$lang]], ['domain' => "{$lang}.example", 'name' => 'Zorbix']);
            $texts = app(QuestionGenerator::class)->generate($analysis)->pluck('question');

            $this->assertCount(3, $texts, $lang);
            $this->assertStringContainsString($country, $texts->implode(' '), $lang);
            $this->assertStringNotContainsString('Recommend the best', $texts->implode(' '), $lang);
            $this->assertStringNotContainsString('{', $texts->implode(' '), $lang);
        }
    }

    public function test_english_keeps_admin_template_wording(): void
    {
        PromptTemplate::query()->delete();
        PromptTemplate::create(['name' => 'Custom', 'template' => 'Who are the leading {industry} experts in {city}?', 'is_enabled' => true]);

        $texts = app(QuestionGenerator::class)->generate($this->analysis(['website_inspection' => ['city' => 'Cluj']]))->pluck('question');

        $this->assertSame('Who are the leading Software Development experts in Cluj?', $texts[0]);
    }

    public function test_questions_never_contain_the_company_name_variations_or_domain(): void
    {
        $analysis = $this->analysis(
            ['industry' => 'Axiontic Software Development', 'language' => 'ro', 'website_inspection' => [
                'services' => ['Axiontic ERP', 'axiontic.ro hosting', 'Cloud Hosting'],
                'city' => 'Axiontic City',
            ]],
            ['brand_variations' => ['AXN Labs']],
        );

        $texts = app(QuestionGenerator::class)->generate($analysis)->pluck('question');

        foreach ($texts as $text) {
            foreach (['axiontic', 'axn labs', 'axiontic.ro'] as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $text);
            }
        }
    }

    public function test_leak_detection_is_diacritic_and_case_insensitive(): void
    {
        $terms = QuestionGenerator::brandTerms('Șoseaua Verde SRL', 'soseaua-verde.ro', ['SV Group']);

        $this->assertTrue(QuestionGenerator::leaks('Is SOSEAUA VERDE good?', $terms));
        $this->assertTrue(QuestionGenerator::leaks('Visit soseaua-verde.ro now', $terms));
        $this->assertTrue(QuestionGenerator::leaks('what about sv group?', $terms));
        $this->assertFalse(QuestionGenerator::leaks('Recommend the best roofing companies in Romania.', $terms));
    }

    public function test_strip_removes_brand_words_and_keeps_the_sentence(): void
    {
        $terms = QuestionGenerator::brandTerms('Acme Software', 'acme.ro');

        $this->assertSame('Is a good provider?', QuestionGenerator::strip('Is Acme Software a good provider?', $terms));
        $this->assertSame('Where is based?', QuestionGenerator::strip('Where is acme.ro based?', $terms));
    }

    public function test_a_brand_that_equals_the_industry_still_never_leaks(): void
    {
        $analysis = $this->analysis(['industry' => 'Zorbix', 'language' => 'en'], ['name' => 'Zorbix', 'domain' => 'zorbix.example']);

        foreach (app(QuestionGenerator::class)->generate($analysis) as $question) {
            $this->assertStringNotContainsStringIgnoringCase('zorbix', $question->question);
        }
    }

    public function test_scraped_services_skip_nav_noise_and_brand_products(): void
    {
        $inspection = ['services' => ['services', 'tools', 'works', 'Axionlab', 'Axionlab Studio', 'Web Development']];
        $terms = ['Axiontic', 'axiontic.com'];

        $question = QuestionGenerator::build('Which companies offer {service} in {market}?', 'en', 'RO', null, $inspection, 0, $terms);
        $this->assertStringContainsString('Web Development', $question);

        $withIndustry = QuestionGenerator::build('Which companies offer {service} in {market}?', 'en', 'RO', 'ERP software', $inspection, 0, $terms);
        $this->assertStringContainsString('ERP software', $withIndustry);
    }
}
