<?php

namespace App\Contracts;

use App\Data\SiteCheck;
use App\Models\Analysis;

interface SiteAuditor
{
    /**
     * Technical GEO checks of the analysed site (homepage, robots.txt, sitemap.xml, llms.txt, AI crawler rules,
     * structure, JSON-LD entity). Only fetches the analysed host through the SSRF-safe fetcher. Never throws:
     * an unreachable resource becomes a Fail/Warn check with the observed evidence.
     *
     * @return list<SiteCheck>
     */
    public function audit(Analysis $analysis): array;
}
