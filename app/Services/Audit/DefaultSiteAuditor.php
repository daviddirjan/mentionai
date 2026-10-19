<?php

namespace App\Services\Audit;

use App\Contracts\SiteAuditor;
use App\Data\SiteCheck;
use App\Enums\CheckGroup;
use App\Enums\CheckStatus;
use App\Models\Analysis;
use App\Services\Detection\TextNormalizer;
use App\Services\Inspection\FetchResult;
use App\Services\Inspection\SafeFetcher;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;
use Throwable;

/**
 * Technical GEO checks of the analysed host only. Evidence strings are the observed facts, untranslated.
 * Every request goes through SafeFetcher and shares one overall deadline.
 */
class DefaultSiteAuditor implements SiteAuditor
{
    /** robots.txt product token => check key suffix */
    public const AI_BOTS = [
        'GPTBot' => 'gptbot',
        'OAI-SearchBot' => 'oai_searchbot',
        'ClaudeBot' => 'claudebot',
        'PerplexityBot' => 'perplexitybot',
        'Google-Extended' => 'google_extended',
    ];

    /** schema.org types that describe a physical/local business (LocalBusiness and its common subtypes). */
    private const LOCAL_TYPES = [
        'localbusiness', 'professionalservice', 'store', 'restaurant', 'foodestablishment', 'dentist', 'physician',
        'medicalbusiness', 'medicalclinic', 'legalservice', 'attorney', 'notary', 'accountingservice', 'financialservice',
        'homeandconstructionbusiness', 'automotivebusiness', 'autorepair', 'healthandbeautybusiness', 'realestateagent',
        'travelagency', 'lodgingbusiness', 'hotel', 'employmentagency', 'insuranceagency', 'sportsactivitylocation',
        'entertainmentbusiness', 'educationalorganization', 'childcare', 'drycleaningorlaundry', 'emergencyservice',
        'governmentoffice', 'internetcafe', 'library', 'recyclingcenter', 'selfstorage', 'shoppingcenter',
        'touristinformationcenter', 'electrician', 'plumber', 'roofingcontractor', 'generalcontractor', 'hvacbusiness',
        'locksmith', 'movingcompany', 'housepainter', 'beautysalon', 'hairsalon', 'dayspa', 'veterinarycare', 'pharmacy',
        'optician', 'cafeorcoffeeshop', 'bakery', 'barorpub',
    ];

    public function __construct(private readonly SafeFetcher $fetcher) {}

    public function audit(Analysis $analysis): array
    {
        try {
            return $this->run($analysis);
        } catch (Throwable $e) {
            report($e);

            return [new SiteCheck('homepage_status', CheckGroup::Access, CheckStatus::Fail, 'Site audit error')];
        }
    }

