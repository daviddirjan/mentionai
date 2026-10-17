# PROJECT: MentionAI
# AI Brand Visibility Analysis Platform
# Developed by Axiontic SRL

You are a senior full-stack engineer, software architect,
UI/UX designer, and SaaS product developer.

Your task is to DESIGN AND IMPLEMENT a complete, functional,
production-oriented MVP called MentionAI.

Do not simply create mockups or documentation.
Build the actual application, database, frontend, backend,
AI integrations, and administration dashboard.

The application must be maintainable, secure, scalable,
and designed for international users.

Prioritize simplicity, low API costs, and excellent UX.

====================================================
1. PROJECT OVERVIEW
====================================================

MentionAI is a web application that helps businesses
discover whether AI assistants recommend their company.

The application analyzes brand visibility across:

1. OpenAI / ChatGPT
2. Google Gemini
3. Anthropic Claude
4. Perplexity

The user enters their company name and website.

MentionAI automatically generates relevant discovery
questions and sends them to the four AI providers.

It then analyzes the responses and determines:

- Whether the company was mentioned.
- How frequently the company was recommended.
- Which competitors were recommended.
- Whether the company website was referenced.
- The company's visibility score.
- Opportunities to improve brand visibility.

IMPORTANT:

MentionAI is an independent visibility analysis platform.

Do not claim that API results are identical to the results
users see inside ChatGPT, Gemini, Claude, or Perplexity.

All results must be generated using real API responses.
Never fabricate AI results.

The main commercial objective is lead generation for
Axiontic SRL's GEO optimization services.

Secondary monetization:
Selling automated AI visibility audit reports.

====================================================
2. TECHNOLOGY STACK
====================================================

Use the following stack:

Backend:
- PHP
- Laravel (latest compatible stable version)
- PostgreSQL
- Laravel database queues

Frontend:
- Laravel Blade
- Livewire
- Tailwind CSS
- Alpine.js
- Lucide icons

Administration:
- Filament Admin Panel

Payments:
- Stripe Checkout

Email:
- Laravel Mail with SMTP support

PDF:
- A reliable Laravel-compatible HTML-to-PDF solution

Use current stable versions and follow official documentation.

Avoid unnecessary microservices.

Keep the architecture modular so that AI providers,
payment services, and additional features can be added later.

The application must be deployable on a conventional
Linux VPS with PHP, PostgreSQL, and a queue worker.

====================================================
3. DESIGN SYSTEM
====================================================

Create an elegant, modern, premium SaaS interface.

Design inspiration:
- Linear
- Vercel
- Stripe
- Modern AI analytics platforms

The design should feel like a premium technology product.

Visual characteristics:

- Minimalist design
- Generous whitespace
- Excellent typography
- Subtle gradients
- Rounded cards
- Smooth transitions
- High-quality loading animations
- Professional charts
- Clean responsive layouts

Use a sophisticated dark theme with a neutral background,
white typography, and restrained blue/violet accents.

Support both dark and light mode.

Mobile-first and fully responsive.

Avoid generic dashboard templates.

All interfaces must have proper loading states,
empty states, error states, and success states.

Brand:
MentionAI

Tagline:
"Discover how AI sees your brand."

Alternative marketing headline:
"Is AI recommending your business?"

Footer:
"© MentionAI. Developed by Axiontic SRL."

====================================================
4. INTERNATIONALIZATION
====================================================

MentionAI targets international markets.

Implement a scalable localization system.

Initial supported languages:

- English
- Romanian
- Spanish
- French
- German
- Italian
- Portuguese
- Dutch
- Polish

Requirements:

- Detect browser language.
- Allow users to change language manually.
- Store translations in localization files.
- Default to English when no translation exists.
- Use localized URLs where appropriate.
- Implement hreflang tags.
- Localize metadata and marketing pages.
- Support additional languages without architectural changes.

IMPORTANT:

The interface language and the analysis language
must be independent.

Example:

A user browsing in Spanish should be able to analyze
a company targeting customers in Romania.

The analysis must use the selected target market
and target language.

====================================================
5. PUBLIC LANDING PAGE
====================================================

Create a conversion-focused landing page.

HERO SECTION

Headline:

"Is AI recommending your business?"

Description:

"Discover whether ChatGPT, Gemini, Claude, and
Perplexity recommend your company to potential
customers."

Primary CTA:

"Check Your AI Visibility — Free"

Below the hero, create the analysis form.

Fields:

1. Company name (required)
2. Website URL (required)
3. Country / target market (required)
4. Industry / business category (optional)
5. Analysis language (required)

Example:

