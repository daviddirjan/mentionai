<div>
    @if ($mode !== 'expert')
        @if ($rejection)
            <div class="mb-4 flex gap-3 rounded-xl border border-warn/30 bg-warn/10 p-4 text-sm" role="alert" data-testid="audit-rejection">
                <x-lucide-circle-alert class="mt-0.5 size-5 shrink-0 text-warn" />
                <p class="text-fg">{{ __('analysis.rejected.'.$rejection) }}</p>
            </div>
        @endif

        <form wire:submit="submit" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end" novalidate data-testid="audit-form">
            <x-ui.input name="email" wire:model="email" type="email" :label="__('site.lead.email')" required autocomplete="email" placeholder="you@company.com" />
            <x-ui.input name="name" wire:model="name" :label="__('site.lead.name').' ('.__('site.common.optional').')'" autocomplete="name" maxlength="120" />

            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label>Nickname <input type="text" tabindex="-1" autocomplete="off" wire:model="nickname"></label>
            </div>

            <x-ui.button type="submit" size="lg" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit" class="inline-flex items-center gap-2">
                    {{ $mode === 'audit' ? __('site.lead.audit_submit') : __('site.lead.unlock_submit') }} <x-lucide-arrow-right class="size-4" />
                </span>
                <span wire:loading wire:target="submit" class="inline-flex items-center gap-2"><x-lucide-loader-circle class="size-4 animate-spin" /> {{ __('site.lead.audit_sending') }}</span>
            </x-ui.button>

            @error('form')
                <p class="text-sm text-bad sm:col-span-3" role="alert">{{ $message }}</p>
            @enderror

            <p class="text-xs leading-relaxed text-muted sm:col-span-3">{{ __('site.lead.audit_consent') }}
                <a class="underline underline-offset-2 hover:text-fg" href="{{ route('privacy') }}">{{ __('site.form.privacy_link') }}</a>
            </p>
        </form>
    @elseif ($sent)
        <div class="flex gap-4 rounded-card border border-good/30 bg-good/10 p-6" role="status" data-testid="lead-success">
            <x-lucide-circle-check class="size-6 shrink-0 text-good" />
            <div>
                <p class="font-semibold text-fg">{{ __('site.lead.success_title') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('site.lead.success_text') }}</p>
            </div>
        </div>
    @else
        <form wire:submit="submit" class="grid gap-4 sm:grid-cols-2" novalidate>
            <x-ui.input name="name" wire:model="name" :label="__('site.lead.name')" required autocomplete="name" />
            <x-ui.input name="company" wire:model="company" :label="__('site.lead.company')" autocomplete="organization" />
            <x-ui.input name="email" wire:model="email" type="email" :label="__('site.lead.email')" required autocomplete="email" />
            <x-ui.input name="phone" wire:model="phone" type="tel" :label="__('site.lead.phone').' ('.__('site.common.optional').')'" autocomplete="tel" />
            <div class="sm:col-span-2">
                <x-ui.input name="website" wire:model="website" :label="__('site.lead.website')" autocomplete="url" />
            </div>
            <div class="sm:col-span-2">
                <x-ui.textarea name="message" wire:model="message" :label="__('site.lead.message')" required rows="4" />
            </div>

            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label>Nickname <input type="text" tabindex="-1" autocomplete="off" wire:model="nickname"></label>
            </div>

            @error('form')
                <p class="text-sm text-bad sm:col-span-2" role="alert">{{ $message }}</p>
            @enderror

            <div class="sm:col-span-2">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit" class="inline-flex items-center gap-2"><x-lucide-mail class="size-4" /> {{ __('site.lead.send') }}</span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center gap-2"><x-lucide-loader-circle class="size-4 animate-spin" /> {{ __('site.lead.sending') }}</span>
                </x-ui.button>
                <p class="mt-3 text-xs text-muted">{{ __('site.contact.privacy') }}
                    <a class="underline underline-offset-2 hover:text-fg" href="{{ route('privacy') }}">{{ __('site.form.privacy_link') }}</a>
                </p>
            </div>
        </form>
    @endif
</div>
