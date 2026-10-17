<?php

namespace Tests\Unit\Backend;

use App\Enums\MentionClassification as C;
use App\Services\Detection\BrandDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BrandDetectorTest extends TestCase
{
    /** @return array<string, array{0: string, 1: array, 2: string, 3: string, 4: array, 5: C}> */
    public static function cases(): array
    {
        $n = 'Axiontic SRL';
        $d = 'axiontic.ro';

        return [
            'list item is a recommendation' => ["Options:\n1. Endava\n2. Axiontic — custom ERP\n3. Fortech", [], $n, $d, [], C::Recommended],
            'bold name is a recommendation' => ['For ERP work, **Axiontic** is a solid pick.', [], $n, $d, [], C::Recommended],
            'heading is a recommendation' => ["## Axiontic\nBuilds software.", [], $n, $d, [], C::Recommended],
            'recommend phrasing in sentence' => ['I would recommend Axiontic for custom software.', [], $n, $d, [], C::Recommended],
            'consider phrasing' => ['You could consider Axiontic if you need ERP.', [], $n, $d, [], C::Recommended],
            'romanian phrasing' => ['Vă recomandăm Axiontic pentru dezvoltare software.', [], $n, $d, [], C::Recommended],
            'plain mention' => ['Axiontic is a company based in Timisoara that was founded in 2019.', [], $n, $d, [], C::Mentioned],
            'negated recommendation is only a mention' => ['I do not recommend Axiontic because there is little public information.', [], $n, $d, [], C::Mentioned],
            'legal suffix stripped' => ['Axiontic is mentioned in some directories.', [], 'Axiontic S.R.L.', $d, [], C::Mentioned],
            'diacritics and case folded' => ['We recommend ȘOSEAUA VERDE for roofing.', [], 'Soseaua Verde', 'soseaua.ro', [], C::Recommended],
            'brand variation' => ['AXN is known in the region.', [], $n, $d, ['AXN'], C::Mentioned],
            'domain in text' => ['Visit www.axiontic.ro for details.', [], 'Totally Different Name', $d, [], C::Mentioned],
            'domain in citations only' => ['Endava and Fortech are common choices.', [['url' => 'https://www.axiontic.ro/services', 'title' => 'Services']], $n, $d, [], C::CitedOnly],
            'subdomain citation' => ['Some text.', [['url' => 'https://blog.axiontic.ro/x']], $n, $d, [], C::CitedOnly],
            'redirect citation titled with domain' => ['Some text.', [['url' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/abc', 'title' => 'axiontic.ro']], $n, $d, [], C::CitedOnly],
            'name in text beats citation' => ['**Axiontic** builds ERP.', [['url' => 'https://axiontic.ro']], $n, $d, [], C::Recommended],
            'no mention' => ['Endava, Fortech and Softvision are common choices.', [['url' => 'https://clutch.co/x']], $n, $d, [], C::NotMentioned],
            'empty text' => ['', [], $n, $d, [], C::NotMentioned],
            'similar name Axion is not Axiontic' => ["1. **Axiontic Labs** — consultancy\n2. Endava", [], 'Axion', 'axion.io', [], C::Uncertain],
            'prefix of brand in text is uncertain' => ['Axion Software is a popular choice.', [], 'Axiontic', 'axiontic.ro', [], C::Uncertain],
            'longer substring is not a match' => ['Axiontically speaking, nothing here.', [], 'Axiontic', 'axiontic.ro', [], C::Uncertain],
            'first word only of multiword brand' => ['Zentrix Corporation is a conglomerate.', [], 'Zentrix Plumbing Services', 'zentrixplumbing.ro', [], C::Uncertain],
            'common-word brand in lowercase prose' => ['Choose a smart approach to budgeting.', [], 'Smart', 'smart.ro', [], C::Uncertain],
            'common-word brand capitalized counts' => ['**Smart** offers consulting.', [], 'Smart', 'smart.ro', [], C::Recommended],
            'word boundary respected' => ['The Axiontics family tree is large.', [], 'Axiontic', 'axiontic.ro', [], C::Uncertain],
            'unrelated short word' => ['One of the pioneers in the field.', [], 'Pio', 'pio.ro', [], C::NotMentioned],
        ];
    }

    #[DataProvider('cases')]
    public function test_classification(string $text, array $citations, string $name, string $domain, array $variations, C $expected): void
    {
        $result = (new BrandDetector)->detect($text, $citations, $name, $domain, $variations);

        $this->assertSame($expected, $result->classification);
    }

    public function test_uncertain_is_never_counted_as_a_recommendation(): void
    {
        $result = (new BrandDetector)->detect('Axion Software is a popular choice.', [], 'Axiontic', 'axiontic.ro');

        $this->assertFalse($result->classification->countsAsRecommendation());
    }

    public function test_evidence_snippets_are_saved_and_bounded(): void
    {
        $text = str_repeat('Lorem ipsum dolor sit amet. ', 30).'We suggest Axiontic for ERP. '.str_repeat('Consectetur adipiscing elit. ', 30);

        $result = (new BrandDetector)->detect($text, [['url' => 'https://axiontic.ro/x']], 'Axiontic', 'axiontic.ro');

        $this->assertSame(C::Recommended, $result->classification);
        $this->assertSame(['Axiontic'], array_values(array_unique(array_filter($result->matchedTerms, fn ($t) => $t === 'Axiontic'))));
        $name = collect($result->evidence)->firstWhere('type', 'name');
        $this->assertStringContainsString('Axiontic', $name['snippet']);
        $this->assertLessThanOrEqual(260, mb_strlen($name['snippet']));
        $this->assertNotNull(collect($result->evidence)->firstWhere('type', 'citation'));
    }
}
