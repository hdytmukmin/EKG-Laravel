<?php

namespace Tests\Unit;

use App\Models\Device;
use Tests\TestCase;

class DeviceStatusTest extends TestCase
{
    public function test_device_is_online_when_recently_seen(): void
    {
        config(['monitoring.device_offline_after_minutes' => 2]);

        $device = new Device([
            'status' => 'unknown',
            'last_seen_at' => now()->subMinute(),
        ]);

        $this->assertSame('online', $device->effectiveStatus());
    }

    public function test_device_is_offline_when_last_seen_is_stale_or_missing(): void
    {
        config(['monitoring.device_offline_after_minutes' => 2]);

        $staleDevice = new Device([
            'status' => 'online',
            'last_seen_at' => now()->subMinutes(3),
        ]);
        $neverSeenDevice = new Device(['status' => 'unknown']);

        $this->assertSame('offline', $staleDevice->effectiveStatus());
        $this->assertSame('offline', $neverSeenDevice->effectiveStatus());
    }

    public function test_maintenance_status_overrides_recent_activity(): void
    {
        $device = new Device([
            'status' => 'maintenance',
            'last_seen_at' => now(),
        ]);

        $this->assertSame('maintenance', $device->effectiveStatus());
        $this->assertSame('Pemeliharaan', $device->statusLabel());
    }
}
