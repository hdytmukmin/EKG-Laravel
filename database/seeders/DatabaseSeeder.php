<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $sites = collect([
            ['name' => 'Puskesmas 1', 'code' => 'PKM-001', 'address' => 'Alamat Puskesmas 1'],
            ['name' => 'Puskesmas 2', 'code' => 'PKM-002', 'address' => 'Alamat Puskesmas 2'],
            ['name' => 'Puskesmas 3', 'code' => 'PKM-003', 'address' => 'Alamat Puskesmas 3'],
        ])->map(fn (array $site) => Puskesmas::query()->updateOrCreate(['code' => $site['code']], $site));

        User::query()->updateOrCreate(['email' => 'superadmin@ekg.local'], [
            'name' => 'Super Admin',
            'role' => 'super_admin',
            'puskesmas_id' => null,
            'password' => Hash::make('password'),
        ]);

        $sites->each(function (Puskesmas $site, int $index) {
            Device::query()->updateOrCreate(
                ['device_uid' => 'EKG-DEVICE-'.($index + 1)],
                [
                    'puskesmas_id' => $site->id,
                    'name' => 'Alat EKG '.($index + 1),
                    'mqtt_client_id' => 'ekg-device-'.($index + 1),
                    'status' => 'active',
                    'last_seen_at' => now()->subMinutes($index * 7),
                    'topic_map' => [
                        'subject' => 'building/subjek',
                        'interval_pt' => 'building/tspt',
                        'bpm' => 'building/bpm',
                        'rr' => 'building/RR',
                        'rr_lokal' => 'building/rrlokal',
                        'heart_rate' => 'building/hrr',
                        'raw' => 'building/rawdata',
                    ],
                ]
            );

            User::query()->updateOrCreate(['email' => 'admin'.($index + 1).'@ekg.local'], [
                'name' => 'Admin '.$site->name,
                'role' => 'admin_puskesmas',
                'puskesmas_id' => $site->id,
                'password' => Hash::make('password'),
            ]);
        });

        $this->call(LegacyEcgCsvSeeder::class);
    }
}
