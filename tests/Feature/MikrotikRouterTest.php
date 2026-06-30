<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MikrotikRouterTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_mikrotik_router(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/dashboard/mikrotik', [
            'name' => 'Core Router',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'description' => 'Main gateway',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/mikrotik');

        $this->assertDatabaseHas('mikrotiks', [
            'name' => 'Core Router',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'description' => 'Main gateway',
        ]);
    }
}
