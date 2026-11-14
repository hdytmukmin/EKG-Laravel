<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\EkgFeature;
use App\Models\EkgRawSignal;
use App\Models\Patient;
use App\Models\Prediction;
use App\Models\Puskesmas;
use App\Models\RecordingSession;
use App\Services\DeepLearningEcgClassificationService;
use App\Services\EcgSignalProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Throwable;

class MqttListenCommand extends Command
{
    protected $signature = 'mqtt:listen {--once : Stop after one complete EKG feature payload is saved}';

    protected $description = 'Listen to EKG MQTT topics and store data into the AF/Non-AF Laravel schema.';

    private const TOPIC_TO_FIELD = [
        'building/device_uid' => 'device_uid',
        'building/faskes_id' => 'faskes_id',
        'building/faskes_code' => 'faskes_code',
        'building/subjek' => 'subject',
        'building/tspt' => 'interval_pt',
        'building/bpm' => 'bpm',
        'building/RR' => 'rr',
        'building/rrlokal' => 'rr_lokal',
        'building/hrr' => 'heart_rate',
        'building/rawdata' => 'raw_signal',
    ];

    private const FIELD_ALIASES = [
        'faskes_id' => 'faskes_id',
        'faskes_code' => 'faskes_code',
        'subjek' => 'subject',
        'subject' => 'subject',
        'tspt' => 'interval_pt',
        'interval_pt' => 'interval_pt',
        'bpm' => 'bpm',
        'rr' => 'rr',
        'rrlokal' => 'rr_lokal',
        'rr_lokal' => 'rr_lokal',
        'hrr' => 'heart_rate',
        'heart_rate' => 'heart_rate',
        'rawdata' => 'raw_signal',
        'raw_signal' => 'raw_signal',
    ];

    private const REQUIRED_FIELDS = ['subject', 'interval_pt', 'bpm', 'rr', 'rr_lokal', 'heart_rate', 'raw_signal'];

    private array $buffers = [];

    private ?string $legacyDeviceUid = null;

    private int $savedSessions = 0;

