<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puskesmas', function (Blueprint $table) {
            $table->string('contact')->nullable()->after('address');
        });

        Schema::table('recording_sessions', function (Blueprint $table) {
            $table->uuid('session_uuid')->nullable()->unique()->after('id');
        });

        Schema::create('device_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('puskesmas_id')->constrained('puskesmas')->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status', 'unassigned_at']);
            $table->index(['puskesmas_id', 'status', 'unassigned_at']);
        });

        $now = now();
        DB::table('devices')
            ->whereNotNull('puskesmas_id')
            ->orderBy('id')
            ->chunkById(100, function ($devices) use ($now): void {
                $rows = $devices->map(fn ($device) => [
                    'device_id' => $device->id,
                    'puskesmas_id' => $device->puskesmas_id,
                    'assigned_at' => $device->created_at ?? $now,
                    'unassigned_at' => null,
                    'status' => 'active',
                    'notes' => 'Migrasi relasi alat lama',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('device_assignments')->insert($rows);
            });

        DB::table('recording_sessions')
            ->whereNull('session_uuid')
            ->orderBy('id')
            ->chunkById(100, function ($sessions): void {
                foreach ($sessions as $session) {
                    DB::table('recording_sessions')
                        ->where('id', $session->id)
                        ->update(['session_uuid' => (string) Str::uuid()]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_assignments');

        Schema::table('recording_sessions', function (Blueprint $table) {
            $table->dropUnique(['session_uuid']);
            $table->dropColumn('session_uuid');
        });

        Schema::table('puskesmas', function (Blueprint $table) {
            $table->dropColumn('contact');
        });
    }
};
