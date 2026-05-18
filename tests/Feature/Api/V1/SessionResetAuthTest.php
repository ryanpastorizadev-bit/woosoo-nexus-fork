<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Events\SessionReset;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SessionResetAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Event::fake([SessionReset::class]);
    }

    public function test_admin_user_can_reset_session(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson('/api/sessions/42/reset');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('version', 1);

        $this->assertSame(1, Cache::get('session:42:version'));
        Event::assertDispatched(SessionReset::class);
    }

    public function test_authenticated_device_can_reset_session(): void
    {
        $device = Device::factory()->create(['is_active' => true]);
        $token = $device->createToken('device-auth', expiresAt: now()->addDays(30))->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/sessions/42/reset');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('version', 1);

        $this->assertSame(1, Cache::get('session:42:version'));
        Event::assertDispatched(SessionReset::class);
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)
            ->postJson('/api/sessions/42/reset');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertNull(Cache::get('session:42:version'));
        Event::assertNotDispatched(SessionReset::class);
    }

    public function test_repeated_reset_increments_version(): void
    {
        $device = Device::factory()->create(['is_active' => true]);
        $token = $device->createToken('device-auth', expiresAt: now()->addDays(30))->plainTextToken;

        $first = $this->withToken($token)->postJson('/api/sessions/42/reset');
        $second = $this->withToken($token)->postJson('/api/sessions/42/reset');

        $first->assertJsonPath('version', 1);
        $second->assertJsonPath('version', 2);

        $this->assertSame(2, (int) Cache::get('session:42:version'));
    }
}