Company: Axiontic
Website: https://axiontic.com
Market: Romania
Industry: Software Development
Language: Romanian

Button:

"Analyze My Business"

OTHER LANDING PAGE SECTIONS

Create:

- How MentionAI Works
- Supported AI Platforms
- Why AI Visibility Matters
- Example Analysis
- Features
- Pricing / GEO Audit
- Frequently Asked Questions
- Contact
- Footer

SEO optimize all public pages.

Do not fabricate testimonials, customer counts,
market statistics, or client logos.

====================================================
6. BUSINESS ANALYSIS ENGINE
====================================================

This is the core functionality.

When a user submits a company:

STEP 1 — INPUT VALIDATION

Validate:

- Company name
- Website URL
- Market
- Analysis language

Normalize the website domain.

Reject malformed URLs.

STEP 2 — WEBSITE INSPECTION

Safely inspect the public website.

Extract available information:

- Website title
- Meta description
- Main H1
- Business description
- Relevant services
- Industry
- Basic structured data
- Public business information

Use this information ONLY to understand
the company's identity and services.

Do not insert the company name into discovery prompts.

Protect the website inspection against SSRF,
private network requests, unsafe redirects,
oversized responses, and request timeouts.

If inspection fails, allow analysis using
the user-provided information.

STEP 3 — DISCOVERY QUERY GENERATION

Generate 3 relevant questions that a potential
customer might realistically ask an AI assistant.

Example:

Company: Axiontic
Industry: Software Development
Market: Romania

Possible prompts:

"Recommend software development companies in Romania."

"What companies develop custom ERP systems in Romania?"

"Which agencies provide web development services
for businesses in Timisoara?"

These questions must not contain the company name.

This is extremely important.

We want to discover whether AI recommends the company
organically, not whether AI recognizes its name.

Use consistent query generation rules.

Save every generated question in the database.

STEP 4 — AI PROVIDER EXECUTION

Send the same discovery questions to:

- OpenAI
- Gemini
- Claude
- Perplexity

For the MVP:

3 questions x 4 providers = maximum 12 main API requests.

Use affordable models suitable for basic research.

Where supported, enable the provider's official
web search / grounding functionality.

Consult current official provider documentation
before implementing integrations.

Use:

OpenAI:
Responses API with supported web-search capability.

Google:
Gemini API with Google Search grounding.

Anthropic:
Messages API with supported web-search tool.

Perplexity:
Sonar API using the official supported endpoint.

Model identifiers must be configurable.

Do not hardcode outdated model names.

Use separate provider adapters implementing
a common interface.

Each adapter should return normalized data:

- Provider name
- Model identifier
- Question
- Raw response
- Extracted response text
- Available citations
- Execution time
- Token usage where available
- Estimated API cost
- Execution status
- Error details where applicable

Use background jobs to execute provider requests.

The public interface should display analysis progress.

Possible states:

- Preparing analysis
- Generating discovery questions
- Querying AI providers
- Analyzing recommendations
- Calculating visibility score
- Complete

Do not fake progress or fabricate responses.

Handle individual provider failures gracefully.

====================================================
7. BRAND MENTION DETECTION
====================================================

Implement a dedicated brand detection service.

The detection system must identify whether the
target company appears in the AI-generated response.

Check:

- Exact company name
- Normalized company name
- Known brand variations
- Official website domain
- Relevant citations

Important:

A website appearing only as a cited source is not
necessarily a brand recommendation.

Differentiate between:

1. Explicit brand recommendation
2. Brand mention without recommendation
3. Website citation only
4. No mention

Avoid false positives from companies with similar names.

Use deterministic matching whenever possible.

For ambiguous matches, classify the result as
uncertain rather than inventing a positive match.

Save classification evidence.

Also extract competing companies appearing
in the same responses.

Deduplicate competitor names.

Store competitor mention frequency.

====================================================
8. VISIBILITY SCORE
====================================================

Create a transparent scoring system.

For the MVP, use an easy-to-understand metric:

Visibility Score =
(Questions with confirmed brand recommendations /
Successfully analyzed questions) x 100

Only successful provider responses should contribute
to the denominator.

An API failure must never be interpreted as
a missing brand recommendation.

Generate:

- Global visibility score
- Score by provider
- Number of recommendations
- Number of mentions
- Number of website citations
- Most frequently mentioned competitors

Display the number of successful tests.

Example:

AI Visibility Score: 25/100

ChatGPT: 0/3
Gemini: 1/3
Claude: 0/3
Perplexity: 2/3

These are sample values for UI design only.

The production application must calculate
all results from actual API responses.

Explain clearly that the score is a
MentionAI metric based on sampled API responses,
not an official ranking from AI providers.

