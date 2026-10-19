# GEO Audit — shared contract (Phase 3)

Read with `CLAUDE.md` and `docs/ARCHITECTURE.md`. Reference PDF style: `docs/audit-sample/` page renders (cover, findings table, priorities table).
Every agent codes against what is written here. If something is missing, choose the smallest consistent addition and report it.

## Product
- **Free check** (`tier = check`, unchanged): `query_count` (3) templates that are enabled and **not** `is_audit_only` × enabled providers ≈ 12 tests. No site audit, no PDF.
- **GEO Audit** (`tier = audit`): started from a *completed* free check after the visitor leaves an email (lead).
  - Questions: `settings('audit_query_count')` (25). It uses all enabled templates (including audit-only) ordered by `sort_order` and cycles them if fewer than 25, with `{service}` offsets for variety. Result: 25 × 4 providers = 100 tests.
  - For the audit tier, each provider's `max_requests_per_analysis` cap is raised to the audit question count.
  - `max_cost_per_audit` replaces `max_cost_per_analysis` for this tier.
  - Pipeline:
    1. inspecting_website
    2. **auditing_site** (`SiteAuditor`)
    3. generating_questions
    4. querying
    5. analyzing
    6. scoring
    7. Finalize: `RecommendationBuilder` → `AuditNarrator` → status completed
    8. Dispatch the `GenerateAuditReport` job, which runs `ReportRenderer`, then mails `AuditReadyMail` to the audit's lead.
- Payments are not in scope.

## Schema (done, Lead)
- `analyses`: `tier` (AnalysisTier), `parent_analysis_id`, `site_audit` json, `recommendations` json, `narrative` json, `report_path` (hidden), `report_generated_at`.
- `prompt_templates`: `category` (QuestionCategory), `is_audit_only`.
- `Analysis` helpers: `isAudit()`, `hasReport()`, `siteChecks()`, `recommendationList()`, `auditNarrative()`, `parent()`, `audits()`.
- The factory `Analysis::factory()->audit()->create()` builds a full completed audit with site_audit, recommendations and narrative (source `demo`).
- Enums: `AnalysisTier`, `CheckStatus`, `CheckGroup`, `Priority` (p0 / p1 / p2, `planPhase()`), `QuestionCategory`, and `AnalysisStatus::AuditingSite`.
  - `AnalysisStatus::progressSteps($tier)` includes `auditing_site` only for audits.
- `AnalysisRejected::NOT_ELIGIBLE`.
- Settings: `audit_enabled`, `audit_query_count`, `max_cost_per_audit`, `audit_narrative_provider`, `report_link_days`.

## Contracts (app/Contracts, bound in AppServiceProvider → app/Services/Audit/*)
| Contract | Impl (Backend) | Notes |
|---|---|---|
| `AuditStarter::start(Analysis $check, Lead $lead, string $ip): AnalysisStartResult` | `DefaultAuditStarter` | Sets `lead.analysis_id` to the audit. Reuses a completed audit with the same config_hash within cache_hours. |
| `SiteAuditor::audit(Analysis): list<SiteCheck>` | `DefaultSiteAuditor` | Only the analysed host, via `SafeFetcher`. Never throws. |
| `RecommendationBuilder::build(Analysis): list<Recommendation>` | `RuleRecommendationBuilder` | Deterministic. |
| `AuditNarrator::narrate(Analysis): AuditNarrative` | `AiAuditNarrator` | One call. Mock → `demo`. Failure → `rules`. |
| `AuditReportBuilder::build(Analysis): AuditReport` | `DefaultAuditReportBuilder` | DB only. |
| `ReportRenderer::render(Analysis): string` | `DompdfReportRenderer` | Renders view `pdf.geo-audit` with `$report`, and writes `reports/{uuid}.pdf` on the `local` disk. |

DTOs in `app/Data`: `SiteCheck`, `Recommendation`, `AuditNarrative`, `HistoryComparison`, `AuditReport` (see the docblocks). Views format the DTOs only; they never query.

## Check keys (Backend emits, Frontend translates `audit.check.{key}.title` and `audit.check.{key}.impact`, with `:params`)
`evidence` is the observed fact, untranslated (`GET /sitemap.xml → 404`, `2 × <h1>`).
- **access:**
  - `homepage_status`
  - `https`
  - `robots_txt`
  - `sitemap_xml` (fail when declared in robots/HTML but 404)
  - `llms_txt` (warn when missing)
  - `response_time` (info; impact text says "observed, not Core Web Vitals")
- **ai_crawlers:**
  - `ai_crawler_gptbot`
  - `ai_crawler_oai_searchbot`
  - `ai_crawler_claudebot`
  - `ai_crawler_perplexitybot`
  - `ai_crawler_google_extended`
  - These take the param `bot`. They fail when they are disallowed for `/`.
- **structure:**
  - `title_tag` (param `length`)
  - `meta_description` (param `length`)
  - `h1_count` (param `count`)
  - `html_lang` (param `lang`)
  - `canonical`
  - `robots_meta` (fail on noindex)
  - `hreflang`
  - `open_graph`
