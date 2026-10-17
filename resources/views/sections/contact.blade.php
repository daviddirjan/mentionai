<section id="contact" class="border-t border-line bg-bg-soft">
    <div class="mx-auto max-w-2xl px-4 py-20 sm:px-6">
        <x-ui.section-header :eyebrow="__('site.contact.eyebrow')" :title="__('site.contact.title')" :subtitle="__('site.contact.subtitle')" />
        <x-ui.card class="mt-10 p-6 sm:p-8">
            <livewire:contact-form />
        </x-ui.card>
    </div>
</section>