====================================================
9. PUBLIC RESULTS PAGE
====================================================

Create a professional results dashboard.

The page must show:

A. Company information

- Name
- Website
- Market
- Industry

B. Global visibility score

Display the score using a modern chart.

C. Provider comparison

Create four cards:

- ChatGPT
- Gemini
- Claude
- Perplexity

Each card displays:

- Brand recommendation status
- Recommendations / successful tests
- Visibility percentage
- Provider execution status

D. Competitor visibility

Display competing companies extracted from
the analyzed AI responses.

Show their frequency of appearance.

E. Discovery questions

Display all analyzed questions.

Allow the user to expand a question and see:

- AI provider
- Actual response
- Brand detection result
- Available citations

F. Commercial section

If visibility is low:

"Your competitors may be getting recommended
while your business remains invisible."

If visibility is high:

"Discover how to maintain and expand your
visibility across AI platforms."

Display two CTAs:

1. "Get Full GEO Audit"
2. "Talk to a GEO Expert"

Always make both options available.

Results must have a unique, non-guessable URL.

Protect access to potentially sensitive
submitted company and contact information.

====================================================
10. GEO AUDIT REPORT
====================================================

Implement a basic automated GEO audit system.

The report is a paid product.

Default price:
99 RON

Price must be configurable by the administrator.

The report should include:

1. Executive summary
2. Company information
3. Overall AI visibility score
4. Visibility by AI platform
5. Analyzed discovery queries
6. Competitor comparison
7. Website technical observations
8. GEO improvement opportunities
9. Prioritized recommendations
10. Recommended next steps

The report must use real analysis data.

Website-related recommendations must be based
on actual observable website characteristics.

Examples:

- Missing or weak page titles
- Missing structured data where relevant
- Poorly defined services
- Missing business descriptions
- Inconsistent company information
- Lack of useful service-specific content

Do not claim that adding schema markup or other
individual changes guarantees AI recommendations.

Generate a professionally designed PDF.

Include MentionAI branding and a discreet:

"Analysis powered by Axiontic SRL"

The report should be available only after
successful payment confirmation.

====================================================
11. PAYMENTS
====================================================

Integrate Stripe Checkout.

Requirements:

- Configurable report price
- Test and live environment support
- Secure checkout session creation
- Verified Stripe webhooks
- Payment status tracking
- Order database records
- Prevention of duplicate fulfillment
- Report access after confirmed payment
- Confirmation email
- Secure PDF download

Never trust payment status provided by the frontend.

If Stripe credentials are not configured,
disable the checkout function gracefully.

Do not display a fake successful payment.

Implement a basic purchase flow suitable for an MVP.

====================================================
12. CONTACT / LEAD GENERATION
====================================================

The second commercial objective is selling
monthly GEO optimization services.

Create a contact form accessible from
every results page.

Fields:

- Name
- Company
- Email
- Phone (optional)
- Website
- Message

Automatically associate the submitted lead
with the relevant analysis.

Store leads in the database.

Send an email notification to the administrator.

The user should receive a professional
confirmation message.

All contact messages must be visible
in the admin dashboard.

====================================================
13. ADMINISTRATION PANEL
====================================================

Create a complete administration panel using Filament.

URL:
/admin

The administration panel must have
secure administrator authentication.

There must be no public administrator registration.

Use appropriate password hashing,
session protection, CSRF protection,
authorization, and login rate limiting.

ADMIN MODULES:

-----------------------------------
A. DASHBOARD
-----------------------------------

Display:

- Total analyses
- Analyses today
- Analyses this month
- Total leads
- Leads this month
- Reports sold
- Total revenue
- Estimated API expenses
- Average visibility score
- Analyses by provider
- Recent activity

Include useful charts.

-----------------------------------
B. ANALYSES MANAGEMENT
-----------------------------------

List all company analyses.

Columns:

- Company name
- Website
- Country
- Language
- Visibility score
- Analysis status
- Date

Filters:

- Date
- Country
- Provider
- Status
- Score range

Allow administrators to:

- View complete analysis
- Inspect prompts
- Inspect raw AI responses
- View detected competitors
- See provider errors
- Retry failed requests
- Recalculate score
- Export data
- Delete analysis

Retries must avoid accidental duplicate paid API calls.

-----------------------------------
C. LEADS MANAGEMENT
-----------------------------------

Create a basic CRM-style lead management section.

Columns:

- Contact name
- Company
- Email
- Phone
- Website
- Visibility score
- Lead status
- Creation date

Statuses:

- New
- Contacted
- Qualified
- Proposal Sent
- Won
- Lost

Allow:

