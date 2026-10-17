<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\PromptTemplates\Pages\CreatePromptTemplate;
use App\Filament\Resources\PromptTemplates\Pages\EditPromptTemplate;
use App\Filament\Resources\PromptTemplates\Pages\ListPromptTemplates;
use App\Filament\Resources\PromptTemplates\PromptTemplateResource;
use App\Models\Analysis;
use App\Models\PromptTemplate;
use Livewire\Livewire;

class PromptTemplateResourceTest extends AdminTestCase
{
    public function test_pages_load(): void
    {
        $template = PromptTemplate::create(['name' => 'Best', 'template' => 'Best {service} in {market}?', 'sort_order' => 1]);

        $this->get(PromptTemplateResource::getUrl('index'))->assertOk()->assertSee('Best');
        $this->get(PromptTemplateResource::getUrl('create'))->assertOk()->assertSee('{industry}');
        $this->get(PromptTemplateResource::getUrl('edit', ['record' => $template]))->assertOk();
    }

    public function test_template_with_company_placeholder_is_rejected(): void
    {
        Livewire::test(CreatePromptTemplate::class)
            ->fillForm(['name' => 'Bad', 'template' => 'Is {Company} good for {service}?', 'sort_order' => 1])
            ->call('create')
            ->assertHasFormErrors(['template']);

        $this->assertDatabaseCount('prompt_templates', 0);
    }

    public function test_valid_template_is_created_and_edited(): void
    {
        Livewire::test(CreatePromptTemplate::class)
            ->fillForm(['name' => 'Good', 'template' => 'Best {service} in {city}, {market}?', 'sort_order' => 3, 'is_enabled' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = PromptTemplate::firstWhere('name', 'Good');
        $this->assertNotNull($template);

        Livewire::test(EditPromptTemplate::class, ['record' => $template->getKey()])
            ->fillForm(['template' => 'Top {service} providers in {market}?', 'is_enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($template->refresh()->is_enabled);
    }

    public function test_editing_never_alters_question_history(): void
    {
        $template = PromptTemplate::create(['name' => 'T', 'template' => 'Best {service}?', 'sort_order' => 1]);
        $analysis = Analysis::factory()->create();
        $question = $analysis->questions()->create([
            'prompt_template_id' => $template->id,
            'position' => 1,
            'question' => 'Best accounting?',
            'template_snapshot' => 'Best {service}?',
        ]);

        Livewire::test(EditPromptTemplate::class, ['record' => $template->getKey()])
            ->fillForm(['template' => 'Changed {service}?'])
            ->call('save');

        $this->assertSame('Best {service}?', $question->refresh()->template_snapshot);
        $this->assertSame('Best accounting?', $question->question);
    }

    public function test_usage_count_is_listed(): void
    {
        $template = PromptTemplate::create(['name' => 'T', 'template' => 'Best {service}?', 'sort_order' => 1]);
        $analysis = Analysis::factory()->create();
        $analysis->questions()->create(['prompt_template_id' => $template->id, 'position' => 1, 'question' => 'Q?', 'template_snapshot' => 'Best {service}?']);

        Livewire::test(ListPromptTemplates::class)
            ->assertCanSeeTableRecords([$template])
            ->assertTableColumnStateSet('questions_count', 1, $template);
    }
}
