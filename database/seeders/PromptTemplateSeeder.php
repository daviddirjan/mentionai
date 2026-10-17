<?php

namespace Database\Seeders;

use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

class PromptTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (PromptTemplate::exists()) {
            return;
        }

        $templates = [
            ['Top providers in market', 'Recommend the best {industry} companies in {market}.'],
            ['Specific service', 'Which companies offer {service} in {market}? I am looking for a reliable provider.'],
            ['Local choice', 'I need a trustworthy {industry} provider in {city}. Which companies would you suggest?'],
            ['Comparison', 'What are the most reputable {industry} businesses in {market} and what are they known for?'],
        ];

        foreach ($templates as $i => [$name, $template]) {
            PromptTemplate::create([
                'name' => $name,
                'template' => $template,
                'is_enabled' => $i < 3,
                'sort_order' => $i,
            ]);
        }
    }
}
