<div id="analyze" class="scroll-mt-24">
    <x-ui.card class="p-6 shadow-[0_1px_0_rgba(27,48,48,0.04),0_24px_48px_-24px_rgba(27,48,48,0.18)] sm:p-8">
        <h2 class="text-2xl font-semibold text-fg">{{ __('site.form.title') }}</h2>
        <p class="mt-1 text-muted">{{ __('site.form.subtitle') }}</p>

        @if ($rejection)
            <div class="mt-5 flex gap-3 rounded-xl border border-warn/30 bg-warn/10 p-4 text-sm" role="alert" data-testid="rejection">
                <x-lucide-circle-alert class="mt-0.5 size-5 shrink-0 text-warn" />
                <div>
                    <p class="font-medium text-fg">{{ __('site.form.rejected_title') }}</p>
                    <p class="mt-1 text-muted">{{ __('analysis.rejected.'.$rejection) }}</p>
                    @if ($existingUuid)
                        <a class="mt-2 inline-flex items-center gap-1 font-medium text-fg underline decoration-signal underline-offset-4" href="{{ route('analysis.show', ['analysis' => $existingUuid]) }}">
                            {{ __('site.form.view_existing') }} <x-lucide-arrow-right class="size-4" />
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <form wire:submit="submit" class="mt-6 space-y-4" novalidate>
            <x-ui.input name="websiteUrl" wire:model="websiteUrl" :label="__('site.form.website')" :placeholder="__('site.form.website_placeholder')" required type="text" inputmode="url" autocomplete="url" />
            <x-ui.input name="companyName" wire:model="companyName" :label="__('site.form.company')" :placeholder="__('site.form.company_placeholder')" required maxlength="120" autocomplete="organization" />
            <x-ui.select name="market" wire:model.live="market" :label="__('site.form.market')" :options="$countries" :placeholder="__('site.form.market_placeholder')" required />
            <x-ui.input name="location" wire:model="location" :label="__('site.form.location').' ('.__('site.common.optional').')'" :placeholder="__('site.form.location_placeholder')" maxlength="120" autocomplete="address-level2" />

            <div x-data="{ open: @js($errors->hasAny(['industry', 'language'])) }" class="border-t border-line pt-3">
                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-controls="more-options"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-fg">
                    <x-lucide-sliders-horizontal class="size-4" />
                    {{ __('site.form.more_options') }}
                    <x-lucide-chevron-down class="size-4 transition" x-bind:class="open && 'rotate-180'" />
                </button>
                <div id="more-options" x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     class="mt-4 space-y-4">
                    <x-ui.input name="industry" wire:model="industry" :label="__('site.form.industry').' ('.__('site.common.optional').')'" :placeholder="__('site.form.industry_placeholder')" maxlength="120" />
                    <x-ui.select name="language" wire:model.live="language" :label="__('site.form.language')" :options="$languages" required />
                </div>
            </div>

            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label>Nickname <input type="text" tabindex="-1" autocomplete="off" wire:model="nickname"></label>
            </div>

            @if (config('mentionai.turnstile.site_key'))
                <div wire:ignore
                     x-data="{ id: null }"
                     x-init="const mount = () => { if (!window.turnstile) { return setTimeout(mount, 200); } id = window.turnstile.render($refs.box, { sitekey: @js(config('mentionai.turnstile.site_key')), callback: (t) => $wire.set('turnstileToken', t), 'expired-callback': () => $wire.set('turnstileToken', '') }); }; mount();"
                     x-on:turnstile-reset.window="window.turnstile && id !== null && window.turnstile.reset(id)">
                    <div x-ref="box"></div>
                </div>
                @error('turnstileToken')
                    <p class="text-sm text-bad" role="alert">{{ $message }}</p>
                @enderror
            @endif

            <div class="pt-1">
                <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit" class="inline-flex items-center gap-2">
                        {{ __('site.form.submit') }} <x-lucide-arrow-right class="size-5" />
                    </span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                        <x-lucide-loader-circle class="size-5 animate-spin" /> {{ __('site.form.submitting') }}
                    </span>
                </x-ui.button>
                <p class="mt-3 flex gap-2 text-xs leading-relaxed text-muted">
                    <x-lucide-lock class="mt-0.5 size-3.5 shrink-0" />
                    <span>{{ __('site.form.processing_notice') }} <a class="underline underline-offset-2 hover:text-fg" href="{{ route('privacy') }}">{{ __('site.form.privacy_link') }}</a></span>
                </p>
            </div>
        </form>
    </x-ui.card>
</div>
