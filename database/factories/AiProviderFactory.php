<?php

namespace Database\Factories;

use App\Enums\ProviderSlug;
use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->randomElement(ProviderSlug::cases());

        return [
            'slug' => $slug,
            'name' => $slug->displayName(),
            'display_name' => $slug->displayName(),
            'is_enabled' => true,
            'model' => 'test-model',
            'input_price_per_million' => 0.25,
            'output_price_per_million' => 2.00,
            'search_price_per_thousand' => 10.00,
        ];
    }

    public function slug(ProviderSlug $slug): static
    {
        return $this->state(fn () => ['slug' => $slug, 'name' => $slug->displayName(), 'display_name' => $slug->displayName()]);
    }
}
