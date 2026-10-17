<div id="analyze" class="scroll-mt-24">
    <x-ui.card class="relative overflow-hidden p-6 sm:p-8">
        <div class="pointer-events-none absolute -right-24 -top-24 size-64 rounded-full bg-accent/10 blur-3xl"></div>

        <div class="relative">
            <h2 class="text-xl font-semibold tracking-tight">{{ __('site.form.title') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('site.form.subtitle') }}</p>

            @if ($rejection)
                <div class="mt-5 flex gap-3 rounded-xl border border-warn/30 bg-warn/10 p-4 text-sm" role="alert" data-testid="rejection">
                    <x-lucide-circle-alert class="mt-0.5 size-5 shrink-0 text-warn" />
                    <div>
                        <p class="font-medium">{{ __('site.form.rejected_title') }}</p>
                        <p class="mt-1 text-muted">{{ __('analysis.rejected.'.$rejection) }}</p>
                        @if ($existingUuid)
                            <a class="mt-2 inline-flex items-center gap-1 font-medium text-accent hover:underline" href="{{ route('analysis.show', ['analysis' => $existingUuid]) }}">
                                {{ __('site.form.view_existing') }} <x-lucide-arrow-right class="size-4" />
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            <form wire:submit="submit" class="mt-6 grid gap-4 sm:grid-cols-2" novalidate>
                <x-ui.input name="companyName" wire:model="companyName" :label="__('site.form.company')" :placeholder="__('site.form.company_placeholder')" required maxlength="120" autocomplete="organization" />
                <x-ui.input name="websiteUrl" wire:model="websiteUrl" :label="__('site.form.website')" :placeholder="__('site.form.website_placeholder')" required type="text" inputmode="url" autocomplete="url" />
                <x-ui.select name="market" wire:model="market" :label="__('site.form.market')" :options="$countries" :placeholder="__('site.form.market_placeholder')" required />
                <x-ui.input name="industry" wire:model="industry" :label="__('site.form.industry').' ('.__('site.common.optional').')'" :placeholder="__('site.form.industry_placeholder')" maxlength="120" />
                <div class="sm:col-span-2">
                    <x-ui.select name="language" wire:model="language" :label="__('site.form.language')" :options="$languages" required />
                    <p class="mt-1 text-xs text-subtle">{{ __('site.form.language_hint') }}</p>
                </div>

                <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                    <label>Nickname <input type="text" tabindex="-1" autocomplete="off" wire:model="nickname"></label>
                </div>

                @if (config('mentionai.turnstile.site_key'))
                    <div class="sm:col-span-2" wire:ignore
                         x-data="{ id: null }"
                         x-init="const mount = () => { if (!window.turnstile) { return setTimeout(mount, 200); } id = window.turnstile.render($refs.box, { sitekey: @js(config('mentionai.turnstile.site_key')), callback: (t) => $wire.set('turnstileToken', t), 'expired-callback': () => $wire.set('turnstileToken', '') }); }; mount();"
                         x-on:turnstile-reset.window="window.turnstile && id !== null && window.turnstile.reset(id)">
                        <div x-ref="box"></div>
                    </div>
                    @error('turnstileToken')
                        <p class="text-xs text-bad sm:col-span-2" role="alert">{{ $message }}</p>
                    @enderror
                @endif

                <div class="sm:col-span-2">
                    <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled" wire:target="submit">
                        <span wire:loading.remove wire:target="submit" class="inline-flex items-center gap-2">
                            <x-lucide-search class="size-5" /> {{ __('site.form.submit') }}
                        </span>
                        <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                            <x-lucide-loader-circle class="size-5 animate-spin" /> {{ __('site.form.submitting') }}
                        </span>
                    </x-ui.button>
                    <p class="mt-3 flex gap-2 text-xs text-subtle">
                        <x-lucide-lock class="mt-0.5 size-3.5 shrink-0" />
                        <span>{{ __('site.form.processing_notice') }} <a class="text-muted underline underline-offset-2 hover:text-fg" href="{{ route('privacy') }}">{{ __('site.form.privacy_link') }}</a>.</span>
                    </p>
                </div>
            </form>
        </div>
    </x-ui.card>
</div>
