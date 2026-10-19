<?php

namespace Database\Seeders;

use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

/**
 * Discovery templates. Idempotent: creates missing templates by name and fills a missing category,
 * never overwrites admin edits. The first three (not audit-only) are the free check; an audit uses all enabled.
 * Every template here needs a matching entry in QuestionGenerator::PHRASES for non-English analyses.
 */
class PromptTemplateSeeder extends Seeder
{
    public const TEMPLATES = [
        // [name, category, template, enabled, audit only]
        ['Top providers in market', 'top', 'Recommend the best {industry} companies in {market}.', true, false],
        ['Specific service', 'service', 'Which companies offer {service} in {market}? I am looking for a reliable provider.', true, false],
        ['Local choice', 'local', 'I need a trustworthy {industry} provider in {city}. Which companies would you suggest?', true, false],
        ['Comparison', 'compare', 'What are the most reputable {industry} businesses in {market} and what are they known for?', true, true],
        ['Top 5 right now', 'top', 'Which are the top 5 {industry} companies in {market} right now?', true, true],
        ['Leading providers', 'top', 'Who are the leading {industry} providers in {market}?', true, true],
        ['Single pick in city', 'top', 'If you had to pick one {industry} company in {city}, which would it be and why?', true, true],
        ['Service need', 'service', 'I need {service}. Which companies in {market} do you recommend?', true, true],
        ['Service in city', 'service', 'Which companies are best for {service} in {city}?', true, true],
        ['Based in city', 'local', 'Which {industry} companies are based in {city}?', true, true],
        ['Local business hiring', 'local', 'I run a business in {city}. Who should I hire for {service}?', true, true],
        ['Near city', 'local', 'Recommend {industry} companies near {city}.', true, true],
        ['Project need', 'problem', 'I have a project that needs {service}. Which reliable companies in {market} could handle it?', true, true],
        ['On time and budget', 'problem', 'Which {industry} companies in {market} are known for delivering on time and on budget?', true, true],
        ['Value for money', 'budget', 'Which affordable {industry} companies in {market} offer good value for money?', true, true],
        ['Limited budget', 'budget', 'I have a limited budget for {service}. Which providers in {market} would you recommend?', true, true],
        ['Alternatives to big names', 'alternatives', 'What are good alternatives to the big {industry} companies in {market}?', true, true],
        ['Smaller companies', 'alternatives', 'Besides the best-known names, which smaller {industry} companies in {market} are worth considering?', true, true],
        ['Best reviews', 'reviews', 'Which {industry} companies in {market} have the best client reviews?', true, true],
        ['Most trusted in city', 'reviews', 'Which {industry} companies in {city} are most trusted by their clients?', true, true],
        ['Specialists', 'specialist', 'Which companies in {market} specialize in {service}?', true, true],
        ['Experts in city', 'specialist', 'Who are the experts in {service} in {city}?', true, true],
        ['Long-term partner', 'b2b', 'We are a mid-sized company in {market} looking for a long-term {industry} partner. Who would you recommend?', true, true],
        ['Startups and SMEs', 'b2b', 'Which {industry} companies in {market} work with startups and SMEs?', true, true],
        ['Enterprise clients', 'b2b', 'Which {industry} companies in {market} have experience with enterprise clients?', true, true],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $i => [$name, $category, $template, $enabled, $auditOnly]) {
            $existing = PromptTemplate::query()->where('name', $name)->first();
            if ($existing !== null) {
                if ($existing->category === null) {
                    $existing->update(['category' => $category, 'is_audit_only' => $auditOnly] + ($auditOnly ? ['is_enabled' => true] : []));
                }

                continue;
            }

            PromptTemplate::create([
                'name' => $name,
                'category' => $category,
                'template' => $template,
                'is_enabled' => $enabled,
                'is_audit_only' => $auditOnly,
                'sort_order' => $i,
            ]);
        }
    }
}
