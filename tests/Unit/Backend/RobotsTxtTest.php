<?php

namespace Tests\Unit\Backend;

use App\Services\Audit\RobotsTxt;
use PHPUnit\Framework\TestCase;

class RobotsTxtTest extends TestCase
{
    public function test_specific_group_overrides_the_wildcard_group(): void
    {
        $robots = new RobotsTxt("User-agent: *\nDisallow: /\n\nUser-agent: GPTBot\nAllow: /\n");

        $this->assertTrue($robots->evaluate('GPTBot')['allowed']);
        $this->assertSame('gptbot', $robots->evaluate('GPTBot')['group']);
        $this->assertFalse($robots->evaluate('ClaudeBot')['allowed']);
        $this->assertSame('*', $robots->evaluate('ClaudeBot')['group']);
        $this->assertSame('Disallow: /', $robots->evaluate('ClaudeBot')['rule']);
    }

    public function test_agent_matching_is_case_insensitive_and_groups_share_rules(): void
    {
        $robots = new RobotsTxt("User-agent: gptbot\nUser-agent: ClaudeBot\nDisallow: / # block\n\nUser-agent: *\nDisallow: /admin");

        $this->assertFalse($robots->evaluate('GPTBot')['allowed']);
        $this->assertFalse($robots->evaluate('ClaudeBot')['allowed']);
        $this->assertTrue($robots->evaluate('PerplexityBot')['allowed']);
    }

    public function test_empty_disallow_allows_everything_and_partial_paths_do_not_block_root(): void
    {
        $robots = new RobotsTxt("User-agent: *\nDisallow:\n\nUser-agent: Google-Extended\nDisallow: /private/");

        $this->assertTrue($robots->evaluate('GPTBot')['allowed']);
        $this->assertTrue($robots->evaluate('Google-Extended')['allowed']);
        $this->assertFalse($robots->evaluate('Google-Extended', '/private/x')['allowed']);
    }

    public function test_longest_match_wins_and_allow_wins_ties(): void
    {
        $robots = new RobotsTxt("User-agent: *\nDisallow: /\nAllow: /$\n");
        $this->assertTrue($robots->evaluate('GPTBot', '/')['allowed']);
        $this->assertFalse($robots->evaluate('GPTBot', '/page')['allowed']);

        $tie = new RobotsTxt("User-agent: *\nDisallow: /\nAllow: /\n");
        $this->assertTrue($tie->evaluate('GPTBot')['allowed']);

        $wildcard = new RobotsTxt("User-agent: *\nDisallow: /*\n");
        $this->assertFalse($wildcard->evaluate('GPTBot')['allowed']);
    }

    public function test_sitemaps_are_collected_from_anywhere_in_the_file(): void
    {
        $robots = new RobotsTxt("Sitemap: https://acme.example/sitemap_index.xml\nUser-agent: *\nDisallow:\nSitemap: https://acme.example/news.xml");

        $this->assertSame(['https://acme.example/sitemap_index.xml', 'https://acme.example/news.xml'], $robots->sitemaps());
    }
}
