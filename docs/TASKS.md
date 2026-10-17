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
- [~] F1 Locale routing, browser detection, switcher, hreflang, 9 language files
- [~] F2 Layout, design system (dark/light), SEO component, sitemap, robots
- [~] F3 Landing page sections
- [~] F4 Analysis form (Livewire) → AnalysisStarter
- [~] F5 Results dashboard with live progress, provider cards, competitors, questions, CTAs, DEMO label
- [~] F6 Lead form on results page + contact form, emails
- [~] F7 Privacy / Terms / Cookie pages
- [~] F8 Tests

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
- [ ] Merge backend → frontend → admin, resolve conflicts, full test + build
- [ ] End-to-end check in mock mode: submit → progress → results → lead → admin

## Phase 2 — Payments & reports (blocked until Phase 1 verified)
- [ ] orders/payments/reports schema
- [ ] Stripe Checkout + verified webhooks + idempotent fulfillment
- [ ] PDF GEO audit report
- [ ] Admin orders/reports resources
- [ ] README deployment docs
