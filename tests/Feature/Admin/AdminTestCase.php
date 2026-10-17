<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');
    }
}