    /** @return list<SiteCheck> */
    private function run(Analysis $analysis): array
    {
        $config = config('mentionai.website_inspection');
        // Up to five requests share a budget of two single-request timeouts.
        $deadline = microtime(true) + 2 * (float) $config['timeout_seconds'];

        $home = $this->fetcher->get($analysis->website_url, ['content_types' => ['text/html', 'application/xhtml'], 'deadline' => $deadline]);
        $base = $home->ok() ? $home->finalUrl : $analysis->website_url;
        $parts = parse_url($base) ?: [];
        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower(rtrim($parts['host'] ?? $analysis->domain, '.'));
        $origin = $scheme.'://'.$host;

        $text = ['accept' => 'text/plain,text/markdown,application/xml,text/xml;q=0.9,*/*;q=0.5', 'same_host' => $host, 'deadline' => $deadline];
        $robots = $this->fetcher->get($origin.'/robots.txt', $text);
        $robotsTxt = $this->isTextFile($robots) ? new RobotsTxt((string) $robots->body) : null;
        $llms = $this->fetcher->get($origin.'/llms.txt', $text);

        $doc = $home->ok() ? $this->parse((string) $home->body) : null;

        $checks = [
            $this->homepageStatus($home),
            $this->https($home, $analysis->website_url),
            $this->robotsCheck($robots, $robotsTxt),
            $this->sitemapCheck($origin, $host, $robotsTxt, $doc, $text),
            $this->llmsCheck($llms),
            new SiteCheck('response_time', CheckGroup::Access, CheckStatus::Info, "GET / → {$home->durationMs} ms", ['ms' => $home->durationMs]),
        ];

        foreach (self::AI_BOTS as $bot => $suffix) {
            $checks[] = $this->crawlerCheck($bot, $suffix, $robots, $robotsTxt);
        }

        $unavailable = 'Homepage not available ('.($home->status ?? $home->error).')';
        $structure = $doc === null
            ? array_map(fn (string $key) => new SiteCheck($key, CheckGroup::Structure, CheckStatus::Warn, $unavailable), ['title_tag', 'meta_description', 'h1_count', 'html_lang', 'canonical', 'robots_meta', 'hreflang', 'open_graph'])
            : $this->structureChecks($doc, $home, $host, $analysis->language);
        $entity = $doc === null
            ? array_map(fn (string $key) => new SiteCheck($key, CheckGroup::Entity, CheckStatus::Warn, $unavailable, $key === 'jsonld_present' ? ['types' => ''] : ['location' => $analysis->location]), $analysis->location ? ['jsonld_present', 'jsonld_local', 'jsonld_locality_match'] : ['jsonld_present', 'jsonld_local'])
            : $this->entityChecks($doc, $analysis->location);

        return [...$checks, ...$structure, ...$entity];
    }

    private function homepageStatus(FetchResult $home): SiteCheck
    {
        $evidence = 'GET / → '.($home->status ?? $home->error);
        if ($home->redirects > 0) {
            $evidence .= " ({$home->redirects} redirect".($home->redirects > 1 ? 's' : '').' → '.$home->finalUrl.')';
        }
        if ($home->error === 'content_type') {
            $evidence .= ' ('.$home->contentType().', not HTML)';
        }

        return new SiteCheck('homepage_status', CheckGroup::Access, $home->ok() ? CheckStatus::Pass : CheckStatus::Fail, $evidence);
    }

    private function https(FetchResult $home, string $requested): SiteCheck
    {
        if (! $home->ok()) {
            $https = str_starts_with(strtolower($requested), 'https://');

            return new SiteCheck('https', CheckGroup::Access, CheckStatus::Warn, ($https ? 'https' : 'http').' URL, homepage not reachable ('.($home->status ?? $home->error).')');
        }

        $https = str_starts_with(strtolower($home->finalUrl), 'https://');

        return new SiteCheck('https', CheckGroup::Access, $https ? CheckStatus::Pass : CheckStatus::Fail, 'Final URL '.$home->finalUrl);
    }

    private function robotsCheck(FetchResult $robots, ?RobotsTxt $parsed): SiteCheck
    {
        if ($parsed !== null) {
            return new SiteCheck('robots_txt', CheckGroup::Access, CheckStatus::Pass, 'GET /robots.txt → 200');
        }

        return new SiteCheck('robots_txt', CheckGroup::Access, CheckStatus::Fail, $this->missingEvidence('/robots.txt', $robots));
    }

