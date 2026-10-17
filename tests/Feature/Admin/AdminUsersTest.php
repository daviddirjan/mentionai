<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

class AdminUsersTest extends AdminTestCase
{
    public function test_pages_load(): void
    {
        $other = User::factory()->create(['is_admin' => true]);

        $this->get(UserResource::getUrl('index'))->assertOk()->assertSee($other->email);
        $this->get(UserResource::getUrl('create'))->assertOk();
        $this->get(UserResource::getUrl('edit', ['record' => $other]))->assertOk();
    }

    public function test_create_admin_hashes_password_and_forces_admin(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Admin',
                'email' => 'new@example.com',
                'password' => 'a-very-strong-pass',
                'password_confirmation' => 'a-very-strong-pass',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::firstWhere('email', 'new@example.com');
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->is_active);
        $this->assertNotSame('a-very-strong-pass', $user->password);
        $this->assertTrue(Hash::check('a-very-strong-pass', $user->password));
    }

    public function test_password_confirmation_is_enforced(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'X', 'email' => 'x@example.com', 'password' => 'a-very-strong-pass', 'password_confirmation' => 'different-pass-1'])
            ->call('create')
            ->assertHasFormErrors(['password' => 'confirmed']);
    }

    public function test_admin_can_deactivate_another_admin(): void
    {
        $other = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        Livewire::test(EditUser::class, ['record' => $other->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($other->refresh()->is_active);
        $this->assertFalse($other->canAccessPanel(\Filament\Facades\Filament::getCurrentPanel()));
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        Livewire::test(EditUser::class, ['record' => $this->admin->getKey()])
            ->assertFormFieldDisabled('is_active');

        Livewire::test(ListUsers::class)
            ->call('updateTableColumnState', 'is_active', (string) $this->admin->getKey(), false);

        $this->assertTrue($this->admin->refresh()->is_active);
    }

    public function test_create_admin_command_with_options(): void
    {
        $this->artisan('mentionai:create-admin', ['--name' => 'CLI Admin', '--email' => 'cli@example.com', '--password' => 'a-very-strong-pass'])
            ->assertSuccessful();

        $user = User::firstWhere('email', 'cli@example.com');
        $this->assertTrue($user->is_admin && $user->is_active);
        $this->assertTrue(Hash::check('a-very-strong-pass', $user->password));
    }

    public function test_create_admin_command_validates_input(): void
    {
        $this->artisan('mentionai:create-admin', ['--name' => 'A', '--email' => 'not-an-email', '--password' => 'short', '--no-interaction' => true])
            ->assertFailed();

        $this->artisan('mentionai:create-admin', ['--name' => 'Dup', '--email' => $this->admin->email, '--password' => 'a-very-strong-pass', '--no-interaction' => true])
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }
}
