<?php

namespace Tests\Unit\Backend;

use App\Services\Detection\CompetitorExtractor;
use Tests\TestCase;

class CompetitorExtractorTest extends TestCase
{
    private function response(string $provider, string $text, array $citations = []): object
    {
        return (object) ['provider' => $provider, 'response_text' => $text, 'citations' => $citations];
    }

    /** @return array<string, array> keyed by normalized name */
    private function extract(array $responses, string $name = 'Axiontic SRL', string $domain = 'axiontic.ro', array $variations = []): array
    {
        $out = [];
        foreach ((new CompetitorExtractor)->extract($responses, $name, $domain, $variations) as $row) {
            $out[$row['normalized_name']] = $row;
        }

        return $out;
    }

    public function test_extracts_bold_list_and_heading_names(): void
    {
        $text = "Here are some options:\n\n1. **Endava** — large delivery centers.\n2. Softvision: product engineering\n- Fortech (outsourcing)\n\n## Bitdefender\nSecurity.\n\nThese are my top picks.";

        $found = $this->extract([$this->response('openai', $text)]);

        $this->assertEqualsCanonicalizing(['endava', 'softvision', 'fortech', 'bitdefender'], array_keys($found));
        $this->assertSame('Endava', $found['endava']['name']);
    }

    public function test_excludes_target_brand_providers_and_generic_words(): void
    {
        $text = "1. **Axiontic SRL** — us\n2. **Axiontic** — us again\n3. **ChatGPT** — an assistant\n4. **Pros** — x\n5. **Key Features** — y\n6. **Why choose a local firm?** — z\n7. **Endava** — real\n8. **Google** — search";

        $found = $this->extract([$this->response('gemini', $text)]);

        $this->assertSame(['endava'], array_keys($found));
    }

    public function test_excludes_brand_variations_and_domain_label(): void
    {
        $text = "1. **AXN Labs** — us\n2. **Axiontic.ro** — us\n3. **Endava** — other";

        $found = $this->extract([$this->response('openai', $text)], variations: ['AXN Labs']);

        $this->assertSame(['endava'], array_keys($found));
    }

    public function test_dedupes_across_case_diacritics_and_legal_suffixes_and_counts_frequency(): void
    {
        $responses = [
            $this->response('openai', "1. **Endava SRL** — a\n2. **Softvision** — b"),
            $this->response('gemini', "1. **ENDAVA** — a\n2. **Fortech** — c"),
            $this->response('anthropic', "- Endava Ltd.\n- Șoftvision\n"),
            $this->response('openai', "1. **Endava** — second question\n"),
        ];

        $found = $this->extract($responses);

        $this->assertSame(4, $found['endava']['mention_count']);
        $this->assertEqualsCanonicalizing(['openai', 'gemini', 'anthropic'], $found['endava']['providers']);
        $this->assertSame(2, $found['softvision']['mention_count']);
        $this->assertSame(1, $found['fortech']['mention_count']);
        $this->assertCount(3, $found);
        $this->assertSame('endava', array_key_first($found)); // ordered by frequency
    }

    public function test_a_name_repeated_inside_one_response_counts_once(): void
    {
        $found = $this->extract([$this->response('openai', "1. **Endava** — a\n\n## Endava\nMore text about **Endava**.")]);

        $this->assertSame(1, $found['endava']['mention_count']);
    }

    public function test_citation_domains_enrich_names_and_add_new_competitors(): void
    {
        $responses = [$this->response('perplexity', "1. **Endava** — a", [
            ['url' => 'https://www.endava.com/en', 'title' => 'Endava'],
            ['url' => 'https://fortech.ro/services', 'title' => null],
            ['url' => 'https://axiontic.ro/', 'title' => 'Us'],
            ['url' => 'https://en.wikipedia.org/wiki/X', 'title' => 'Wiki'],
            ['url' => 'https://clutch.co/profile', 'title' => 'Clutch'],
        ])];

        $found = $this->extract($responses);

        $this->assertSame('endava.com', $found['endava']['domain']);
        $this->assertSame(1, $found['endava']['mention_count']);
        $this->assertSame('fortech.ro', $found['fortech']['domain']);
        $this->assertArrayNotHasKey('axiontic', $found);
        $this->assertArrayNotHasKey('wikipedia', $found);
        $this->assertArrayNotHasKey('clutch', $found);
    }

    public function test_markdown_links_and_citation_markers_are_cleaned(): void
    {
        $found = $this->extract([$this->response('openai', "1. [Endava](https://endava.com) [1] — delivery\n2. **Softvision**[2] — product")]);

        $this->assertEqualsCanonicalizing(['endava', 'softvision'], array_keys($found));
    }

    public function test_plain_sentences_and_lowercase_items_are_not_companies(): void
    {
        $text = "I could not find reliable data.\n- some generic advice about budgeting\n- check reviews carefully\nContact them directly.";

        $this->assertSame([], $this->extract([$this->response('openai', $text)]));
    }

    public function test_names_ending_in_romanian_diacritics_survive_quote_trimming(): void
    {
        $text = "1. **Clinica Dentară Bucureș** — x\n2. „Atelier Mureș”: y\n3. **Endava** — z";

        $found = $this->extract([$this->response('gemini', $text)]);

        $this->assertArrayHasKey('endava', $found);
        $names = array_column($found, 'name');
        $this->assertContains('Clinica Dentară Bucureș', $names);
        foreach ($names as $name) {
            $this->assertTrue(mb_check_encoding($name, 'UTF-8'));
        }
    }
}
