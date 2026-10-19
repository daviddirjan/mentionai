<?php

namespace Tests\Feature\Backend;

use App\Enums\AnalysisTier;
use App\Models\Analysis;
use App\Models\Company;
use App\Models\PromptTemplate;
use App\Services\Analysis\QuestionGenerator;
use App\Services\Detection\TextNormalizer;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditQuestionGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PromptTemplateSeeder::class);
    }

    private function analysis(array $attributes = []): Analysis
    {
        $company = Company::factory()->create(['name' => 'Axiontic SRL', 'domain' => 'axiontic.ro']);

        return Analysis::factory()->create($attributes + [
            'company_id' => $company->id, 'market' => 'RO', 'language' => 'en', 'industry' => 'Software Development', 'tier' => AnalysisTier::Audit,
        ])->refresh();
    }

    /** @return array<string, array{0: string}> */
    public static function languages(): array
    {
        return array_combine(
            ['en', 'ro', 'de', 'es', 'fr', 'it', 'pt', 'nl', 'pl'],
            array_map(fn ($l) => [$l], ['en', 'ro', 'de', 'es', 'fr', 'it', 'pt', 'nl', 'pl']),
        );
    }

    #[DataProvider('languages')]
    public function test_audit_generates_25_unique_questions_without_the_company_name(string $language): void
    {
        $analysis = $this->analysis([
            'language' => $language,
            'location' => 'Oradea',
            'website_inspection' => ['services' => ['Axiontic CRM Platform', 'Custom ERP Systems'], 'city' => 'Axiontic City'],
        ]);

        $questions = app(QuestionGenerator::class)->generate($analysis);

        $this->assertCount(25, $questions);
        $texts = $questions->pluck('question');
        $this->assertSame($texts->count(), $texts->map(fn ($t) => mb_strtolower($t))->unique()->count());
        $this->assertSame(range(1, 25), $questions->pluck('position')->all());
        foreach ($texts as $text) {
            $this->assertStringNotContainsString('axiontic', TextNormalizer::fold($text), $text);
            $this->assertDoesNotMatchRegularExpression('/\{(industry|service|market|city|language)\}/', $text);
        }
        $this->assertSame(25, $analysis->refresh()->query_config['generated_count']);
        $this->assertSame(PromptTemplate::enabled()->count(), count($analysis->query_config['template_ids']));
    }

    #[DataProvider('languages')]
    public function test_every_seeder_template_has_a_native_phrasing(string $language): void
    {
        if ($language === 'en') {
            $this->assertCount(25, PromptTemplateSeeder::TEMPLATES);

            return;
        }

        $seen = [];
        foreach (PromptTemplateSeeder::TEMPLATES as [$name, , $template]) {
            $localized = QuestionGenerator::build($template, $language, 'DE', 'Software', [], 0, ['acme'], 'Köln');
            $english = QuestionGenerator::build($template, 'en', 'DE', 'Software', [], 0, ['acme'], 'Köln');
            $this->assertNotSame($english, $localized, "{$name} has no {$language} phrasing");
            $seen[$localized] = true;
        }
        // Each template has its own phrasing, not a shared intent fallback.
        $this->assertCount(25, $seen);
    }

    public function test_free_check_uses_three_non_audit_templates(): void
    {
        $analysis = $this->analysis(['tier' => AnalysisTier::Check]);

        $questions = app(QuestionGenerator::class)->generate($analysis);

        $this->assertCount(3, $questions);
        $templates = PromptTemplate::query()->whereIn('id', $questions->pluck('prompt_template_id'))->get();
        $this->assertCount(3, $templates);
        $this->assertFalse($templates->contains('is_audit_only', true));
    }

    public function test_free_check_never_falls_back_to_audit_only_templates(): void
    {
        settings()->set('query_count', 10);
        PromptTemplate::query()->where('name', 'Local choice')->update(['is_enabled' => false]);

        $questions = app(QuestionGenerator::class)->generate($this->analysis(['tier' => AnalysisTier::Check]));

        $this->assertCount(2, $questions);
    }

    public function test_audit_count_follows_the_setting(): void
    {
        settings()->set('audit_query_count', 7);

        $this->assertCount(7, app(QuestionGenerator::class)->generate($this->analysis()));
    }

    public function test_audit_cycles_templates_with_service_offsets_when_there_are_too_few(): void
    {
        PromptTemplate::query()->whereNotIn('name', ['Specific service', 'Service need'])->update(['is_enabled' => false]);
        $analysis = $this->analysis([
            'industry' => null,
            'website_inspection' => ['services' => ['Custom ERP Systems', 'Mobile App Development', 'Cloud Hosting Services']],
        ]);

        $texts = app(QuestionGenerator::class)->generate($analysis)->pluck('question');

        $this->assertGreaterThan(2, $texts->count());
        $this->assertLessThanOrEqual(25, $texts->count());
        $this->assertSame($texts->count(), $texts->unique()->count());
        $this->assertStringContainsString('Mobile App Development', $texts->implode(' | '));
    }

    public function test_cycling_stops_when_templates_cannot_produce_new_questions(): void
    {
        PromptTemplate::query()->where('name', '!=', 'Top providers in market')->update(['is_enabled' => false]);

        $this->assertCount(1, app(QuestionGenerator::class)->generate($this->analysis()));
    }

    public function test_questions_keep_their_template_for_categories(): void
    {
        $analysis = $this->analysis(['location' => 'Oradea']);
        app(QuestionGenerator::class)->generate($analysis);

        $categories = $analysis->questions()->with('promptTemplate')->get()->map(fn ($q) => $q->promptTemplate->category->value)->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['top', 'service', 'local', 'compare', 'problem', 'budget', 'alternatives', 'reviews', 'specialist', 'b2b'], $categories);
    }
}
