<?php

namespace Tests\Feature\Admin;

use App\Contracts\AnalysisMaintenance;
use App\Filament\Resources\Analyses\AnalysisResource;
use App\Filament\Resources\Analyses\Pages\ListAnalyses;
use App\Filament\Resources\Analyses\Pages\ViewAnalysis;
use App\Models\Analysis;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Mockery\MockInterface;

class AnalysisResourceTest extends AdminTestCase
{
    public function test_list_and_view_pages_load(): void
    {
        $analysis = Analysis::factory()->completed()->demo()->create();
        Analysis::factory()->failed()->create();

        $this->get(AnalysisResource::getUrl('index'))->assertOk()->assertSee($analysis->company_name);
        $this->get(AnalysisResource::getUrl('view', ['record' => $analysis]))
            ->assertOk()
            ->assertSee('Endava')
            ->assertSee('DEMO');
    }

    public function test_list_filters_and_exports(): void
    {
        $demo = Analysis::factory()->completed()->demo()->create();
        $live = Analysis::factory()->completed()->create();

        Livewire::test(ListAnalyses::class)
            ->assertCanSeeTableRecords([$demo, $live])
            ->filterTable('is_demo', true)
            ->assertCanSeeTableRecords([$demo])
            ->assertCanNotSeeTableRecords([$live])
            ->resetTableFilters()
            ->filterTable('provider', 'gemini')
            ->assertCanSeeTableRecords([$demo, $live])
            ->callAction(TestAction::make('exportCsv')->table())
            ->assertFileDownloaded();
    }

    public function test_retry_and_recalculate_use_the_maintenance_contract(): void
    {
        $analysis = Analysis::factory()->completed()->create();

        $this->mock(AnalysisMaintenance::class, function (MockInterface $mock) use ($analysis) {
            $mock->shouldReceive('retryFailed')->once()->withArgs(fn ($a) => $a->is($analysis))->andReturn(1);
            $mock->shouldReceive('recalculate')->once()->withArgs(fn ($a) => $a->is($analysis))->andReturn($analysis);
        });

        Livewire::test(ViewAnalysis::class, ['record' => $analysis->getRouteKey()])
            ->callAction('retryFailed')
            ->assertNotified('1 failed request(s) re-queued')
            ->callAction('recalculate')
            ->assertNotified('Score recalculated');
    }

    public function test_delete_cascades(): void
    {
        $analysis = Analysis::factory()->completed()->create();

        Livewire::test(ViewAnalysis::class, ['record' => $analysis->getRouteKey()])
            ->callAction('delete')
            ->assertRedirect(AnalysisResource::getUrl('index'));

        $this->assertDatabaseMissing('analyses', ['id' => $analysis->id]);
        $this->assertDatabaseCount('ai_responses', 0);
    }
}
