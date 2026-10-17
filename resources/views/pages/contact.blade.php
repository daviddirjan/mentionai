<x-layouts.app :title="__('site.meta.contact_title')" :description="__('site.meta.contact_description')">
    <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <x-ui.section-header :eyebrow="__('site.contact.eyebrow')" :title="__('site.contact.title')" :subtitle="__('site.contact.subtitle')" />
        <x-ui.card class="mt-10 p-6 sm:p-8">
            <livewire:contact-form />
        </x-ui.card>
    </div>
</x-layouts.app>
