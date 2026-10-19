<?php

namespace App\Services\Audit;

/**
 * Minimal RFC 9309 robots.txt evaluator: groups of consecutive user-agent lines, the most specific matching group
 * wins over "*" (all groups naming the same agent are merged), longest matching rule wins and Allow wins ties.
 */
final class RobotsTxt
{
    /** @var list<array{agents: list<string>, rules: list<array{allow: bool, path: string}>}> */
    private array $groups = [];

    /** @var list<string> */
    private array $sitemaps = [];

    public function __construct(string $content)
    {
        $current = null;
        $lastWasAgent = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'sitemap') {
                if ($value !== '') {
                    $this->sitemaps[] = $value;
                }

                continue;
            }

            if ($field === 'user-agent') {
                if (! $lastWasAgent || $current === null) {
                    $this->groups[] = ['agents' => [], 'rules' => []];
                    $current = array_key_last($this->groups);
                }
                $this->groups[$current]['agents'][] = strtolower($value);
                $lastWasAgent = true;

                continue;
            }

            $lastWasAgent = false;
            if ($current !== null && in_array($field, ['allow', 'disallow'], true)) {
                $this->groups[$current]['rules'][] = ['allow' => $field === 'allow', 'path' => $value];
            }
        }
    }

    /** @return list<string> */
    public function sitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @return array{allowed: bool, group: ?string, rule: ?string} group is the matched user-agent ("*" or the bot token),
     *                                                              rule the deciding line (e.g. "Disallow: /")
     */
    public function evaluate(string $agent, string $path = '/'): array
    {
        $agent = strtolower($agent);
        $rules = [];
        $group = null;

        foreach ($this->groups as $g) {
            if (in_array($agent, $g['agents'], true)) {
                $rules = [...$rules, ...$g['rules']];
                $group = $agent;
            }
        }
        if ($group === null) {
            foreach ($this->groups as $g) {
                if (in_array('*', $g['agents'], true)) {
                    $rules = [...$rules, ...$g['rules']];
                    $group = '*';
                }
            }
        }

        $best = null;
        foreach ($rules as $rule) {
            if ($rule['path'] === '' || ! self::matches($rule['path'], $path)) {
                continue;
            }
            $length = strlen($rule['path']);
            if ($best === null || $length > strlen($best['path']) || ($length === strlen($best['path']) && $rule['allow'])) {
                $best = $rule;
            }
        }

        return [
            'allowed' => $best === null || $best['allow'],
            'group' => $group,
            'rule' => $best === null ? null : ($best['allow'] ? 'Allow: ' : 'Disallow: ').$best['path'],
        ];
    }

    private static function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = $anchored ? substr($pattern, 0, -1) : $pattern;
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).($anchored ? '$' : '').'#';

        return (bool) preg_match($regex, $path);
    }
}