    public function handle(EcgSignalProcessingService $processor, DeepLearningEcgClassificationService $classifier): int
    {
        $host = (string) config('mqtt.host');
        $port = (int) config('mqtt.port');
        $clientId = (string) config('mqtt.client_id');

        $settings = (new ConnectionSettings)
            ->setKeepAliveInterval((int) config('mqtt.keepalive'))
            ->setConnectTimeout((int) config('mqtt.connect_timeout'))
            ->setReconnectAutomatically(true)
            ->setMaxReconnectAttempts((int) config('mqtt.reconnect_attempts'))
            ->setDelayBetweenReconnectAttempts((int) config('mqtt.reconnect_delay'));

        if (filled(config('mqtt.username'))) {
            $settings->setUsername((string) config('mqtt.username'));
        }

        if (filled(config('mqtt.password'))) {
            $settings->setPassword((string) config('mqtt.password'));
        }

        $mqtt = new MqttClient($host, $port, $clientId);

        try {
            $this->info("Connecting MQTT {$host}:{$port} ...");
            $mqtt->connect($settings, true);

            foreach (self::TOPIC_TO_FIELD as $topic => $field) {
                $mqtt->subscribe($topic, function (string $topic, string $message) use ($mqtt, $processor, $classifier): void {
                    $this->handleMessage($topic, $message, $processor, $classifier);

                    if ($this->option('once') && $this->savedSessions > 0) {
                        $mqtt->interrupt();
                    }
                }, MqttClient::QOS_AT_MOST_ONCE);

                $this->line("Subscribed: {$topic}");
            }

            $mqtt->subscribe('ekg/+/+', function (string $topic, string $message) use ($mqtt, $processor, $classifier): void {
                $this->handleMessage($topic, $message, $processor, $classifier);

                if ($this->option('once') && $this->savedSessions > 0) {
                    $mqtt->interrupt();
                }
            }, MqttClient::QOS_AT_MOST_ONCE);
            $this->line('Subscribed: ekg/+/+');

            $this->info('Waiting for MQTT EKG data...');
            $mqtt->loop(true);

            if ($mqtt->isConnected()) {
                $mqtt->disconnect();
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            report($exception);

            return self::FAILURE;
        }
    }

    private function handleMessage(
        string $topic,
        string $message,
        EcgSignalProcessingService $processor,
        DeepLearningEcgClassificationService $classifier
    ): void {
        [$deviceUid, $field] = $this->resolveTopicContext($topic, $message);
        if ($field === null) {
            return;
        }

        if ($field === 'device_uid') {
            $normalizedUid = $this->normalizeIdentifier($message);
            if ($normalizedUid === '') {
                $this->warn('Payload device_uid kosong.');
                return;
            }

            $this->legacyDeviceUid = $normalizedUid;
            $this->line("MQTT device context: {$normalizedUid}");
            return;
        }

        if ($deviceUid === null) {
            $this->warn("MQTT {$topic} diabaikan karena device_uid belum tersedia.");
            return;
        }

        if ($field === 'subject') {
            $facilityContext = array_intersect_key(
                $this->buffers[$deviceUid] ?? [],
                array_flip(['faskes_id', 'faskes_code'])
            );
            $this->buffers[$deviceUid] = $facilityContext;
        }

        $this->buffers[$deviceUid][$field] = $message;
        $this->line("MQTT received: {$topic} [device={$deviceUid}]");

        if (! $this->hasCompleteFeaturePayload($deviceUid)) {
            return;
        }

        $buffer = $this->buffers[$deviceUid];
        $payload = $this->normalizePayload($buffer);
        if ($payload === null) {
            unset($this->buffers[$deviceUid]);
            return;
        }

        try {
            [$device, $facility] = $this->resolveDeviceAndFacility($deviceUid, $buffer);
            $sampleRate = max((int) config('ekg.sample_rate', 250), 1);
            $processed = $processor->process($payload['raw_signal'], $sampleRate);
            $prediction = $classifier->classify($payload['raw_signal'], $sampleRate);

            [$session, $patient] = DB::transaction(function () use (
                $device,
                $facility,
                $payload,
                $processed,
                $prediction,
                $processor,
                $sampleRate
            ): array {
                $patient = Patient::query()->firstOrCreate(
                    [
                        'puskesmas_id' => $facility->id,
                        'name' => $payload['subject'],
                    ],
                    ['external_subject_id' => $payload['subject']]
                );

                $device->update([
                    'status' => 'online',
                    'last_seen_at' => now(),
                ]);

                $session = RecordingSession::create([
                    'puskesmas_id' => $facility->id,
                    'device_id' => $device->id,
                    'patient_id' => $patient->id,
                    'recorded_at' => now(),
                    'status' => 'completed',
                    'source' => 'mqtt',
                ]);

                EkgFeature::create([
                    'recording_session_id' => $session->id,
                    'subject' => $payload['subject'],
                    'interval_pt' => $payload['interval_pt'],
                    'bpm' => $payload['bpm'],
                    'rr' => $payload['rr'],
                    'rr_lokal' => $payload['rr_lokal'],
                    'status' => $payload['status'],
                    'sdnn' => $processed['sdnn'],
                    'sns' => $processed['sns'],
                    'heart_rate' => $payload['heart_rate'] ?: $processed['heart_rate'],
                ]);

                EkgRawSignal::create([
                    'recording_session_id' => $session->id,
                    'voltage_values' => $processor->roundSeries($payload['raw_signal'], 6),
                    'filtered_values' => $processed['filtered_values'],
                    'r_peak_indices' => $processed['r_peak_indices'],
                    'sample_rate' => $sampleRate,
                    'total_samples' => count($payload['raw_signal']),
                ]);

                Prediction::create([
                    'recording_session_id' => $session->id,
                    'label' => $prediction['label'],
                    'confidence' => $prediction['confidence'],
                    'model_version' => $prediction['model_version'],
                    'error_message' => $prediction['error_message'],
                    'predicted_at' => now(),
                ]);

                return [$session, $patient];
            });

            $minimumSamples = max((int) config('ekg.minimum_samples', 5000), 2);
            if (count($payload['raw_signal']) < $minimumSamples) {
                $this->warn(sprintf(
                    'Sinyal sesi #%d belum mencukupi: %d dari minimal %d sampel. Data tetap disimpan.',
                    $session->id,
                    count($payload['raw_signal']),
                    $minimumSamples
                ));
            }

            $this->info(sprintf(
                'Session saved: #%d, UUID=%s, Device=%s, Facility=%s, Patient=%s, BPM=%s',
                $session->id,
                $session->session_uuid,
                $device->device_uid,
                $facility->code,
                $patient->name,
                $payload['bpm']
            ));
            $this->savedSessions++;
        } catch (Throwable $exception) {
            $this->error("Payload device {$deviceUid} gagal disimpan: {$exception->getMessage()}");
            report($exception);
        } finally {
            unset($this->buffers[$deviceUid]);
        }
    }

    private function hasCompleteFeaturePayload(string $deviceUid): bool
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (! array_key_exists($field, $this->buffers[$deviceUid] ?? [])) {
                return false;
            }
        }

