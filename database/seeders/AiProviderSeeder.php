<?php

namespace Database\Seeders;

use App\Enums\ProviderSlug;
use App\Models\AiProvider;
use Illuminate\Database\Seeder;

/** Idempotent: creates missing providers, never overwrites admin-edited settings. */
class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ProviderSlug::OpenAI->value => ['name' => 'OpenAI', 'max_search_calls' => 1],
            ProviderSlug::Gemini->value => ['name' => 'Google Gemini', 'max_search_calls' => null],
            ProviderSlug::Anthropic->value => ['name' => 'Anthropic Claude', 'max_search_calls' => 1],
            ProviderSlug::Perplexity->value => ['name' => 'Perplexity', 'max_search_calls' => null],
        ];

        foreach (ProviderSlug::cases() as $order => $slug) {
            AiProvider::firstOrCreate(['slug' => $slug->value], [
                'name' => $providers[$slug->value]['name'],
                'display_name' => $slug->displayName(),
                'model' => config("mentionai.providers.{$slug->value}.default_model"),
                'max_search_calls' => $providers[$slug->value]['max_search_calls'],
                'sort_order' => $order,
            ]);
        }
    }
}
