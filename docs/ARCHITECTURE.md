# MentionAI Architecture

## Request flow (free analysis)

```
Landing form (Livewire, Frontend)
  └─ builds App\Data\AnalysisInput
  └─ app(AnalysisStarter::class)->start($input)          (Backend)
       ├─ Turnstile check, IP + domain rate limits, global switch, daily/monthly budget
       ├─ cache reuse: same config_hash completed < cache_hours ago → return it (reused = true)
       ├─ in-flight duplicate with same config_hash → return it (reused = true)
       ├─ create Company (by domain) + Analysis (status pending)
       └─ dispatch RunAnalysis job
  └─ redirect to /{locale}/analysis/{uuid}

RunAnalysis job (Backend, database queue)
  1. status inspecting_website   → WebsiteInspector (SSRF-safe) → analyses.website_inspection
  2. status generating_questions → QuestionGenerator (templates, no company name) → analysis_questions
  3. status querying_providers   → one ai_responses row per question × enabled provider (pending),
                                   Bus::batch of QueryProvider jobs; each calls the adapter, stores the
                                   normalized ProviderResult, writes api_usage_logs; checks budget first
  4. status analyzing            → BrandDetector per successful response → brand_mentions;
                                   CompetitorExtractor across responses → competitors
  5. status scoring              → ScoreCalculator → analyses score columns + provider_scores
  6. status completed (or failed if every provider response failed)

Results page (Livewire, Frontend) polls Analysis status while !isFinished(), then renders from DB.
```

## Contracts (app/Contracts)

| Interface | Implemented by | Used by |
|---|---|---|
| `AnalysisStarter::start(AnalysisInput): AnalysisStartResult` throws `AnalysisRejected` | Backend `App\Services\Analysis\DefaultAnalysisStarter` | Frontend form |
| `AnalysisMaintenance::retryFailed()`, `recalculate()` | Backend `App\Services\Analysis\DefaultAnalysisMaintenance` | Admin actions |
| `ProviderRegistry::adapterFor(AiProvider)`, `isMockMode()` | Backend `App\Services\Ai\DefaultProviderRegistry` | Backend jobs, Admin "Test connection" |
| `AiProviderAdapter::query()`, `testConnection()` | Backend adapters in `App\Services\Ai\Adapters` | Registry |

DTOs live in `app/Data`. `ProviderResult` is the normalized adapter output; adapters never throw on API errors.

## Database (see database/migrations)

- `companies` — one per domain/name; `brand_variations` for detection.
- `analyses` — public `uuid`; input snapshot; `status` (AnalysisStatus); `config_hash` (domain|market|language|query config) for cache/dedupe; score columns; `provider_scores` JSON `{slug: {recommended, successful, total, percentage}}`; `website_inspection` JSON; `is_demo`.
- `analysis_questions` — `question` text + `template_snapshot` (history survives template edits).
- `ai_providers` — admin-editable model/limits/prices; keys only in env.
- `ai_responses` — unique (question, provider): retries update the row, never duplicate. `raw_response` admin-only.
- `brand_mentions` — one per response; `classification` (MentionClassification), `matched_terms`, `evidence`.
- `competitors` — per analysis, deduped by `normalized_name`, `mention_count`, `providers`.
- `api_usage_logs` — append-only cost/usage log (estimates, not invoices).
- `leads` (results-page form, linked to analysis) and `contact_messages` (general contact form).
- `settings` — key/value JSON over `config('mentionai.defaults')`, read via `settings()`.
- `prompt_templates` — admin-managed discovery templates. Placeholders: `{industry} {service} {market} {city} {language}`.

Phase 2 (after the core workflow is verified): `orders`, `payments`, `reports`, Stripe Checkout, PDF GEO audit.

## Localization

Interface: URL prefix `/{locale}` for public pages (`config('mentionai.locales')`), browser detection on `/`, `lang/{locale}/*.php`, English fallback, hreflang. Analysis language is a separate form field and drives question language.

## Translation keys shared between teams
- `analysis.status.{AnalysisStatus value}`
- `analysis.classification.{MentionClassification value}`
- `analysis.rejected.{AnalysisRejected reason}`