- **entity:**
  - `jsonld_present` (param `types`)
  - `jsonld_local` (LocalBusiness / ProfessionalService with address)
  - `jsonld_locality_match` (param `location`; only when the analysis has a location)

## Recommendation keys (Backend emits, Frontend translates `audit.rec.{key}.action`, `.acceptance` and `.why`)
| key | priority / window | trigger |
|---|---|---|
| `fix_homepage_status` | p0 / 0-7 | homepage_status fail |
| `fix_https` | p0 / 0-7 | https fail |
| `publish_robots_sitemap` | p0 / 0-7 | robots_txt or sitemap_xml fail |
| `allow_ai_crawlers` (param `bots`) | p0 / 0-7 | any ai_crawler fail |
| `fix_robots_meta` | p0 / 0-7 | robots_meta fail |
| `fix_html_lang_h1` | p0 / 0-14 | html_lang or h1_count not pass |
| `fix_title_meta` | p0 / 0-14 | title_tag or meta_description not pass |
| `add_canonical` | p1 / 15-30 | canonical not pass |
| `add_local_business_schema` (param `location`) | p0 / 0-21 | jsonld_present, jsonld_local or jsonld_locality_match not pass |
| `add_hreflang` | p1 / 15-30 | hreflang not pass |
| `add_open_graph` | p2 / 60-90 | open_graph not pass |
| `publish_llms_txt` | p2 / 60-90 | llms_txt not pass |
| `local_landing_page` (param `location`) | p1 / 15-30 | location set and a local-category question was not recommended |
| `competitor_gap` (param `competitors`, max 3 names) | p1 / 30-60 | competitors mentioned in ≥ 25% of successful tests in which the brand was not recommended |
| `provider_gap` (param `provider`, display name) | p1 / 30-60 | a provider with ≥ 2 successful tests and 0 recommended |
| `case_studies` | p1 / 30-60 | score < 50 |
| `earn_citations` | p2 / 60-90 | no successful citation contains the brand domain |
| `third_party_profiles` | p2 / 60-90 | always, last |

## Narrative (AiAuditNarrator)
- **Input:** aggregated facts only (domain, market, location, industry, score, per-provider %, per-category %, top competitors, failed / warn check keys with evidence). Never raw AI answers. Never the email.
- **Output:** strict JSON `{verdict ≤ 400 chars, summary: 3–5 items ≤ 220 chars, h1 ≤ 70, title ≤ 65, meta_description ≤ 160}` in the analysis language.
- **Validation:** the output is checked and clamped. Invalid output → the `rules` narrative, built from `audit.narrative.*` lang keys with params.
- **Prompt rules:** "use only the facts given; do not invent numbers, clients, years, addresses; do not promise rankings". The usage is logged in `api_usage_logs` (operation `narrative`).

## Access to the PDF and the audit page
- **Route (Frontend):**
  - `GET /report/{analysis:uuid}.pdf`, named **`report.download`**, with no locale prefix.
  - Allowed when there is a valid signature (`URL::temporarySignedRoute('report.download', now()->addDays(settings('report_link_days')), ['analysis' => $uuid])`) or the session has `audit_access.{uuid}` (set when the visitor submits the audit email).
  - Otherwise it returns 403.
  - When the audit is not ready (`!hasReport()`), it returns 404.
  - It streams `Storage::disk('local')`, with filename `geo-audit-{domain}-{Y-m-d}.pdf`.
- **Mail (Frontend):** `App\Mail\AuditReadyMail(Analysis $audit, string $reportUrl, string $resultsUrl)`, a queued Mailable. Backend sends it from `GenerateAuditReport` to the latest lead of the audit, in the lead's `locale`.
- The audit results page is the existing `analysis.show` page with an audit section. It is public by uuid (like checks); the PDF needs the email or a signature.

## PDF look (Frontend, `resources/views/pdf/geo-audit.blade.php`)
- **Format:** A4, dompdf (tables, no flex/grid), inline `<style>`. Fonts from `resources/fonts` via `@font-face` with `src: url("{{ resource_path('fonts/…') }}")`:
  - Afacad (headings)
  - Inter (body)
  - JetBrains Mono (evidence, code)
  - Source Serif 4 SemiBold (optional, for the verdict)
- **Colours (Axiontic):**
  - ink `#1b3030`
  - secondary `#2d4a4a`
  - deep `#162626`
  - muted `#5b6b6b`
  - rules `#d9dedc`
  - cream box `#f2f0eb`
  - pass `#21a86b`, warn `#c98a12`, fail `#c0392b`
- **Sections, in order:**
  1. Cover with eyebrow, domain and market / location
  2. Meta table and verdict box
  3. 01 Executive summary
  4. 02 Methodology & limits
  5. 03 AI visibility (provider + category)
  6. 04 Questions & results
  7. 05 Competitors
  8. 06 Technical findings
  9. 07 Priorities
  10. H1 / meta model
  11. 08 Plan 30/60/90
  12. 09 Before & after
  13. 10 KPIs
  14. Appendix: sources
- **Footer:** `Audit GEO — {domain}` · `{date} · page N`.
- In demo mode, show a DEMO label on the cover and in the footer.
