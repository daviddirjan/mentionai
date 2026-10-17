<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Locked;

/**
 * Livewire update requests carry no /{locale} URL prefix, so the component remembers its own locale.
 */
trait UsesLocale
{
    #[Locked]
    public string $locale = 'en';

    public function mountUsesLocale(): void
    {
        $this->locale = app()->getLocale();
        URL::defaults(['locale' => $this->locale]);
    }

    public function hydrateUsesLocale(): void
    {
        $locale = in_array($this->locale, config('mentionai.locales'), true) ? $this->locale : config('app.locale');

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'required' => __('site.validation.required'),
            'max' => __('site.validation.max'),
            'in' => __('site.validation.in'),
            'email' => __('site.validation.email'),
        ];
    }

    protected function ipHash(): string
    {
        return hash('sha256', (string) request()->ip().config('app.key'));
    }
}