- Changing lead status
- Adding internal notes
- Viewing associated analysis
- Filtering and searching leads
- Exporting lead data
- Deleting leads

-----------------------------------
D. AI PROVIDERS MANAGEMENT
-----------------------------------

Create a dedicated section for managing
the four AI providers.

Provider settings:

- Provider name
- Enabled / disabled
- Model identifier
- Maximum requests per analysis
- Timeout
- Maximum output tokens
- Maximum search calls where supported
- Estimated input token pricing
- Estimated output token pricing
- Estimated search pricing

API keys must be loaded securely
from environment variables or an encrypted
server-side secrets mechanism.

Never expose API keys in the frontend,
logs, or API responses.

Show provider connection status.

Add a "Test Connection" action.

Make provider settings configurable
without editing application code.

-----------------------------------
E. API COST MANAGEMENT
-----------------------------------

Implement basic API cost monitoring.

Show:

- Estimated expense per analysis
- Expense per provider
- Daily expenses
- Monthly expenses
- Total API calls
- Failed calls
- Token usage when available

Allow configuration of:

- Maximum analyses per day
- Maximum estimated cost per analysis
- Monthly estimated API budget
- Rate limits for free users
- Global enable / disable switch

When a configured limit is reached,
stop launching additional paid requests.

Do not confuse estimated internal costs
with confirmed provider invoices.

-----------------------------------
F. REPORTS & ORDERS
-----------------------------------

Display:

- Purchased reports
- Customer information
- Payment status
- Amount
- Currency
- Purchase date
- Associated analysis
- PDF generation status

Allow:

- Viewing reports
- Downloading reports
- Regenerating reports
- Viewing order information
- Editing report price

-----------------------------------
G. CONTACT MESSAGES
-----------------------------------

Store and display all contact form submissions.

Allow administrators to:

- Read messages
- Mark as read/unread
- Change status
- Add notes
- Delete messages

-----------------------------------
H. WEBSITE SETTINGS
-----------------------------------

Create a settings area.

Allow editing:

- Website name
- Tagline
- Logo
- Favicon
- Contact email
- Company information
- Social media links
- SEO title
- Meta description
- Default language
- Default report price
- Maintenance mode

-----------------------------------
I. PROMPT MANAGEMENT
-----------------------------------

Create a section to manage discovery
prompt generation.

Allow administrators to:

- Configure query count
- Edit query templates
- Set default query rules
- Enable / disable specific templates
- View prompts used in past analyses

Changing templates must not alter
historical analysis data.

-----------------------------------
J. ADMIN USERS
-----------------------------------

For the MVP, implement a simple
administrator permission system.

Only authorized administrators
may access /admin.

Support creating, editing, and
deactivating admin accounts.

No public user account system is required.

====================================================
14. FREE ANALYSIS LIMITS
====================================================

Public users can analyze companies
without creating an account.

Protect the service against abuse.

Implement:

- Rate limiting by IP
- Rate limiting by normalized domain
- Cloudflare Turnstile support
- Server-side validation
- API request quotas
- Analysis caching
- Duplicate request prevention

Default:

1 free analysis per domain every 24 hours.

Make this configurable.

When the same domain, market, language,
and query configuration have been analyzed
recently, reuse the cached analysis.

Prevent concurrent duplicate jobs
for the same analysis configuration.

Display the date of the cached analysis.

Do not allow unrestricted API spending.

====================================================
15. DATABASE
====================================================

Design a clean relational schema.

Suggested entities:

- users
- companies
- analyses
- analysis_questions
- ai_providers
- ai_responses
- brand_mentions
- competitors
- leads
- contact_messages
- reports
- orders
- payments
- settings
- api_usage_logs

Implement migrations and relationships.

Use indexes for frequent searches.

Use UUIDs for publicly exposed identifiers.

Store historical analyses independently.

Do not overwrite previous results.

Keep timestamps for all important operations.

====================================================
16. SECURITY AND PRIVACY
====================================================

Follow Laravel security best practices.

Requirements:

- Input validation
- Output escaping
- CSRF protection
- Authentication protection
- Secure administrator sessions
- API secret protection
- Rate limiting
- SQL injection protection
- XSS protection
- SSRF protection
- Secure file downloads
- Verified payment webhooks

Add the necessary technical infrastructure
for GDPR-oriented privacy compliance.

Include:

- Privacy Policy page
- Terms of Service page
- Cookie Policy page when applicable
- User-facing data processing disclosure
- Data deletion capability
- Clear data retention settings

Do not claim automatic legal compliance.

Make legal content easy for the administrator
to review and replace before production launch.

====================================================
17. SEO REQUIREMENTS
====================================================

