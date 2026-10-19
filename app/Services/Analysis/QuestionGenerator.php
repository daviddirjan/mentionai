<?php

namespace App\Services\Analysis;

use App\Models\Analysis;
use App\Models\AnalysisQuestion;
use App\Models\PromptTemplate;
use App\Services\Detection\TextNormalizer;
use Illuminate\Support\Collection;
use Locale;

/**
 * Deterministic discovery-question generation. No LLM calls.
 * English templates are used as written; other languages use a fixed phrase table keyed by template intent.
 * Product rule: a question never contains the company name, a brand variation or the domain.
 */
class QuestionGenerator
{
    private const GENERIC = [
        'en' => 'businesses', 'ro' => 'companii', 'es' => 'empresas', 'fr' => 'entreprises', 'de' => 'Unternehmen',
        'it' => 'aziende', 'pt' => 'empresas', 'nl' => 'bedrijven', 'pl' => 'firmy',
    ];

    private const PHRASES = [
        'top' => [
            'ro' => 'Recomandă cele mai bune companii de {industry} din {market}.',
            'es' => 'Recomienda las mejores empresas de {industry} en {market}.',
            'fr' => 'Recommande les meilleures entreprises de {industry} en {market}.',
            'de' => 'Empfiehl die besten Unternehmen für {industry} in {market}.',
            'it' => 'Consiglia le migliori aziende di {industry} in {market}.',
            'pt' => 'Recomenda as melhores empresas de {industry} em {market}.',
            'nl' => 'Beveel de beste bedrijven in {industry} in {market} aan.',
            'pl' => 'Poleć najlepsze firmy z branży {industry} w {market}.',
        ],
        'service' => [
            'ro' => 'Ce companii oferă {service} în {market}? Caut un furnizor de încredere.',
            'es' => '¿Qué empresas ofrecen {service} en {market}? Busco un proveedor fiable.',
            'fr' => 'Quelles entreprises proposent {service} en {market} ? Je cherche un prestataire fiable.',
            'de' => 'Welche Unternehmen bieten {service} in {market} an? Ich suche einen zuverlässigen Anbieter.',
            'it' => 'Quali aziende offrono {service} in {market}? Cerco un fornitore affidabile.',
            'pt' => 'Que empresas oferecem {service} em {market}? Procuro um fornecedor de confiança.',
            'nl' => 'Welke bedrijven bieden {service} aan in {market}? Ik zoek een betrouwbare leverancier.',
            'pl' => 'Które firmy oferują {service} w {market}? Szukam rzetelnego dostawcy.',
        ],
        'local' => [
            'ro' => 'Am nevoie de un furnizor de {industry} de încredere în {city}. Ce companii mi-ai sugera?',
            'es' => 'Necesito un proveedor de {industry} de confianza en {city}. ¿Qué empresas me sugieres?',
            'fr' => "J'ai besoin d'un prestataire de {industry} de confiance à {city}. Quelles entreprises me suggères-tu ?",
            'de' => 'Ich brauche einen vertrauenswürdigen Anbieter für {industry} in {city}. Welche Unternehmen würdest du vorschlagen?',
            'it' => 'Ho bisogno di un fornitore di {industry} affidabile a {city}. Quali aziende mi suggerisci?',
            'pt' => 'Preciso de um fornecedor de {industry} de confiança em {city}. Que empresas sugeres?',
            'nl' => 'Ik zoek een betrouwbare aanbieder van {industry} in {city}. Welke bedrijven raad je aan?',
            'pl' => 'Potrzebuję godnego zaufania dostawcy usług {industry} w {city}. Jakie firmy mi polecisz?',
        ],
        'compare' => [
            'ro' => 'Care sunt cele mai respectate companii de {industry} din {market} și cu ce se remarcă?',
            'es' => '¿Cuáles son las empresas de {industry} más reputadas de {market} y por qué destacan?',
            'fr' => 'Quelles sont les entreprises de {industry} les plus réputées en {market} et pour quoi se distinguent-elles ?',
            'de' => 'Welche sind die angesehensten Unternehmen für {industry} in {market} und wofür sind sie bekannt?',
            'it' => 'Quali sono le aziende di {industry} più rispettate in {market} e per cosa sono conosciute?',
            'pt' => 'Quais são as empresas de {industry} mais conceituadas em {market} e pelo que são conhecidas?',
            'nl' => 'Wat zijn de meest gerenommeerde bedrijven in {industry} in {market} en waar staan ze om bekend?',
            'pl' => 'Jakie są najbardziej renomowane firmy z branży {industry} w {market} i z czego słyną?',
        ],
    ];

