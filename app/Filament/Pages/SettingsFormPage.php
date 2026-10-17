<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

/** A page holding one form whose values are stored through App\Support\Settings. */
abstract class SettingsFormPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @return list<Component> */
    abstract protected function fields(): array;

    /** Setting keys edited by this page. */
    abstract protected function keys(): array;

    /** Maps validated form state to the values to persist. */
    protected function toSettings(array $state): array
    {
        return collect($this->keys())->mapWithKeys(fn (string $key) => [$key => $state[$key] ?? null])->all();
    }

    /** Maps stored settings to form state. */
    protected function fromSettings(array $values): array
    {
        return $values;
    }

    public function mount(): void
    {
        $this->form->fill($this->fromSettings(collect($this->keys())->mapWithKeys(fn (string $key) => [$key => settings($key)])->all()));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->fields())->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save settings')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        settings()->setMany($this->toSettings($this->form->getState()));

        Notification::make()->success()->title('Settings saved')->send();
    }
}
