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
- [~] B1 Provider adapters: OpenAI (Responses + web search), Gemini (Google Search grounding), Anthropic (Messages + web search tool), Perplexity (Sonar), Mock
- [~] B2 DefaultProviderRegistry (mock/live mode, no silent fallback)
- [~] B3 WebsiteInspector with SSRF protection
- [~] B4 QuestionGenerator (templates, no company name, analysis language)
- [~] B5 Pipeline jobs: RunAnalysis, QueryProvider (batch), FinalizeAnalysis; usage logs; budget checks
- [~] B6 BrandDetector (4 classes + uncertain, evidence)
- [~] B7 CompetitorExtractor (dedupe, frequency)
- [~] B8 ScoreCalculator
- [~] B9 DefaultAnalysisStarter (Turnstile, IP/domain limits, budget, cache reuse, dedupe)
- [~] B10 DefaultAnalysisMaintenance (retry failed only, recalculate)
- [~] B11 Tests

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
- [~] A1 Panel setup, auth hardening (active admins only, rate limit), branding
- [~] A2 Dashboard widgets
- [~] A3 Analyses resource (filters, detail, raw responses, retry, recalculate, export, delete)
- [~] A4 Leads resource (CRM statuses, notes, export)
- [~] A5 Contact messages resource
- [~] A6 AI providers resource (settings, test connection, key status)
- [~] A7 API cost page + limits settings
- [~] A8 Prompt templates resource
- [~] A9 Website settings page
- [~] A10 Admin users resource + `mentionai:create-admin` command
- [~] A11 DemoDataSeeder
- [~] A12 Tests

## Phase 1 integration (Lead)
- [ ] Merge backend → frontend → admin, resolve conflicts, full test + build
- [ ] End-to-end check in mock mode: submit → progress → results → lead → admin

## Phase 2 — Payments & reports (blocked until Phase 1 verified)
- [ ] orders/payments/reports schema
- [ ] Stripe Checkout + verified webhooks + idempotent fulfillment
- [ ] PDF GEO audit report
- [ ] Admin orders/reports resources
- [ ] README deployment docs