    private const FALLBACK_TEMPLATE = 'Recommend the best {industry} companies in {market}.';

    /** @return Collection<int, AnalysisQuestion> */
    public function generate(Analysis $analysis): Collection
    {
        $existing = $analysis->questions()->get();
        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $company = $analysis->company()->first();
        $terms = self::brandTerms($analysis->company_name, $analysis->domain, $company?->brand_variations ?? []);
        $inspection = $analysis->website_inspection ?? [];
        $count = max(1, (int) settings('query_count'));

        $templates = PromptTemplate::enabled()->get();
        if ($templates->isEmpty()) {
            $templates = collect([new PromptTemplate(['name' => 'Default', 'template' => self::FALLBACK_TEMPLATE])]);
        }

        $questions = collect();
        $usedIds = [];
        $seen = [];

        foreach ($templates as $template) {
            if ($questions->count() >= $count) {
                break;
            }
            $text = self::build($template->template, $analysis->language, $analysis->market, $analysis->industry, $inspection, $questions->count(), $terms, $analysis->location);
            if (isset($seen[mb_strtolower($text)])) {
                continue;
            }
            $seen[mb_strtolower($text)] = true;

            $questions->push($analysis->questions()->create([
                'prompt_template_id' => $template->exists ? $template->id : null,
                'position' => $questions->count() + 1,
                'question' => $text,
                'template_snapshot' => $template->template,
            ]));
            if ($template->exists) {
                $usedIds[] = $template->id;
            }
        }

        $analysis->update(['query_config' => [
            'query_count' => $count,
            'generated_count' => $questions->count(),
            'template_ids' => $usedIds,
        ]]);

        return $questions;
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @param  list<string>  $terms
     * @param  ?string  $location  visitor-chosen city / county / region; fills {city} ahead of the scraped city
     */
    public static function build(string $template, string $language, string $market, ?string $industry, array $inspection, int $offset, array $terms, ?string $location = null): string
    {
        $language = strtolower($language);
        $pattern = $language === 'en' || ! isset(self::PHRASES['top'][$language])
            ? $template
            : self::PHRASES[self::intent($template)][$language];

        $generic = self::GENERIC[$language] ?? self::GENERIC['en'];
        $country = self::countryName($market, $language);

        $values = [
            'industry' => self::safe($industry, $terms) ?? $generic,
            'market' => $country,
            'language' => Locale::getDisplayLanguage($language, $language) ?: $language,
        ];
        $values['service'] = self::safe($industry, $terms) ?? self::scrapedService($inspection, $offset, $terms) ?? $values['industry'];
        $values['city'] = self::place($location, $country, $terms) ?? self::safe($inspection['city'] ?? null, $terms) ?? $country;

        $question = self::fill($pattern, $values);

        if (self::leaks($question, $terms)) {
            $question = self::strip($question, $terms);
        }
        if (self::leaks($question, $terms) || trim($question) === '') {
            $fallback = $language === 'en' ? self::FALLBACK_TEMPLATE : self::PHRASES['top'][$language];
            $question = self::fill($fallback, ['industry' => $generic, 'market' => $country]);
        }

        return $question;
    }

    /** @param  list<string>  $variations @return list<string> */
    public static function brandTerms(string $name, string $domain, array $variations = []): array
    {
        $domain = strtolower(preg_replace('/^www\./', '', $domain));
        $label = explode('.', $domain)[0] ?? '';
        $candidates = [$name, TextNormalizer::brandName($name), $domain, 'www.'.$domain, $label, ...array_filter($variations, 'is_string')];

        $terms = [];
        foreach ($candidates as $candidate) {
            $folded = trim(TextNormalizer::fold($candidate));
            if (mb_strlen($folded) >= 3) {
                $terms[$folded] = $folded;
            }
        }
        uasort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_values($terms);
    }

    /** @param  list<string>  $terms folded brand terms from brandTerms() */
    /**
     * Scraped headings/nav links are noisy ("tools", "works") and include the company's own product
     * names, which would identify the brand. Only multi-word phrases sharing no stem with the brand qualify.
     *
     * @param  list<string>  $terms
     */
    private static function scrapedService(array $inspection, int $offset, array $terms): ?string
    {
        $stems = array_filter(array_map(fn ($t) => mb_substr(TextNormalizer::fold($t), 0, 5), $terms), fn ($s) => mb_strlen($s) >= 4);

        $services = array_values(array_filter(
            array_map(fn ($s) => is_string($s) ? self::safe($s, $terms) : null, $inspection['services'] ?? []),
            function (?string $service) use ($stems): bool {
                if ($service === null || count(preg_split('/\s+/u', $service)) < 2) {
                    return false;
                }
                foreach (preg_split('/[^\p{L}\p{N}]+/u', TextNormalizer::fold($service), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    foreach ($stems as $stem) {
                        if (str_starts_with($word, $stem)) {
                            return false;
                        }
                    }
                }

                return true;
            },
        ));

        return $services === [] ? null : $services[$offset % count($services)];
    }

    public static function leaks(string $text, array $terms): bool
    {
        $folded = TextNormalizer::fold($text);
        foreach ($terms as $term) {
            if (preg_match(TextNormalizer::boundaryPattern($term), $folded)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $terms */
    public static function strip(string $text, array $terms): string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $core = fn (string $w): string => TextNormalizer::fold(preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $w));

        foreach ($terms as $term) {
            $termWords = explode(' ', $term);
            $n = count($termWords);
            for ($i = 0; $i + $n <= count($words); $i++) {
                $slice = array_map($core, array_slice($words, $i, $n));
                if ($slice === $termWords) {
                    $tail = preg_match('/[?!.,;:]+$/u', $words[$i + $n - 1], $m) ? $m[0] : '';
                    array_splice($words, $i, $n);
                    if ($tail !== '' && $i > 0) {
                        $words[$i - 1] = rtrim($words[$i - 1], '?!.,;:').$tail;
                    }
                    $i--;
                }
            }
        }

        return trim(implode(' ', $words));
    }

    /**
     * "Cluj" alone is ambiguous to a search engine, so the country is appended unless the visitor already wrote it.
     *
     * @param  list<string>  $terms
     */
    private static function place(?string $location, string $country, array $terms): ?string
    {
        $location = self::safe($location, $terms);
        if ($location === null) {
            return null;
        }

        return str_contains(TextNormalizer::fold($location), TextNormalizer::fold($country)) ? $location : "{$location}, {$country}";
    }

    private static function safe(?string $value, array $terms): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
        if ($value === '') {
            return null;
        }
        $value = mb_substr($value, 0, 80);
        if (self::leaks($value, $terms)) {
            $value = trim(self::strip($value, $terms), " \t-–—,;:|");
            if ($value === '' || self::leaks($value, $terms)) {
                return null;
            }
        }

        return $value;
    }

    /** @param  array<string, string>  $values */
    private static function fill(string $pattern, array $values): string
    {
        $out = preg_replace_callback('/\{(industry|service|market|city|language)\}/', fn ($m) => $values[$m[1]] ?? '', $pattern);

        return trim(preg_replace('/\s+/u', ' ', $out));
    }

    private static function intent(string $template): string
    {
        return match (true) {
            str_contains($template, '{service}') => 'service',
            str_contains($template, '{city}') => 'local',
            (bool) preg_match('/reputab|known for|compar/i', $template) => 'compare',
            default => 'top',
        };
    }

    private static function countryName(string $market, string $language): string
    {
        $market = strtoupper($market);
        $name = Locale::getDisplayRegion('-'.$market, $language);

        return $name !== '' && $name !== $market ? $name : $market;
    }
}
