# MentionAI — Development Conventions

MentionAI checks whether AI assistants (ChatGPT, Gemini, Claude, Perplexity) recommend a business.
Full product spec: `docs/SPEC.md`. Architecture and contracts: `docs/ARCHITECTURE.md`. Task list: `docs/TASKS.md`.

## Stack
Laravel 13 · PHP 8.3+ · PostgreSQL (prod) / SQLite (local + tests) · database queue · Livewire 4 (bundles Alpine — do not install Alpine separately) · Tailwind CSS 4 (Vite) · Filament 5 at `/admin` · Lucide icons via `mallardduck/blade-lucide-icons` (`<x-lucide-search class="size-4"/>`) · dompdf · stripe-php (phase 2).

## Commands
```sh
# first-time local setup (SQLite)
cp .env.example .env && sed -i '' -e 's/^DB_CONNECTION=pgsql/DB_CONNECTION=sqlite/' -e '/^DB_\(HOST\|PORT\|DATABASE\|USERNAME\|PASSWORD\)=/d' .env
php artisan key:generate && touch database/database.sqlite
php artisan migrate:fresh --seed   # local DB (database/database.sqlite)
php artisan test                   # all tests (SQLite in-memory)
php artisan test --filter=Name     # one test
npm run build                      # assets; must succeed before a task is done
php artisan queue:work             # analysis pipeline runs on the database queue
```
Default `MENTIONAI_AI_MODE=mock` — no paid API calls. Never run tests that hit real provider APIs; use `Http::fake()`.

## Code conventions
- Follow the Laravel 13 skeleton style: model attributes `#[Fillable([...])]`, `casts()` method, typed properties and return types, `final readonly` DTOs in `app/Data`.
- Enums in `app/Enums` are the source of truth for statuses. Store `->value`; cast on models.
- Contracts in `app/Contracts`; bindings in `AppServiceProvider::$bindings`. Code against the interface, not the implementation.
- Read admin settings with `settings('key')` (`App\Support\Settings`), never `config('mentionai.defaults.*')` directly.
- Normalize URLs/domains only via `App\Support\Domain`.
- Keep comments sparse: explain *why*, not *what*.
- Public identifiers are UUIDs (`analyses.uuid`, `leads.uuid`). Never put numeric ids in public URLs or HTML.

## Product rules that must not be broken
- Discovery questions must never contain the company name.
- An API failure is never a "not mentioned": failed responses are excluded from the score denominator.
- Score = recommended successful responses / successful responses × 100. Only `MentionClassification::Recommended` counts.
- Ambiguous matches → `uncertain`, never positive.
- Never fabricate AI responses, progress, testimonials, statistics or customer logos.
- Mock-mode data is `is_demo = true` and must be visibly labeled DEMO. Live mode never falls back to mock.
- API keys only in env/config. Never log them, render them, or store them in the DB.
- `ai_responses.raw_response`, `ip_hash`, lead `notes` are admin-only.
- The interface locale and the analysis language are independent.
- No payments until the core analysis workflow is verified (phase 2).

## Security
Validate all input server-side (Form Requests or Livewire `#[Validate]`). Escape output (`{{ }}`; never `{!! !!}` on AI or user text). Website inspection must block private/reserved IPs, re-check every redirect, cap size and time. Rate-limit public forms.

## Testing
PHPUnit (not Pest). Use `RefreshDatabase`, factories (`Analysis::factory()->completed()` gives a full 3×4 analysis), `Http::fake()`, `Queue::fake()`, `Mail::fake()`. Every feature ships with tests in its owner's test directory. A task is done only when `php artisan test` and `npm run build` pass.

## File ownership (parallel development)
Each area has exactly one owner. Do not edit files outside your area. If you need a change in a shared or foreign file, describe it in your final report and the lead will make it.

| Owner | Paths |
|---|---|
| **Lead** (architect) | `CLAUDE.md`, `AGENTS.md`, `docs/**`, `README.md`, `composer.*`, `package*.json`, `vite.config.js`, `bootstrap/**`, `config/**`, `database/migrations/**`, `database/factories/**`, `database/seeders/{DatabaseSeeder,AiProviderSeeder,PromptTemplateSeeder}.php`, `app/Models/**`, `app/Enums/**`, `app/Contracts/**`, `app/Data/**`, `app/Support/**`, `app/Exceptions/**`, `app/Providers/AppServiceProvider.php`, `.env.example`, `phpunit.xml`, `tests/TestCase.php`, `tests/*/Foundation/**` |
| **Frontend** | `routes/web.php`, `app/Livewire/**`, `app/Http/**`, `app/Mail/**`, `resources/views/**` (except `filament/`), `resources/css/**`, `resources/js/**`, `lang/**`, `public/**` (except `build/`), `tests/Feature/Frontend/**` |
| **Backend & AI** | `app/Services/**`, `app/Jobs/**`, `app/Actions/**`, `routes/console.php`, `tests/Feature/Backend/**`, `tests/Unit/Backend/**` |
| **Admin** | `app/Filament/**`, `app/Console/Commands/**`, `app/Providers/Filament/**`, `resources/views/filament/**`, `database/seeders/DemoDataSeeder.php`, `tests/Feature/Admin/**` |

Schema/model/contract changes go through the lead. Never add Composer or npm packages yourself — request them.