    /** @param  array<string, mixed>  $options */
    private function sitemapCheck(string $origin, string $host, ?RobotsTxt $robots, ?DOMXPath $doc, array $options): SiteCheck
    {
        $declared = [];
        foreach ($robots?->sitemaps() ?? [] as $url) {
            $declared[] = ['url' => $url, 'where' => 'robots.txt'];
        }
        foreach ($doc?->query('//link[translate(@rel,"SITEMAP","sitemap")="sitemap"]/@href') ?? [] as $href) {
            $declared[] = ['url' => SafeFetcher::absoluteUrl($origin.'/', trim($href->nodeValue)), 'where' => 'HTML'];
        }

        $offHost = null;
        foreach ($declared as $candidate) {
            $candidateHost = strtolower((string) parse_url($candidate['url'], PHP_URL_HOST));
            if ($candidateHost !== $host) {
                $offHost ??= $candidate['url'];

                continue;
            }
            $path = $this->pathOf($candidate['url']);
            $result = $this->fetcher->get($candidate['url'], $options + ['max_bytes' => (int) config('mentionai.website_inspection.max_bytes')]);
            if ($result->error === 'too_large' || ($result->ok() && $this->isSitemap($result))) {
                return new SiteCheck('sitemap_xml', CheckGroup::Access, CheckStatus::Pass, "GET {$path} → ".($result->status ?? 200)." (declared in {$candidate['where']})");
            }

            return new SiteCheck('sitemap_xml', CheckGroup::Access, CheckStatus::Fail, "Declared in {$candidate['where']}, GET {$path} → ".($result->ok() ? $result->status.' (not a sitemap)' : ($result->status ?? $result->error)));
        }

        $result = $this->fetcher->get($origin.'/sitemap.xml', $options);
        $suffix = $offHost !== null ? " (declared sitemap on another host: {$offHost})" : '';
        if ($result->error === 'too_large' || ($result->ok() && $this->isSitemap($result))) {
            return new SiteCheck('sitemap_xml', CheckGroup::Access, CheckStatus::Pass, 'GET /sitemap.xml → '.($result->status ?? 200).$suffix);
        }
        if ($result->ok()) {
            return new SiteCheck('sitemap_xml', CheckGroup::Access, CheckStatus::Warn, 'GET /sitemap.xml → 200 ('.($result->contentType() ?: 'unknown type').', no <urlset>/<sitemapindex>)'.$suffix);
        }

        return new SiteCheck('sitemap_xml', CheckGroup::Access, CheckStatus::Fail, 'GET /sitemap.xml → '.($result->status ?? $result->error).$suffix);
    }

    private function llmsCheck(FetchResult $llms): SiteCheck
    {
        if ($this->isTextFile($llms) && trim((string) $llms->body) !== '') {
            return new SiteCheck('llms_txt', CheckGroup::Access, CheckStatus::Pass, 'GET /llms.txt → 200');
        }

        return new SiteCheck('llms_txt', CheckGroup::Access, CheckStatus::Warn, $this->missingEvidence('/llms.txt', $llms));
    }

    private function crawlerCheck(string $bot, string $suffix, FetchResult $robots, ?RobotsTxt $parsed): SiteCheck
    {
        $key = 'ai_crawler_'.$suffix;
        $params = ['bot' => $bot];

        if ($parsed === null) {
            // RFC 9309: a missing robots.txt (4xx) means no restrictions; an unreachable one (5xx/timeout) means "assume disallowed".
            if ($robots->status !== null && $robots->status >= 400 && $robots->status < 500) {
                return new SiteCheck($key, CheckGroup::AiCrawlers, CheckStatus::Pass, "No robots.txt ({$robots->status}): no rule blocks {$bot}", $params);
            }

            return new SiteCheck($key, CheckGroup::AiCrawlers, CheckStatus::Warn, 'robots.txt not readable ('.($robots->status ?? $robots->error)."): access for {$bot} unknown", $params);
        }

        $verdict = $parsed->evaluate($bot, '/');
        if (! $verdict['allowed']) {
            return new SiteCheck($key, CheckGroup::AiCrawlers, CheckStatus::Fail, "robots.txt User-agent: {$this->agentLabel($verdict['group'], $bot)} → {$verdict['rule']}", $params);
        }

        $evidence = $verdict['rule'] !== null
            ? "robots.txt User-agent: {$this->agentLabel($verdict['group'], $bot)} → {$verdict['rule']}"
            : "No robots.txt rule blocks {$bot}";

        return new SiteCheck($key, CheckGroup::AiCrawlers, CheckStatus::Pass, $evidence, $params);
    }

