<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiCsrfExemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable CSRF for testing
        config(['session.driver' => 'file']);
        config(['session.encrypt' => false]);
    }

    /** @test */
    public function it_requires_csrf_for_session_authenticated_api_routes()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // Test a session-authenticated endpoint (should require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('/api/sessions/1/reset');

        // Should return 419 CSRF token mismatch for session auth
        $response->assertStatus(419);
    }

    /** @test */
    public function it_exempts_device_bearer_endpoints_from_csrf()
    {
        $device = Device::factory()->create([
            'security_code' => 'TEST123',
            'status' => 'active'
        ]);

        // Test device Bearer token endpoint (should not require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->post('/api/devices/refresh');

        // Should not return 419 for Bearer token auth
        $response->assertStatus(200);
    }

    /** @test */
    public function it_exempts_device_registration_endpoints_from_csrf()
    {
        // Test device registration (no auth required)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('/api/devices/register', [
            'branch_id' => 1,
            'security_code' => 'TEST123',
            'name' => 'Test Device',
            'ip_address' => '192.168.1.100'
        ]);

        // Should not return 419 for device registration
        $response->assertStatus(201);
    }

    /** @test */
    public function it_exempts_device_login_endpoints_from_csrf()
    {
        Device::factory()->create([
            'security_code' => 'TEST123',
            'status' => 'active'
        ]);

        // Test device login (no auth required)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->get('/api/devices/login', [
            'security_code' => 'TEST123'
        ]);

        // Should not return 419 for device login
        $response->assertStatus(200);
    }

    /** @test */
    public function it_exempts_public_menu_endpoints_from_csrf()
    {
        // Test public menu endpoint (no auth required)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->get('/api/menus');

        // Should not return 419 for public endpoints
        $response->assertStatus(200);
    }

    /** @test */
    public function it_exempts_printer_endpoints_from_csrf()
    {
        $device = Device::factory()->create([
            'security_code' => 'TEST123',
            'status' => 'active'
        ]);

        // Enable print events for testing
        config(['api.print_events_enabled' => true]);

        // Test printer endpoint (should not require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->get('/api/printer/unprinted-events');

        // Should not return 419 for printer endpoints
        $this->assertContains($response->getStatusCode(), [200, 503]); // 503 if feature disabled
    }

    /** @test */
    public function it_requires_auth_for_exempted_endpoints()
    {
        // Test that exempted endpoints still require authentication
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('/api/devices/refresh');

        // Should return 401/403, not 419 (no CSRF error)
        $response->assertStatus(401);
    }

    /** @test */
    public function it_exempts_v2_tablet_endpoints_from_csrf()
    {
        $device = Device::factory()->create([
            'security_code' => 'TEST123',
            'status' => 'active'
        ]);

        // Test V2 tablet endpoint (should not require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->get('/api/v2/tablet/packages');

        // Should not return 419 for V2 tablet endpoints
        $response->assertStatus(200);
    }

    /** @test */
    public function it_exempts_device_order_endpoints_from_csrf()
    {
        $device = Device::factory()->create([
            'security_code' => 'TEST123',
            'status' => 'active'
        ]);

        // Test device order endpoint (should not require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->get('/api/device-orders');

        // Should not return 419 for device order endpoints
        $response->assertStatus(200);
    }

    /** @test */
    public function it_exempts_order_refill_endpoints_from_csrf()
    {
        $device = Device::factory()->create([
            'security_code' => 'TEST123',
            'status' => 'active'
        ]);

        // Test order refill endpoint (should not require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->post('/api/order/123/refill');

        // Should not return 419 for order refill endpoints
        $this->assertContains($response->getStatusCode(), [404, 422]); // 404 if order not found, 422 if validation fails
    }

    /** @test */
    public function it_exempts_public_health_endpoints_from_csrf()
    {
        // Test health endpoint (no auth required)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->get('/api/health');

        // Should not return 419 for health endpoint
        $response->assertStatus(200);
    }

    /** @test */
    public function it_does_not_exempt_admin_sanctum_endpoints_from_csrf()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // Test admin Sanctum endpoint (should require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->get('/api/v2/devices');

        // Should return 419 for session/Sanctum auth
        $response->assertStatus(419);
    }
}
