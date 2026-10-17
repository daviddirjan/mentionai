<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/analyses')->assertRedirect('/admin/login');
    }

    public function test_login_page_is_brand_labelled(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('MentionAI');
    }

    public function test_non_admin_is_denied(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_inactive_admin_is_denied(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_active_admin_sees_the_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($user)->get('/admin')->assertOk()->assertSee('Dashboard');
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true, 'last_login_at' => null]);

        event(new Login('web', $user, false));

        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_login_form_signs_in_active_admin_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true, 'password' => 'secret-password']);
        $inactive = User::factory()->create(['is_admin' => true, 'is_active' => false, 'password' => 'secret-password']);

        Livewire::test(\Filament\Auth\Pages\Login::class)
            ->fillForm(['email' => $inactive->email, 'password' => 'secret-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        $this->assertGuest();

        Livewire::test(\Filament\Auth\Pages\Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'secret-password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->refresh()->last_login_at);
    }

    public function test_registration_is_not_available(): void
    {
        $this->get('/admin/register')->assertNotFound();
    }
}
