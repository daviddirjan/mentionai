# MentionAI — Shared Task List

Maintained by the lead. Teammates report progress in their final reports; the lead updates this file.
Status: `[ ]` todo · `[~]` in progress · `[x]` done & integrated · `[!]` blocked

## Phase 0 — Foundation (Lead)
- [x] Laravel 13 skeleton, Livewire 4, Filament 5, Tailwind 4, Lucide, dompdf, stripe-php installed
- [x] Architecture, CLAUDE.md conventions, file ownership
- [x] Database migrations, models, enums, factories, seeders
- [x] Contracts and DTOs (AnalysisStarter, AnalysisMaintenance, ProviderRegistry, AiProviderAdapter)
- [x] Settings service, Domain normalizer, foundation tests

## Phase 1 — MVP core (parallel)

### Backend & AI (`backend` branch)
- [x] B1 Provider adapters: OpenAI (Responses + web search), Gemini (Google Search grounding), Anthropic (Messages + web search tool), Perplexity (Sonar), Mock
- [x] B2 DefaultProviderRegistry (mock/live mode, no silent fallback)
- [x] B3 WebsiteInspector with SSRF protection
- [x] B4 QuestionGenerator (templates, no company name, analysis language)
- [x] B5 Pipeline jobs: RunAnalysis, QueryProvider (batch), FinalizeAnalysis; usage logs; budget checks
- [x] B6 BrandDetector (4 classes + uncertain, evidence)
- [x] B7 CompetitorExtractor (dedupe, frequency)
- [x] B8 ScoreCalculator
- [x] B9 DefaultAnalysisStarter (Turnstile, IP/domain limits, budget, cache reuse, dedupe)
- [x] B10 DefaultAnalysisMaintenance (retry failed only, recalculate)
- [x] B11 Tests

### Frontend (`frontend` branch)
- [x] F1 Locale routing, browser detection, switcher, hreflang, 9 language files
- [x] F2 Layout, design system (dark/light), SEO component, sitemap, robots
- [x] F3 Landing page sections
- [x] F4 Analysis form (Livewire) → AnalysisStarter
- [x] F5 Results dashboard with live progress, provider cards, competitors, questions, CTAs, DEMO label
- [x] F6 Lead form on results page + contact form, emails
- [x] F7 Privacy / Terms / Cookie pages
- [x] F8 Tests

### Admin (`admin` branch)
- [x] A1 Panel setup, auth hardening (active admins only, rate limit), branding
- [x] A2 Dashboard widgets
- [x] A3 Analyses resource (filters, detail, raw responses, retry, recalculate, export, delete)
- [x] A4 Leads resource (CRM statuses, notes, export)
- [x] A5 Contact messages resource
- [x] A6 AI providers resource (settings, test connection, key status)
- [x] A7 API cost page + limits settings
- [x] A8 Prompt templates resource
- [x] A9 Website settings page
- [x] A10 Admin users resource + `mentionai:create-admin` command
- [x] A11 DemoDataSeeder
- [x] A12 Tests

## Phase 1 integration (Lead)
- [x] Merge admin → backend → frontend (no conflicts), full test + build (232 tests)
- [x] End-to-end check in mock mode in a browser with a real queue worker: submit → results → lead + emails → admin
- [x] Fix: website inspection failed on every live fetch (stream handler vs CURLOPT_RESOLVE)
- [x] Fix: {service} used noisy scraped nav text and company product names; now industry first, filtered scrape fallback
- [ ] Verify the 4 provider adapters against current official API docs, then one live smoke test per provider (needs API keys)
- [ ] Run migrations + suite against PostgreSQL

## Phase 2 — Payments & reports (blocked until Phase 1 verified)
- [ ] orders/payments/reports schema
- [ ] Stripe Checkout + verified webhooks + idempotent fulfillment
- [ ] PDF GEO audit report
- [ ] Admin orders/reports resources
- [ ] README deployment docs

## Phase 3 — Final version: GEO Audit, PDF, redesign (spec: docs/AUDIT.md)
- [x] L1 Schema (tier, site_audit, recommendations, narrative, report), enums, DTOs, contracts, settings, 25 templates, fonts
### Backend & AI (`backend-audit` worktree)
- [x] BA1 SafeFetcher extracted from WebsiteInspector
- [x] BA2 DefaultSiteAuditor (check keys in AUDIT.md)
- [x] BA3 QuestionGenerator audit tier (25 questions, 9 languages for all templates, free check excludes audit-only)
- [x] BA4 RuleRecommendationBuilder
- [x] BA5 AiAuditNarrator (grounded, mock demo, rules fallback)
- [x] BA6 History comparison + DefaultAuditReportBuilder
- [x] BA7 DompdfReportRenderer + GenerateAuditReport job + mail dispatch
- [x] BA8 DefaultAuditStarter + pipeline tier handling (caps, budget)
- [x] BA9 Tests
### Frontend (`frontend-final` worktree)
- [x] FF1 Axiontic design tokens, Afacad, components restyle
- [x] FF2 Simplified form
- [x] FF3 Landing: "Why MentionAI" comparison, lean sections
- [x] FF4 Results: audit CTA + email → AuditStarter, audit section, PDF button
- [x] FF5 PDF template pdf.geo-audit
- [x] FF6 report.download route, AuditReadyMail
- [x] FF7 lang audit.php ×9 + tests
### Admin (`admin-audit` worktree)
- [x] AA1 Tier column/filter, PDF download/regenerate actions, infolist tables, settings fields, demo audit seed, tests
### Integration (Lead)
- [x] Merge admin → backend → frontend, 368 tests + build green
- [x] Tests can no longer reach real providers (phpunit.xml blanks keys, Http::preventStrayRequests)
- [x] Mock E2E (isolated DB): form → check → audit email → 100 tests → site scan of axiontic.com → PDF (25 pages) → signed email link
- [ ] One live audit (needs user approval, ~$0.50–1)
