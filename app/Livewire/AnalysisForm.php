<?php

namespace App\Livewire;

use App\Contracts\AnalysisStarter;
use App\Data\AnalysisInput;
use App\Exceptions\AnalysisRejected;
use App\Http\Support\Countries;
use App\Livewire\Concerns\UsesLocale;
use App\Support\Domain;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AnalysisForm extends Component
{
    use UsesLocale;

    public string $companyName = '';

    public string $websiteUrl = '';

    public string $market = '';

    public string $location = '';

    public string $industry = '';

    public string $language = '';

    /** Once the visitor picks a language, the country no longer changes it. */
    public bool $languageTouched = false;

    public string $turnstileToken = '';

    /** Honeypot: real visitors never see or fill this. */
    public string $nickname = '';

    public ?string $rejection = null;

    public ?string $existingUuid = null;

    public function mount(): void
    {
        $locale = app()->getLocale();
        $this->language = in_array($locale, config('mentionai.analysis_languages'), true) ? $locale : 'en';
    }

    /** Default analysis language per market; anything else falls back to English. */
    private const MARKET_LANGUAGES = [
        'RO' => 'ro', 'MD' => 'ro', 'DE' => 'de', 'AT' => 'de', 'CH' => 'de', 'LI' => 'de', 'ES' => 'es', 'MX' => 'es',
        'AR' => 'es', 'CL' => 'es', 'CO' => 'es', 'PE' => 'es', 'UY' => 'es', 'FR' => 'fr', 'BE' => 'fr', 'LU' => 'fr',
        'MC' => 'fr', 'IT' => 'it', 'PT' => 'pt', 'BR' => 'pt', 'NL' => 'nl', 'PL' => 'pl',
    ];

    public function updatedMarket(string $value): void
    {
        if ($this->languageTouched) {
            return;
        }

        $language = self::MARKET_LANGUAGES[$value] ?? 'en';
        if (in_array($language, config('mentionai.analysis_languages'), true)) {
            $this->language = $language;
        }
    }

    public function updatedLanguage(): void
    {
        $this->languageTouched = true;
    }

    protected function rules(): array
    {
        $rules = [
            'companyName' => ['required', 'string', 'max:120'],
            'websiteUrl' => ['required', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail) {
                if (Domain::normalizeUrl((string) $value) === null) {
                    $fail(__('site.validation.url'));
                }
            }],
            'market' => ['required', 'in:'.implode(',', Countries::codes())],
            'location' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
            'language' => ['required', 'in:'.implode(',', config('mentionai.analysis_languages'))],
        ];

        if (config('mentionai.turnstile.site_key')) {
            $rules['turnstileToken'] = ['required', 'string'];
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        return [
            'companyName' => __('site.form.company'),
            'websiteUrl' => __('site.form.website'),
            'market' => __('site.form.market'),
            'location' => __('site.form.location'),
            'industry' => __('site.form.industry'),
            'language' => __('site.form.language'),
            'turnstileToken' => __('site.form.verification'),
        ];
    }

    public function submit(): void
    {
        $this->rejection = null;
        $this->existingUuid = null;

        $this->validate();

        if ($this->nickname !== '') {
            return;
        }

        $input = new AnalysisInput(
            companyName: trim($this->companyName),
            websiteUrl: trim($this->websiteUrl),
            market: $this->market,
            language: $this->language,
            industry: trim($this->industry) !== '' ? trim($this->industry) : null,
            interfaceLocale: $this->locale,
            ip: (string) request()->ip(),
            turnstileToken: $this->turnstileToken !== '' ? $this->turnstileToken : null,
            location: trim($this->location) !== '' ? trim($this->location) : null,
        );

        try {
            $result = app(AnalysisStarter::class)->start($input);
        } catch (AnalysisRejected $e) {
            $this->rejection = $e->reason;
            $this->existingUuid = $e->existingUuid;
            $this->turnstileToken = '';
            $this->dispatch('turnstile-reset');

            return;
        }

        if ($result->reused) {
            session()->flash('analysis_reused', $result->analysis->uuid);
        }

        $this->redirectRoute('analysis.show', ['analysis' => $result->analysis->uuid], navigate: false);
    }

    public function render(): View
    {
        return view('livewire.analysis-form', [
            'countries' => Countries::options($this->locale),
            'languages' => collect(config('mentionai.analysis_languages'))
                ->mapWithKeys(fn (string $code) => [$code => __('site.languages.'.$code)])
                ->all(),
        ]);
    }
}