    /** @return list<SiteCheck> */
    private function structureChecks(DOMXPath $xpath, FetchResult $home, string $host, string $language): array
    {
        $checks = [];

        $title = $this->clean($xpath->query('//title')->item(0)?->textContent ?? '');
        $length = mb_strlen($title);
        $checks[] = new SiteCheck('title_tag', CheckGroup::Structure, match (true) {
            $length === 0 => CheckStatus::Fail,
            $length < 15 || $length > 65 => CheckStatus::Warn,
            default => CheckStatus::Pass,
        }, $length === 0 ? 'No <title>' : "<title> {$length} chars: \"".Str::limit($title, 90).'"', ['length' => $length]);

        $description = $this->clean($this->metaContent($xpath, 'name', 'description') ?? '');
        $length = mb_strlen($description);
        $checks[] = new SiteCheck('meta_description', CheckGroup::Structure, match (true) {
            $length === 0 => CheckStatus::Fail,
            $length < 70 || $length > 160 => CheckStatus::Warn,
            default => CheckStatus::Pass,
        }, $length === 0 ? 'No <meta name="description">' : "<meta name=\"description\"> {$length} chars", ['length' => $length]);

        $h1 = $xpath->query('//h1')->length;
        $checks[] = new SiteCheck('h1_count', CheckGroup::Structure, match (true) {
            $h1 === 0 => CheckStatus::Fail,
            $h1 > 1 => CheckStatus::Warn,
            default => CheckStatus::Pass,
        }, "{$h1} × <h1>", ['count' => $h1]);

        $lang = trim((string) ($xpath->query('/html/@lang')->item(0)?->nodeValue ?? ''));
        if ($lang === '') {
            $checks[] = new SiteCheck('html_lang', CheckGroup::Structure, CheckStatus::Fail, '<html> without lang', ['lang' => null]);
        } else {
            $primary = strtolower(explode('-', str_replace('_', '-', $lang))[0]);
            $matches = $primary === strtolower($language);
            $checks[] = new SiteCheck('html_lang', CheckGroup::Structure, $matches ? CheckStatus::Pass : CheckStatus::Warn,
                "<html lang=\"{$lang}\">".($matches ? '' : ", analysis language {$language}"), ['lang' => $lang]);
        }

        $canonical = trim((string) ($xpath->query('//link[translate(@rel,"CANONICAL","canonical")="canonical"]/@href')->item(0)?->nodeValue ?? ''));
        if ($canonical === '') {
            $checks[] = new SiteCheck('canonical', CheckGroup::Structure, CheckStatus::Warn, 'No <link rel="canonical">');
        } else {
            $absolute = SafeFetcher::absoluteUrl($home->finalUrl, $canonical);
            $sameHost = $this->sameSite(strtolower((string) parse_url($absolute, PHP_URL_HOST)), $host);
            $checks[] = new SiteCheck('canonical', CheckGroup::Structure, $sameHost ? CheckStatus::Pass : CheckStatus::Warn,
                '<link rel="canonical" href="'.Str::limit($canonical, 120).'">'.($sameHost ? '' : ' (another host)'));
        }

        $robotsMeta = strtolower($this->metaContent($xpath, 'name', 'robots') ?? '');
        $header = strtolower($home->header('X-Robots-Tag'));
        if (str_contains($robotsMeta, 'noindex') || str_contains($robotsMeta, 'none')) {
            $checks[] = new SiteCheck('robots_meta', CheckGroup::Structure, CheckStatus::Fail, "<meta name=\"robots\" content=\"{$robotsMeta}\">");
        } elseif (str_contains($header, 'noindex')) {
            $checks[] = new SiteCheck('robots_meta', CheckGroup::Structure, CheckStatus::Fail, "X-Robots-Tag: {$header}");
        } else {
            $checks[] = new SiteCheck('robots_meta', CheckGroup::Structure, CheckStatus::Pass, $robotsMeta !== '' ? "<meta name=\"robots\" content=\"{$robotsMeta}\">" : 'No noindex directive');
        }

        $hreflang = [];
        foreach ($xpath->query('//link[translate(@rel,"ALTERNATE","alternate")="alternate"][@hreflang]/@hreflang') as $attr) {
            $hreflang[strtolower(trim($attr->nodeValue))] = true;
        }
        $checks[] = new SiteCheck('hreflang', CheckGroup::Structure, $hreflang === [] ? CheckStatus::Warn : CheckStatus::Pass,
            $hreflang === [] ? 'No <link rel="alternate" hreflang>' : count($hreflang).' × hreflang ('.Str::limit(implode(', ', array_keys($hreflang)), 80).')');

        $og = [];
        foreach (['og:title', 'og:description', 'og:image'] as $property) {
            $og[$property] = filled($this->metaContent($xpath, 'property', $property));
        }
        $present = array_keys(array_filter($og));
        $missing = array_keys(array_filter($og, fn ($v) => ! $v));
        $checks[] = new SiteCheck('open_graph', CheckGroup::Structure, match (count($present)) {
            3 => CheckStatus::Pass,
            0 => CheckStatus::Fail,
            default => CheckStatus::Warn,
        }, $present === [] ? 'No og:title, og:description, og:image' : implode(', ', $present).' present'.($missing ? '; missing '.implode(', ', $missing) : ''));

        return $checks;
    }

