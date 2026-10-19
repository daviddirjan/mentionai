<?php

namespace App\Enums;

enum CheckGroup: string
{
    case Access = 'access';         // HTTP, HTTPS, redirects, robots.txt, sitemap, llms.txt
    case AiCrawlers = 'ai_crawlers'; // robots.txt rules for GPTBot, ClaudeBot, PerplexityBot, …
    case Structure = 'structure';   // title, meta, H1, lang, canonical, hreflang, OG
    case Entity = 'entity';         // JSON-LD Organization / LocalBusiness, address, locality

    public function translationKey(): string
    {
        return 'audit.group.'.$this->value;
    }
}
