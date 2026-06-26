<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\RecordingSession;
use App\Models\User;
use Tests\TestCase;

class AccessScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite extension is not available in this environment.');
        }
    }

    public function test_admin_puskesmas_cannot_open_other_puskesmas_patient_by_url(): void
    {
        [$admin, $patient] = $this->makeCrossPuskesmasData();

        $this->actingAs($admin)
            ->get(route('patients.show', $patient))
            ->assertNotFound();
    }

    public function test_admin_puskesmas_cannot_open_other_puskesmas_recording_by_url(): void
    {
        [$admin, $patient, $session] = $this->makeCrossPuskesmasData();

        $this->actingAs($admin)
            ->get(route('recordings.show', $session))
            ->assertNotFound();
    }

    public function test_admin_puskesmas_cannot_read_other_puskesmas_recording_chart_api(): void
    {
        [$admin, $patient, $session] = $this->makeCrossPuskesmasData();

        $this->actingAs($admin)
            ->getJson(route('recordings.chart-data', $session))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Patient, 2: RecordingSession}
     */
    private function makeCrossPuskesmasData(): array
    {
        $suffix = uniqid();

        $puskesmasA = Puskesmas::query()->create([
            'name' => 'Puskesmas Test A '.$suffix,
            'code' => 'TEST-A-'.$suffix,
        ]);

        $puskesmasB = Puskesmas::query()->create([
            'name' => 'Puskesmas Test B '.$suffix,
            'code' => 'TEST-B-'.$suffix,
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Test '.$suffix,
            'email' => 'admin-test-'.$suffix.'@ekg.local',
            'password' => 'password',
            'role' => 'admin_puskesmas',
            'puskesmas_id' => $puskesmasA->id,
        ]);

        $patient = Patient::query()->create([
            'puskesmas_id' => $puskesmasB->id,
            'name' => 'Pasien Scope Test '.$suffix,
            'age' => 50,
            'gender' => 'Perempuan',
            'address' => 'Alamat test',
        ]);

        $device = Device::query()->create([
            'puskesmas_id' => $puskesmasB->id,
            'name' => 'Alat Scope Test '.$suffix,
            'device_uid' => 'DEVICE-TEST-'.$suffix,
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        $session = RecordingSession::query()->create([
            'puskesmas_id' => $puskesmasB->id,
            'device_id' => $device->id,
            'patient_id' => $patient->id,
            'recorded_at' => now(),
            'status' => 'completed',
            'source' => 'test',
        ]);

        return [$admin, $patient, $session];
    }
}
