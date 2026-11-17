<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\RecordingSession;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class FacilityDeviceManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite extension is not available in this environment.');
        }

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_only_super_admin_can_register_a_device(): void
    {
        $facility = $this->createFacility('FAC-ONE');
        $admin = $this->createUser('admin_puskesmas', $facility);

        $this->actingAs($admin)
            ->post(route('devices.store'), $this->devicePayload($facility))
            ->assertForbidden();

        $this->assertDatabaseMissing('devices', ['device_uid' => 'EKG-TEST-001']);
    }

    public function test_super_admin_can_register_device_for_multiple_facilities(): void
    {
        $first = $this->createFacility('FAC-ONE');
        $second = $this->createFacility('FAC-TWO');
        $superAdmin = $this->createUser('super_admin');

        $payload = $this->devicePayload($first);
        $payload['puskesmas_ids'] = [$first->id, $second->id];

        $this->actingAs($superAdmin)
            ->post(route('devices.store'), $payload)
            ->assertRedirect(route('devices.index'));

        $device = Device::query()->where('device_uid', 'EKG-TEST-001')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $device->activeAssignments()->pluck('puskesmas_id')->all()
        );
    }

    public function test_admin_can_only_open_own_facility(): void
    {
        $ownFacility = $this->createFacility('FAC-OWN');
        $otherFacility = $this->createFacility('FAC-OTHER');
        $admin = $this->createUser('admin_puskesmas', $ownFacility);

        $this->actingAs($admin)
            ->get(route('facilities.show', $ownFacility))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('facilities.show', $otherFacility))
            ->assertNotFound();
    }

    public function test_recording_session_gets_server_generated_uuid(): void
    {
        $facility = $this->createFacility('FAC-UUID');
        $patient = Patient::query()->create([
            'puskesmas_id' => $facility->id,
            'name' => 'Pasien UUID',
        ]);
        $device = Device::query()->create([
            'puskesmas_id' => $facility->id,
            'name' => 'Alat UUID',
            'device_uid' => 'EKG-UUID-001',
            'status' => 'unknown',
        ]);

        $session = RecordingSession::query()->create([
            'puskesmas_id' => $facility->id,
            'device_id' => $device->id,
            'patient_id' => $patient->id,
            'recorded_at' => now(),
            'status' => 'completed',
            'source' => 'test',
        ]);

        $this->assertNotEmpty($session->session_uuid);
        $this->assertDatabaseHas('recording_sessions', [
            'id' => $session->id,
            'session_uuid' => $session->session_uuid,
        ]);
    }

    public function test_super_admin_must_select_an_existing_facility_when_creating_patient(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $this->actingAs($superAdmin)
            ->post(route('patients.store'), ['name' => 'Pasien tanpa fasilitas'])
            ->assertSessionHasErrors('puskesmas_id');

        $this->assertDatabaseMissing('patients', ['name' => 'Pasien tanpa fasilitas']);
    }

    public function test_patient_is_created_in_the_facility_selected_by_super_admin(): void
    {
        $facility = $this->createFacility('FAC-PATIENT');
        $superAdmin = $this->createUser('super_admin');

        $this->actingAs($superAdmin)
            ->post(route('patients.store'), [
                'puskesmas_id' => $facility->id,
                'name' => 'Pasien fasilitas',
                'age' => 45,
                'gender' => 'Laki-laki',
                'address' => 'Alamat pasien',
            ])
            ->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('patients', [
            'puskesmas_id' => $facility->id,
            'name' => 'Pasien fasilitas',
        ]);
    }

    private function createFacility(string $code): Puskesmas
    {
        return Puskesmas::query()->create([
            'name' => 'Fasilitas '.$code,
            'code' => $code,
            'address' => 'Alamat pengujian',
            'contact' => '081234567890',
        ]);
    }

    private function createUser(string $role, ?Puskesmas $facility = null): User
    {
        return User::query()->create([
            'name' => 'User '.$role,
            'email' => $role.'-'.uniqid().'@ekg.test',
            'password' => 'password',
            'role' => $role,
            'puskesmas_id' => $facility?->id,
        ]);
    }

    private function devicePayload(Puskesmas $facility): array
    {
        return [
            'puskesmas_ids' => [$facility->id],
            'name' => 'Alat Uji',
            'device_uid' => 'EKG-TEST-001',
            'mqtt_client_id' => 'ekg-test-client',
            'operational_mode' => 'normal',
        ];
    }
}