    /** @return list<SiteCheck> */
    private function entityChecks(DOMXPath $xpath, ?string $location): array
    {
        $nodes = $this->jsonLdNodes($xpath);
        $types = array_values(array_unique(array_merge(...array_map(fn ($n) => $n['types'], $nodes ?: [['types' => []]]))));
        $typeList = implode(', ', array_slice($types, 0, 8));

        $checks = [new SiteCheck('jsonld_present', CheckGroup::Entity, $types === [] ? CheckStatus::Fail : CheckStatus::Pass,
            $types === [] ? 'No JSON-LD (application/ld+json)' : "JSON-LD @type {$typeList}", ['types' => $typeList])];

        $local = array_values(array_filter($nodes, fn ($n) => array_intersect(array_map('strtolower', $n['types']), self::LOCAL_TYPES) !== []));
        $localWithAddress = array_values(array_filter($local, fn ($n) => $n['address'] !== null));
        $anyAddress = array_values(array_filter($nodes, fn ($n) => $n['address'] !== null));

        $params = ['location' => $location];
        if ($localWithAddress !== []) {
            $node = $localWithAddress[0];
            $checks[] = new SiteCheck('jsonld_local', CheckGroup::Entity, CheckStatus::Pass, 'JSON-LD @type '.implode(',', $node['types']).' with address'.($node['address']['locality'] ? ' ('.$node['address']['locality'].')' : ''), $params);
        } elseif ($local !== []) {
            $checks[] = new SiteCheck('jsonld_local', CheckGroup::Entity, CheckStatus::Warn, 'JSON-LD @type '.implode(',', $local[0]['types']).', no address', $params);
        } elseif ($types !== []) {
            $checks[] = new SiteCheck('jsonld_local', CheckGroup::Entity, CheckStatus::Warn, "JSON-LD @type {$typeList}, no LocalBusiness".($anyAddress === [] ? ', no addressLocality' : ''), $params);
        } else {
            $checks[] = new SiteCheck('jsonld_local', CheckGroup::Entity, CheckStatus::Fail, 'No LocalBusiness / ProfessionalService JSON-LD', $params);
        }

        if ($location !== null && trim($location) !== '') {
            $wanted = TextNormalizer::fold(trim(explode(',', $location)[0]));
            $found = [];
            $matched = null;
            foreach ($anyAddress as $node) {
                foreach (['locality', 'region'] as $field) {
                    $value = $node['address'][$field];
                    if ($value === null) {
                        continue;
                    }
                    $found[] = $value;
                    $folded = TextNormalizer::fold($value);
                    if ($wanted !== '' && (str_contains($folded, $wanted) || str_contains($wanted, $folded))) {
                        $matched ??= [$field, $value];
                    }
                }
            }
            if ($matched !== null) {
                $field = $matched[0] === 'locality' ? 'addressLocality' : 'addressRegion';
                $checks[] = new SiteCheck('jsonld_locality_match', CheckGroup::Entity, CheckStatus::Pass, "{$field} \"{$matched[1]}\" matches \"{$location}\"", $params);
            } elseif ($found !== []) {
                $checks[] = new SiteCheck('jsonld_locality_match', CheckGroup::Entity, CheckStatus::Warn, 'addressLocality "'.Str::limit(implode(', ', array_unique($found)), 80)."\" does not match \"{$location}\"", $params);
            } else {
                $checks[] = new SiteCheck('jsonld_locality_match', CheckGroup::Entity, CheckStatus::Fail, "No JSON-LD addressLocality for \"{$location}\"", $params);
            }
        }

        return $checks;
    }