        return true;
    }

    private function normalizePayload(array $data): ?array
    {
        $subject = trim(preg_replace('/\s+/', ' ', (string) $data['subject']));
        if ($subject === '') {
            $this->warn('Payload subject kosong.');
            return null;
        }

        $numbers = [];
        foreach (['interval_pt', 'bpm', 'rr', 'rr_lokal'] as $field) {
            if (! is_numeric(trim((string) $data[$field]))) {
                $this->warn("Payload {$field} tidak valid: {$data[$field]}");
                return null;
            }

            $numbers[$field] = (float) $data[$field];
        }

        return [
            'subject' => $subject,
            'interval_pt' => $numbers['interval_pt'],
            'bpm' => $numbers['bpm'],
            'rr' => $numbers['rr'],
            'rr_lokal' => $numbers['rr_lokal'],
            'status' => (string) ($data['status'] ?? 'received'),
            'heart_rate' => $this->normalizeNumericSeries((string) $data['heart_rate']),
            'raw_signal' => $this->normalizeNumericSeries((string) ($data['raw_signal'] ?? '[]')),
        ];
    }

    /**
     * @return array<int, float>
     */
    private function normalizeNumericSeries(string $value): array
    {
        $raw = trim($value);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = preg_split('/[\s,;]+/', trim($raw, "[] \t\n\r\0\x0B")) ?: [];
        }

        return collect($decoded)
            ->filter(fn ($item) => is_numeric($item))
            ->map(fn ($item) => (float) $item)
            ->values()
            ->all();
    }

    private function resolveTopicContext(string $topic, string $message): array
    {
        if (preg_match('#^ekg/([^/]+)/([^/]+)$#i', $topic, $matches) === 1) {
            $field = self::FIELD_ALIASES[strtolower($matches[2])] ?? null;

            return [$this->normalizeIdentifier($matches[1]), $field];
        }

        $field = self::TOPIC_TO_FIELD[$topic] ?? null;
        if ($field === 'device_uid') {
            return [null, $field];
        }

        $deviceUid = $this->legacyDeviceUid
            ?: $this->normalizeIdentifier((string) config('mqtt.legacy_device_uid', ''));

        return [$deviceUid !== '' ? $deviceUid : null, $field];
    }

    private function normalizeIdentifier(string $value): string
    {
        return strtoupper(trim($value));
    }

    /**
     * @return array{0: Device, 1: Puskesmas}
     */
    private function resolveDeviceAndFacility(string $deviceUid, array $buffer): array
    {
        $device = Device::query()
            ->with('activeAssignments')
            ->where('device_uid', $deviceUid)
            ->first();

        if (! $device) {
            throw new \RuntimeException("Device UID {$deviceUid} belum terdaftar.");
        }

        $facilityId = trim((string) ($buffer['faskes_id'] ?? config('mqtt.legacy_facility_id', '')));
        $facilityCode = strtoupper(trim((string) ($buffer['faskes_code'] ?? config('mqtt.legacy_facility_code', ''))));

        $facility = null;
        if ($facilityId !== '') {
            if (! ctype_digit($facilityId)) {
                throw new \RuntimeException("faskes_id {$facilityId} tidak valid.");
            }

            $facility = Puskesmas::query()->find((int) $facilityId);
        } elseif ($facilityCode !== '') {
            $facility = Puskesmas::query()->where('code', $facilityCode)->first();
        } elseif ($device->puskesmas_id) {
            // Compatibility for old devices while their firmware still uses global topics.
            $facility = Puskesmas::query()->find($device->puskesmas_id);
        }

        if (! $facility) {
            throw new \RuntimeException('Fasilitas kesehatan tidak ditemukan atau belum dikirim oleh alat.');
        }

        $hasActiveAssignments = $device->activeAssignments->isNotEmpty();
        $isActivelyAssigned = $device->activeAssignments
            ->contains(fn ($assignment) => $assignment->puskesmas_id === $facility->id);
        $isLegacyFacility = ! $hasActiveAssignments && $device->puskesmas_id === $facility->id;

        if (! $isActivelyAssigned && ! $isLegacyFacility) {
            throw new \RuntimeException(
                "Device {$deviceUid} tidak sedang ditugaskan ke fasilitas {$facility->code}."
            );
        }

        return [$device, $facility];
    }
}
