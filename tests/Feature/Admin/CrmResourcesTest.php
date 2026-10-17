<?php

namespace Tests\Feature\Admin;

use App\Enums\ContactMessageStatus;
use App\Enums\LeadStatus;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\ContactMessages\Pages\EditContactMessage;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Analysis;
use App\Models\ContactMessage;
use App\Models\Lead;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

class CrmResourcesTest extends AdminTestCase
{
    public function test_lead_pages_load(): void
    {
        $lead = Lead::factory()->for(Analysis::factory()->completed()->create())->create();

        $this->get(LeadResource::getUrl('index'))->assertOk()->assertSee($lead->name);
        $this->get(LeadResource::getUrl('view', ['record' => $lead]))->assertOk()->assertSee($lead->email);
        $this->get(LeadResource::getUrl('edit', ['record' => $lead]))->assertOk();
    }

    public function test_lead_status_inline_edit_and_notes(): void
    {
        $lead = Lead::factory()->create();

        Livewire::test(ListLeads::class)
            ->call('updateTableColumnState', 'status', (string) $lead->getKey(), LeadStatus::Contacted->value);
        $this->assertSame(LeadStatus::Contacted, $lead->refresh()->status);

        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->fillForm(['status' => LeadStatus::Qualified->value, 'notes' => 'Called, wants a demo.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $lead->refresh();
        $this->assertSame(LeadStatus::Qualified, $lead->status);
        $this->assertSame('Called, wants a demo.', $lead->notes);
    }

    public function test_lead_filter_export_and_delete(): void
    {
        $new = Lead::factory()->create();
        $won = Lead::factory()->create(['status' => LeadStatus::Won]);

        Livewire::test(ListLeads::class)
            ->filterTable('status', LeadStatus::Won->value)
            ->assertCanSeeTableRecords([$won])
            ->assertCanNotSeeTableRecords([$new])
            ->callAction(TestAction::make('exportCsv')->table())
            ->assertFileDownloaded();

        Livewire::test(ListLeads::class)
            ->callAction(TestAction::make('delete')->table($new));
        $this->assertModelMissing($new);
    }

    public function test_contact_message_pages_and_mark_read(): void
    {
        $message = ContactMessage::factory()->create();

        $this->get(ContactMessageResource::getUrl('index'))->assertOk()->assertSee($message->subject);
        $this->get(ContactMessageResource::getUrl('view', ['record' => $message]))->assertOk()->assertSee($message->email);
        $this->assertSame('1', ContactMessageResource::getNavigationBadge());

        Livewire::test(ListContactMessages::class)
            ->callAction(TestAction::make('toggleRead')->table($message));
        $this->assertNotNull($message->refresh()->read_at);
        $this->assertNull(ContactMessageResource::getNavigationBadge());

        Livewire::test(ListContactMessages::class)
            ->callAction(TestAction::make('toggleRead')->table($message));
        $this->assertNull($message->refresh()->read_at);
    }

    public function test_contact_message_status_and_notes(): void
    {
        $message = ContactMessage::factory()->create();

        Livewire::test(EditContactMessage::class, ['record' => $message->getKey()])
            ->fillForm(['status' => ContactMessageStatus::Replied->value, 'notes' => 'Answered by email.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Replied, $message->status);
        $this->assertSame('Answered by email.', $message->notes);
    }
}