    /** @return list<array{types: list<string>, address: ?array{locality: ?string, region: ?string, street: ?string}}> */
    private function jsonLdNodes(DOMXPath $xpath): array
    {
        $out = [];
        $visit = function (mixed $node, int $depth) use (&$visit, &$out): void {
            if (! is_array($node) || $depth > 3 || count($out) >= 30) {
                return;
            }
            if (array_is_list($node)) {
                foreach ($node as $child) {
                    $visit($child, $depth + 1);
                }

                return;
            }
            if (isset($node['@graph'])) {
                $visit($node['@graph'], $depth + 1);
            }
            if (isset($node['@type'])) {
                $types = array_values(array_filter((array) $node['@type'], 'is_string'));
                $types = array_map(fn (string $t) => preg_replace('#^https?://schema\.org/#i', '', $t), $types);
                $out[] = ['types' => $types, 'address' => $this->address($node['address'] ?? $node['location']['address'] ?? null)];
            }
        };

        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode(trim($script->textContent), true);
            $visit($data, 0);
        }

        return $out;
    }

    /** @return ?array{locality: ?string, region: ?string, street: ?string} */
    private function address(mixed $address): ?array
    {
        if (is_array($address) && array_is_list($address)) {
            $address = $address[0] ?? null;
        }
        if (! is_array($address)) {
            return null;
        }
        $get = fn (string $k): ?string => isset($address[$k]) && is_string($address[$k]) && trim($address[$k]) !== '' ? $this->clean($address[$k]) : null;
        $out = ['locality' => $get('addressLocality'), 'region' => $get('addressRegion'), 'street' => $get('streetAddress')];

        return array_filter($out) === [] ? null : $out;
    }

    private function parse(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private function metaContent(DOMXPath $xpath, string $attr, string $name): ?string
    {
        $node = $xpath->query("//meta[translate(@{$attr},'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='{$name}']/@content")->item(0);

        return $node ? $this->clean($node->nodeValue) : null;
    }

    /** A 200 that is an HTML page (a soft 404 or a catch-all route) is not a robots.txt / llms.txt file. */
    private function isTextFile(FetchResult $result): bool
    {
        if (! $result->ok()) {
            return false;
        }
        $type = $result->contentType();
        $start = strtolower(ltrim(substr((string) $result->body, 0, 200)));

        return ! str_contains($type, 'html') && ! str_starts_with($start, '<!doctype') && ! str_starts_with($start, '<html');
    }

    private function isSitemap(FetchResult $result): bool
    {
        $head = strtolower(substr((string) $result->body, 0, 4000));

        return str_contains($head, '<urlset') || str_contains($head, '<sitemapindex');
    }

    private function missingEvidence(string $path, FetchResult $result): string
    {
        if ($result->ok()) {
            return "GET {$path} → 200 (".($result->contentType() ?: 'HTML').' page, not a text file)';
        }

        return "GET {$path} → ".($result->status ?? $result->error);
    }

    private function pathOf(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function agentLabel(?string $group, string $bot): string
    {
        return $group === '*' ? '*' : $bot;
    }

    private function sameSite(string $a, string $b): bool
    {
        return preg_replace('/^www\./', '', $a) === preg_replace('/^www\./', '', $b);
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }
}
