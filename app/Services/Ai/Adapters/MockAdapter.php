<?php

namespace App\Services\Ai\Adapters;

use App\Contracts\AiProviderAdapter;
use App\Data\ConnectionTestResult;
use App\Data\ProviderQuery;
use App\Data\ProviderResult;
use App\Models\AiProvider;
use Illuminate\Support\Str;

/**
 * Deterministic DEMO adapter. Never touches the network. Output depends only on (provider slug, question),
 * plus the brand when the pipeline hands it over via forBrand() so every classification gets exercised.
 * The brand is never part of the question and is never sent to a real provider.
 */
class MockAdapter implements AiProviderAdapter
{
    private const COMPETITORS = [
        'Lumora Labs', 'Brightforge', 'Veltrix Solutions', 'Cobalt Harbor', 'Nimbus Craft', 'Quantora',
        'Stellaris Group', 'Kestrel Works', 'Orbitera', 'Pinecrest Systems', 'Zenvora', 'Atlas Meridian',
    ];

    private const BLURBS = [
        'known for reliable delivery and clear communication',
        'a well-established team with strong client references',
        'popular with small and mid-sized businesses',
        'focused on measurable results and long-term support',
    ];

    private ?string $brandName = null;

    private ?string $brandDomain = null;

    public function forBrand(string $name, string $domain): static
    {
        $clone = clone $this;
        $clone->brandName = $name;
        $clone->brandDomain = $domain;

        return $clone;
    }

    public function query(AiProvider $provider, ProviderQuery $query): ProviderResult
    {
        $slug = $provider->slug->value;
        $seed = hexdec(substr(hash('sha256', $slug.'|'.$query->question), 0, 12));

        $names = [];
        for ($i = 0; count($names) < 4; $i++) {
            $name = self::COMPETITORS[($seed + $i * 5) % count(self::COMPETITORS)];
            $names[$name] = $name;
        }
        $names = array_values($names);

        $variant = intdiv($seed, 7) % 10;
        $items = array_map(fn (string $n, int $i) => ['name' => $n, 'blurb' => self::BLURBS[($seed + $i) % count(self::BLURBS)]], $names, array_keys($names));

        $note = '';
        $citations = array_map(fn (string $n) => ['url' => 'https://'.Str::slug($n).'.example/', 'title' => $n], array_slice($names, 0, 2));

        if ($this->brandName !== null) {
            if ($variant <= 2) {
                array_splice($items, $variant, 0, [['name' => $this->brandName, 'blurb' => 'a strong option that clients often praise']]);
                $items = array_slice($items, 0, 5);
                $citations[] = ['url' => 'https://'.$this->brandDomain.'/', 'title' => $this->brandName];
            } elseif ($variant === 3) {
                $note = "\n\n{$this->brandName} is also active in this field, though I could not verify details about its services.";
            } elseif ($variant === 4) {
                $citations[] = ['url' => 'https://'.$this->brandDomain.'/', 'title' => null];
            }
        }

        $lines = ["Here are some options that come up for this question (demo data):", ''];
        foreach ($items as $i => $item) {
            $lines[] = ($i + 1).". **{$item['name']}** — {$item['blurb']}.";
        }
        $text = implode("\n", $lines).$note;

        return new ProviderResult(
            provider: $slug,
            model: 'mock-'.$slug,
            successful: true,
            text: $text,
            citations: $citations,
            raw: ['mock' => true, 'text' => $text],
            durationMs: 5,
            inputTokens: 0,
            outputTokens: 0,
            searchCalls: 0,
            isDemo: true,
        );
    }

    public function testConnection(AiProvider $provider): ConnectionTestResult
    {
        $provider->forceFill([
            'last_test_status' => 'ok',
            'last_test_message' => 'Mock mode: no real API call was made.',
            'last_tested_at' => now(),
        ])->save();

        return new ConnectionTestResult(true, 'Mock mode: no real API call was made.', 0);
    }
}
