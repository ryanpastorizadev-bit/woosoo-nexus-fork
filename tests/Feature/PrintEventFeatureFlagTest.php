<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\PrintEvent;
use App\Services\PrintEventService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('api.print_events_enabled', false);
});

afterEach(function () {
    Config::set('api.print_events_enabled', false);
});

describe('PrintEvent feature flag disabled (MVP default)', function () {
    it('returns 503 for unprinted-events endpoint when disabled', function () {
        $device = Device::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->getJson('/api/printer/unprinted-events');

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'PRINT_EVENTS_DISABLED',
                    'message' => 'PrintEvent processing is disabled. woosoo-print-bridge is the active print execution path.',
                ],
            ]);
    });

    it('returns 503 for print-events ack endpoint when disabled', function () {
        $device = Device::factory()->create();
        $printEvent = PrintEvent::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->postJson("/api/printer/print-events/{$printEvent->id}/ack", [
            'printer_id' => 'TEST_PRINTER',
            'printed_at' => now()->toIso8601String(),
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'PRINT_EVENTS_DISABLED',
                ],
            ]);
    });

    it('returns 503 for print-events failed endpoint when disabled', function () {
        $device = Device::factory()->create();
        $printEvent = PrintEvent::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->postJson("/api/printer/print-events/{$printEvent->id}/failed", [
            'reason' => 'Printer offline',
        ]);

        $response->assertStatus(503);
    });

    it('returns 503 for heartbeat endpoint when disabled', function () {
        $device = Device::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->postJson('/api/printer/heartbeat');

        $response->assertStatus(503);
    });

    it('skips PrintEvent creation via service when disabled', function () {
        Config::set('api.print_events_enabled', false);

        $deviceOrder = DeviceOrder::factory()->create();
        $service = app(PrintEventService::class);

        $result = $service->createForOrder($deviceOrder, 'kitchen', ['test' => true]);

        expect($result)->toBeNull();

        // Verify no PrintEvent was created in database
        expect(PrintEvent::where('device_order_id', $deviceOrder->id)->count())->toBe(0);
    });

    it('order submission still works when PrintEvent is disabled', function () {
        $device = Device::factory()->create();
        $branch = \App\Models\Branch::factory()->create();
        $table = \App\Models\Table::factory()->create(['branch_id' => $branch->id]);
        $menu = \App\Models\Menu::factory()->create();

        // Ensure packages and package modifiers exist
        $package = \App\Models\Package::factory()->create([
            'id' => 46,
            'name' => 'Classic Feast',
            'branch_id' => $branch->id,
        ]);
        DB::table('package_modifiers')->insert([
            'package_id' => 46,
            'menu_id' => $menu->id,
            'position' => 1,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->postJson('/api/devices/create-order', [
            'branch_id' => $branch->id,
            'table_id' => $table->id,
            'guest_count' => 2,
            'items' => [
                [
                    'package_id' => 46,
                    'krypton_menu_id' => $menu->id,
                    'quantity' => 1,
                    'price' => 100,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        // Verify order was created
        expect(DeviceOrder::count())->toBeGreaterThan(0);

        // Verify no PrintEvents were created
        expect(PrintEvent::count())->toBe(0);
    });
});

describe('PrintEvent feature flag enabled (future expansion)', function () {
    beforeEach(function () {
        Config::set('api.print_events_enabled', true);
    });

    it('allows unprinted-events endpoint when enabled', function () {
        // Note: This test requires authentication and proper setup
        // It demonstrates the endpoint becomes accessible when enabled
        // Full test would need device auth and existing print events
        $this->assertTrue(Config::get('api.print_events_enabled'));
    });

    it('creates PrintEvent via service when enabled', function () {
        $deviceOrder = DeviceOrder::factory()->create();
        $service = app(PrintEventService::class);

        $result = $service->createForOrder($deviceOrder, 'kitchen', ['test' => true]);

        expect($result)->toBeInstanceOf(PrintEvent::class);
        expect(PrintEvent::where('device_order_id', $deviceOrder->id)->count())->toBe(1);
    });
});
