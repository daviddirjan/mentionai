<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class WebsiteSettings extends SettingsFormPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Website settings';

    protected static ?string $slug = 'website-settings';

    protected function keys(): array
    {
        return [
            'site_name', 'tagline', 'logo_path', 'favicon_path', 'contact_email', 'company_info', 'social_links',
            'seo_title', 'meta_description', 'default_locale', 'report_price', 'report_currency',
            'maintenance_mode', 'data_retention_days',
        ];
    }

    protected function fields(): array
    {
        $locales = collect(config('mentionai.locales'))->mapWithKeys(fn (string $l) => [$l => strtoupper($l)])->all();

        return [
            Section::make('Branding')->columns(2)->schema([
                TextInput::make('site_name')->required()->maxLength(80),
                TextInput::make('tagline')->maxLength(160),
                FileUpload::make('logo_path')->label('Logo')->image()->disk('public')->directory('branding')->visibility('public')->maxSize(2048),
                FileUpload::make('favicon_path')->label('Favicon')->image()->disk('public')->directory('branding')->visibility('public')->maxSize(512),
            ]),
            Section::make('Company')->columns(2)->schema([
                TextInput::make('contact_email')->email()->required()->maxLength(255),
                Textarea::make('company_info')->rows(3)->maxLength(1000),
                KeyValue::make('social_links')->keyLabel('Network')->valueLabel('URL')->columnSpanFull()
                    ->helperText('For example: linkedin = https://linkedin.com/company/...'),
            ]),
            Section::make('SEO')->columns(1)->schema([
                TextInput::make('seo_title')->maxLength(70)->helperText('Up to about 60 characters is ideal.'),
                Textarea::make('meta_description')->rows(2)->maxLength(200)->helperText('Up to about 160 characters is ideal.'),
            ]),
            Section::make('Site')->columns(2)->schema([
                Select::make('default_locale')->options($locales)->required()->native(false),
                TextInput::make('data_retention_days')->numeric()->integer()->minValue(1)->required()->suffix('days'),
                TextInput::make('report_price')->label('Report price (RON)')->numeric()->minValue(0)->step(0.01)->required()->prefix('RON')
                    ->helperText('Shown in major units; stored in minor units (bani). Used once payments launch.'),
                TextInput::make('report_currency')->required()->length(3)->helperText('ISO 4217 code, e.g. RON.'),
                Toggle::make('maintenance_mode')->helperText('Shows a maintenance notice on the public site.'),
            ]),
        ];
    }

    protected function fromSettings(array $values): array
    {
        $values['report_price'] = round(((int) ($values['report_price'] ?? 0)) / 100, 2);
        $values['social_links'] = $values['social_links'] ?? [];

        return $values;
    }

    protected function toSettings(array $state): array
    {
        $values = parent::toSettings($state);
        $values['report_price'] = (int) round(((float) $values['report_price']) * 100);
        $values['report_currency'] = strtoupper((string) $values['report_currency']);
        $values['data_retention_days'] = (int) $values['data_retention_days'];
        $values['maintenance_mode'] = (bool) $values['maintenance_mode'];
        $values['social_links'] = $values['social_links'] ?? [];

        return $values;
    }
}