The public website must be technically optimized
for search engines.

Implement:

- Semantic HTML
- Proper H1/H2 hierarchy
- SEO metadata
- Open Graph tags
- Canonical URLs
- XML sitemap
- Robots.txt
- Hreflang tags
- Fast page rendering
- Responsive layout
- Appropriate structured data

Keep private analysis pages out of
public search engine indexes by default.

Build a reusable SEO component.

====================================================
18. EMAIL NOTIFICATIONS
====================================================

Create branded transactional email templates.

Required emails:

1. New lead notification to administrator
2. Contact confirmation to visitor
3. Successful report purchase
4. Report delivery / download link

Email language should follow the user's
selected language when translations exist.

Use Laravel Mail.

All credentials must be configurable
through environment variables.

====================================================
19. DEVELOPMENT MODE
====================================================

Create a development environment that works
without real AI credentials.

Implement clearly separated mock provider adapters.

Mock mode must:

- Return deterministic test data
- Never trigger paid API calls
- Be clearly labeled as DEMO
- Never be presented as real production analysis

Create sample records for testing
the admin dashboard.

Production mode must not silently
fall back to mock AI responses.

====================================================
20. TESTING
====================================================

Implement automated tests covering:

- Company submission
- Input validation
- Website URL safety
- Analysis creation
- AI adapter normalization
- Provider failures
- Brand mention detection
- Score calculation
- Competitor extraction
- Free usage limits
- Cache reuse
- Admin authentication
- Lead creation
- Payment webhook verification
- Report access authorization

Create tests using mocked external services
to avoid unnecessary API charges.

Run migrations, tests, and application builds.

Fix discovered errors before completing.

====================================================
21. MVP LIMITATIONS
====================================================

DO NOT BUILD THESE FEATURES YET:

- Public user registration
- Customer dashboards
- Monthly customer subscriptions
- Team collaboration
- API access for external customers
- White-label solutions
- Mobile applications
- Enterprise integrations
- Automated daily brand monitoring
- Complex billing systems
- AI chat interfaces

Focus on:

1. Public landing page
2. Business analysis
3. Four AI integrations
4. Visibility results
5. Lead generation
6. Paid PDF reports
7. Complete administration panel

The application should be simple
but genuinely functional.

====================================================
22. REQUIRED DELIVERABLES
====================================================

Implement the complete project.

Required deliverables:

- Working Laravel application
- Responsive public interface
- Functional admin panel
- Database migrations
- Models and relationships
- AI provider adapters
- Business analysis service
- Scoring engine
- Lead management
- Stripe Checkout integration
- PDF generation
- Localization files
- Security protections
- Automated tests
- README.md
- .env.example
- Deployment instructions

Document:

- Installation
- Environment configuration
- Database setup
- Queue worker setup
- API key configuration
- Stripe webhook setup
- Administrator account creation
- Production deployment

====================================================
23. IMPLEMENTATION PROCESS
====================================================

First inspect the current repository.

If an application already exists,
preserve its working functionality.

Then:

1. Define the architecture and database schema.
2. Create a concise implementation plan.
3. Install and configure the required dependencies.
4. Implement the database and core services.
5. Implement the public interface.
6. Implement AI integrations.
7. Implement results and scoring.
8. Implement the admin panel.
9. Implement lead generation.
10. Implement payments and PDF reports.
11. Add localization and SEO.
12. Implement automated tests.
13. Fix errors and document deployment.

Do not stop after producing a plan.

Continue implementing the actual application.

Avoid unnecessary questions.

Make sensible technical decisions
that prioritize MVP delivery.

If external credentials are missing,
implement the complete integration and
document the required environment variables.

Never invent successful third-party API responses.

At completion, provide:

- Summary of implemented functionality
- Main project structure
- Installation instructions
- Required API keys
- Administrator setup instructions
- Test results
- Remaining limitations

====================================================
24. FINAL PRODUCT EXPECTATION
====================================================

MentionAI must feel like a polished,
professional SaaS application.

A visitor should be able to:

1. Open the website.
2. Enter their company information.
3. Start a free analysis.
4. See real results from available AI providers.
5. Understand their AI visibility.
6. Identify relevant competitors.
7. Purchase a GEO audit report.
8. Contact Axiontic for optimization services.

An administrator should be able to:

1. Log into /admin.
2. View all analyses.
3. Manage leads.
4. Review AI responses.
5. Monitor API expenses.
6. Configure AI providers.
7. Manage orders and reports.
8. Update application settings.

Build the application with excellent
code quality and professional design.

The goal is to launch a real,
commercially usable MVP.

Begin implementation now.