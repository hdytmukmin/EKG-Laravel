<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\EkgFeature;
use App\Models\EkgRawSignal;
use App\Models\LegacyImport;
use App\Models\Patient;
use App\Models\Prediction;
use App\Models\Puskesmas;
use App\Models\RecordingSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncLegacyIotCommand extends Command
{
    protected $signature = 'ekg:sync-legacy-iot
        {--source=all : Source table: all, ecg_data, or recordekg}
        {--limit=100 : Maximum rows per source per run}
        {--dry-run : Show rows that would be imported without writing}
        {--loop : Keep syncing in a loop}
        {--sleep=5 : Sleep seconds between loop runs}
        {--puskesmas-code=PKM-001 : Target puskesmas code for legacy data}
        {--device-uid=EKG-LEGACY-MYSQL : Target device uid for legacy data}';

    protected $description = 'Sync legacy MySQL iot tables into the Laravel EKG AF/Non-AF schema.';

    public function handle(): int
    {
        do {
            $this->syncOnce();

            if ($this->option('loop')) {
                sleep(max(1, (int) $this->option('sleep')));
            }
        } while ($this->option('loop'));

        return self::SUCCESS;
    }

    private function syncOnce(): void
    {
        $source = strtolower((string) $this->option('source'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $sources = match ($source) {
            'ecg_data' => ['ecg_data'],
            'recordekg' => ['recordekg'],
            'all' => ['ecg_data', 'recordekg'],
            default => throw new \InvalidArgumentException('Source harus all, ecg_data, atau recordekg.'),
        };

        $device = $dryRun ? null : $this->resolveDevice();
        $total = 0;

        foreach ($sources as $table) {
            if (! $this->legacyTableExists($table)) {
                $this->warn("Legacy table tidak ditemukan: {$table}");
                continue;
            }

            $rows = DB::connection('legacy')
                ->table($table)
                ->whereNotIn('id', LegacyImport::query()->where('source_table', $table)->pluck('source_id'))
                ->orderBy('id')
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $payload = $table === 'ecg_data'
                    ? $this->mapEcgDataRow($row)
                    : $this->mapRecordEkgRow($row);

                if ($payload === null) {
                    $this->warn("{$table} #{$row->id} dilewati karena payload tidak valid.");
                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '[DRY RUN] %s #%s -> %s | bpm=%s | rr=%s | recorded_at=%s',
                        $table,
                        $row->id,
                        $payload['patient']['name'],
                        $payload['feature']['bpm'] ?? '-',
                        $payload['feature']['rr'] ?? '-',
                        $payload['recorded_at']?->toDateTimeString() ?? '-'
                    ));
                    $total++;
                    continue;
                }

                $session = DB::transaction(function () use ($table, $row, $payload, $device) {
                    return $this->importPayload($table, (int) $row->id, $payload, $device);
                });

                if ($session) {
                    $this->info("Imported {$table} #{$row->id} -> session #{$session->id}");
                    $total++;
                }
            }
        }

        $mode = $dryRun ? 'Dry-run' : 'Sync';
        $this->info("{$mode} selesai. Total diproses: {$total}");
    }

    private function legacyTableExists(string $table): bool
    {
        try {
            return Schema::connection('legacy')->hasTable($table);
        } catch (Throwable $exception) {
            $this->error('Koneksi legacy gagal: '.$exception->getMessage());
            return false;
        }
    }

    private function resolveDevice(): Device
    {
        $puskesmas = Puskesmas::query()->firstOrCreate(
            ['code' => (string) $this->option('puskesmas-code')],
            ['name' => 'Puskesmas 1']
        );

        return Device::query()->updateOrCreate(
            ['device_uid' => (string) $this->option('device-uid')],
            [
                'puskesmas_id' => $puskesmas->id,
                'name' => 'Alat EKG Legacy MySQL',
                'mqtt_client_id' => 'legacy-mysql-sync',
                'status' => 'online',
                'last_seen_at' => now(),
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapEcgDataRow(object $row): ?array
    {
        $name = $this->cleanName($row->nama_subjek ?? null);
        if ($name === null) {
            return null;
        }

        return [
            'patient' => [
                'name' => $name,
                'external_subject_id' => $name,
            ],
            'recorded_at' => $this->parseDate($row->timestamp ?? null),
            'feature' => [
                'subject' => $name,
                'interval_pt' => $this->nullableFloat($row->pt_interval ?? null),
                'bpm' => $this->nullableFloat($row->bpm ?? null),
                'rr' => $this->nullableFloat($row->rata2_rr ?? null),
                'rr_lokal' => $this->nullableFloat($row->rata2_rr_lokal ?? null),
                'status' => trim(implode(' | ', array_filter([
                    $row->status_bpm ?? null,
                    $row->status_pt ?? null,
                ]))) ?: 'legacy_ecg_data',
                'sdnn' => null,
                'sns' => null,
                'heart_rate' => [],
            ],
            'raw_signal' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapRecordEkgRow(object $row): ?array
    {
        $name = $this->cleanName($row->nama ?? null);
        if ($name === null) {
            return null;
        }

        $heartRate = $this->parseNumericSeries($row->hr ?? null);
        $filtered = $this->parseNumericSeries($row->filtered ?? null);

        return [
            'patient' => [
                'name' => $name,
                'age' => $this->nullableInt($row->umur ?? null),
                'gender' => $this->nullableString($row->jk ?? null),
                'address' => $this->nullableString($row->alamat ?? null),
                'external_subject_id' => $name,
            ],
            'recorded_at' => $this->parseDate($row->tglrekam ?? null),
            'feature' => [
                'subject' => $name,
                'interval_pt' => $this->nullableFloat($row->tspt ?? null),
                'bpm' => $this->nullableFloat($row->bpm ?? null),
                'rr' => $this->nullableFloat($row->irr ?? null),
                'rr_lokal' => $this->nullableFloat($row->irrlokal ?? null),
                'status' => 'legacy_recordekg',
                'sdnn' => null,
                'sns' => null,
                'heart_rate' => $heartRate,
            ],
            'raw_signal' => $filtered ? [
                'voltage_values' => $filtered,
                'filtered_values' => $filtered,
                'sample_rate' => (int) env('EKG_SAMPLE_RATE', 250),
                'total_samples' => count($filtered),
            ] : null,
        ];
    }

    private function importPayload(string $sourceTable, int $sourceId, array $payload, Device $device): ?RecordingSession
    {
        if (LegacyImport::query()->where('source_table', $sourceTable)->where('source_id', $sourceId)->exists()) {
            return null;
        }

        $patient = Patient::query()->updateOrCreate(
            [
                'puskesmas_id' => $device->puskesmas_id,
                'name' => $payload['patient']['name'],
            ],
            [
                'age' => $payload['patient']['age'] ?? null,
                'gender' => $payload['patient']['gender'] ?? null,
                'address' => $payload['patient']['address'] ?? null,
                'external_subject_id' => $payload['patient']['external_subject_id'] ?? $payload['patient']['name'],
            ]
        );

        $duplicate = RecordingSession::query()
            ->where('source', 'legacy_iot')
            ->where('patient_id', $patient->id)
            ->when($payload['recorded_at'], fn ($query) => $query->where('recorded_at', $payload['recorded_at']))
            ->whereHas('feature', function ($query) use ($payload) {
                $query->where('bpm', $payload['feature']['bpm']);
            })
            ->first();

        if ($duplicate) {
            LegacyImport::query()->create([
                'source_table' => $sourceTable,
                'source_id' => $sourceId,
                'recording_session_id' => $duplicate->id,
                'imported_at' => now(),
            ]);

            return null;
        }

        $session = RecordingSession::query()->create([
            'puskesmas_id' => $device->puskesmas_id,
            'device_id' => $device->id,
            'patient_id' => $patient->id,
            'recorded_at' => $payload['recorded_at'],
            'status' => 'completed',
            'source' => 'legacy_iot',
            'notes' => "{$sourceTable}:{$sourceId}",
        ]);

        EkgFeature::query()->create([
            'recording_session_id' => $session->id,
            ...$payload['feature'],
        ]);

        if ($payload['raw_signal']) {
            EkgRawSignal::query()->create([
                'recording_session_id' => $session->id,
                'r_peak_indices' => [],
                ...$payload['raw_signal'],
            ]);
        }

        Prediction::query()->create([
            'recording_session_id' => $session->id,
            'label' => 'PENDING_MODEL',
            'confidence' => null,
            'model_version' => 'legacy-iot-sync',
            'error_message' => 'Data legacy disinkronkan dari database iot; prediksi model belum dijalankan untuk sesi ini.',
            'predicted_at' => now(),
        ]);

        LegacyImport::query()->create([
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'recording_session_id' => $session->id,
            'imported_at' => now(),
        ]);

        return $session;
    }

    private function cleanName(mixed $value): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $value));

        return $name === '' ? null : $name;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $date = trim((string) $value);
        if ($date === '' || str_starts_with($date, '0000-00-00')) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<float>
     */
    private function parseNumericSeries(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $raw = trim((string) $value);
        if ($raw === '' || $raw === '0') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = preg_split('/[\s,;]+/', trim($raw, "[] \t\n\r\0\x0B")) ?: [];
        }

        return collect($decoded)
            ->filter(fn ($item) => is_numeric($item))
            ->map(fn ($item) => round((float) $item, 6))
            ->values()
            ->all();
    }
}