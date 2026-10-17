<?php

namespace Tests\Feature\Admin;

use App\Contracts\AiProviderAdapter;
use App\Contracts\ProviderRegistry;
use App\Data\ConnectionTestResult;
use App\Enums\ProviderSlug;
use App\Filament\Resources\AiProviders\AiProviderResource;
use App\Filament\Resources\AiProviders\Pages\EditAiProvider;
use App\Filament\Resources\AiProviders\Pages\ListAiProviders;
use App\Models\AiProvider;
use Database\Seeders\AiProviderSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Mockery\MockInterface;

class AiProviderResourceTest extends AdminTestCase
{
    private AiProvider $openai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AiProviderSeeder::class);
        $this->openai = AiProvider::firstWhere('slug', ProviderSlug::OpenAI->value);
    }

    public function test_pages_load_and_show_ai_mode(): void
    {
        config(['mentionai.ai_mode' => 'mock']);

        $this->get(AiProviderResource::getUrl('index'))->assertOk()->assertSee('ChatGPT')->assertSee('MOCK');
        $this->get(AiProviderResource::getUrl('edit', ['record' => $this->openai]))
            ->assertOk()
            ->assertSee('USD per 1M tokens')
            ->assertSee('USD per 1K searches');
    }

    public function test_api_key_is_never_rendered(): void
    {
        config(['mentionai.providers.openai.api_key' => 'sk-super-secret-key-12345']);
        $this->assertTrue($this->openai->hasApiKey());

        $this->get(AiProviderResource::getUrl('index'))->assertOk()->assertDontSee('sk-super-secret-key-12345');
        $this->get(AiProviderResource::getUrl('edit', ['record' => $this->openai]))->assertOk()->assertDontSee('sk-super-secret-key-12345');
        Livewire::test(ListAiProviders::class)->assertDontSee('sk-super-secret-key-12345');
    }

    public function test_provider_settings_can_be_edited(): void
    {
        Livewire::test(EditAiProvider::class, ['record' => $this->openai->getKey()])
            ->fillForm([
                'model' => 'gpt-custom',
                'timeout_seconds' => 45,
                'max_output_tokens' => 900,
                'max_search_calls' => 2,
                'input_price_per_million' => 1.5,
                'output_price_per_million' => 6,
                'search_price_per_thousand' => 25,
                'is_enabled' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->openai->refresh();
        $this->assertSame('gpt-custom', $this->openai->model);
        $this->assertSame(45, $this->openai->timeout_seconds);
        $this->assertFalse($this->openai->is_enabled);
        $this->assertSame(1.5, (float) $this->openai->input_price_per_million);
    }

    public function test_model_is_required(): void
    {
        Livewire::test(EditAiProvider::class, ['record' => $this->openai->getKey()])
            ->fillForm(['model' => ''])
            ->call('save')
            ->assertHasFormErrors(['model' => 'required']);
    }

    public function test_test_connection_displays_the_result(): void
    {
        $this->mock(ProviderRegistry::class, function (MockInterface $registry) {
            $adapter = $this->mock(AiProviderAdapter::class, function (MockInterface $adapter) {
                $adapter->shouldReceive('testConnection')->once()->andReturn(new ConnectionTestResult(true, 'Pong from fake adapter', 123));
            });
            $registry->shouldReceive('adapterFor')->once()->andReturn($adapter);
        });

        Livewire::test(ListAiProviders::class)
            ->callAction(TestAction::make('testConnection')->table($this->openai))
            ->assertNotified('Connection OK');
    }

    public function test_failed_test_connection_is_reported(): void
    {
        $this->mock(ProviderRegistry::class, function (MockInterface $registry) {
            $adapter = $this->mock(AiProviderAdapter::class, function (MockInterface $adapter) {
                $adapter->shouldReceive('testConnection')->once()->andReturn(new ConnectionTestResult(false, 'Missing credentials', 0));
            });
            $registry->shouldReceive('adapterFor')->once()->andReturn($adapter);
        });

        Livewire::test(EditAiProvider::class, ['record' => $this->openai->getKey()])
            ->callAction('testConnection')
            ->assertNotified('Connection failed');
    }

    public function test_providers_cannot_be_created_or_deleted(): void
    {
        $this->assertFalse(AiProviderResource::canCreate());
        $this->assertFalse(AiProviderResource::canDelete($this->openai));
    }
}
