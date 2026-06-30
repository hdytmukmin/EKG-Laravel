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
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Throwable;

class MqttListenCommand extends Command
{
    protected $signature = 'mqtt:listen {--once : Stop after one complete EKG feature payload is saved}';

    protected $description = 'Listen to EKG MQTT topics and store data into the AF/Non-AF Laravel schema.';

    private const TOPIC_TO_FIELD = [
        'building/subjek' => 'subject',
        'building/tspt' => 'interval_pt',
        'building/bpm' => 'bpm',
        'building/RR' => 'rr',
        'building/rrlokal' => 'rr_lokal',
        'building/hrr' => 'heart_rate',
        'building/rawdata' => 'raw_signal',
    ];

    private const REQUIRED_FIELDS = ['subject', 'interval_pt', 'bpm', 'rr', 'rr_lokal', 'heart_rate', 'raw_signal'];

    private array $buffer = [];

    public function handle(EcgSignalProcessingService $processor, DeepLearningEcgClassificationService $classifier): int
    {
        $host = (string) env('MQTT_HOST', '160.187.144.147');
        $port = (int) env('MQTT_PORT', 1883);
        $clientId = (string) env('MQTT_CLIENT_ID', 'ekg-laravel-'.gethostname().'-'.getmypid());

        $settings = (new ConnectionSettings)
            ->setKeepAliveInterval((int) env('MQTT_KEEPALIVE', 60))
            ->setConnectTimeout((int) env('MQTT_CONNECT_TIMEOUT', 10))
            ->setReconnectAutomatically(true)
            ->setMaxReconnectAttempts((int) env('MQTT_RECONNECT_ATTEMPTS', 0))
            ->setDelayBetweenReconnectAttempts((int) env('MQTT_RECONNECT_DELAY', 3));

        if (filled(env('MQTT_USERNAME'))) {
            $settings->setUsername((string) env('MQTT_USERNAME'));
        }

        if (filled(env('MQTT_PASSWORD'))) {
            $settings->setPassword((string) env('MQTT_PASSWORD'));
        }

        $mqtt = new MqttClient($host, $port, $clientId);

        try {
            $this->info("Connecting MQTT {$host}:{$port} ...");
            $mqtt->connect($settings, true);

            foreach (self::TOPIC_TO_FIELD as $topic => $field) {
                $mqtt->subscribe($topic, function (string $topic, string $message) use ($mqtt, $processor, $classifier): void {
                    $this->handleMessage($topic, $message, $processor, $classifier);

                    if ($this->option('once') && empty($this->buffer)) {
                        $mqtt->interrupt();
                    }
                }, MqttClient::QOS_AT_MOST_ONCE);

                $this->line("Subscribed: {$topic}");
            }

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
        $field = self::TOPIC_TO_FIELD[$topic] ?? null;
        if ($field === null) {
            return;
        }

        if ($field === 'subject') {
            $this->buffer = [];
        }

        $this->buffer[$field] = $message;
        $this->line("MQTT received: {$topic}");

        if (! $this->hasCompleteFeaturePayload()) {
            return;
        }

        $payload = $this->normalizePayload($this->buffer);
        if ($payload === null) {
            $this->buffer = [];
            return;
        }

        $device = $this->resolveDevice();
        $patient = Patient::query()->firstOrCreate(
            [
                'puskesmas_id' => $device->puskesmas_id,
                'name' => $payload['subject'],
            ],
            [
                'external_subject_id' => $payload['subject'],
            ]
        );

        $device->update([
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        $session = RecordingSession::create([
            'puskesmas_id' => $device->puskesmas_id,
            'device_id' => $device->id,
            'patient_id' => $patient->id,
            'recorded_at' => now(),
            'status' => 'completed',
            'source' => 'mqtt',
        ]);

        $sampleRate = (int) env('EKG_SAMPLE_RATE', 0) ?: 200;
        $processed = $processor->process($payload['raw_signal'], $sampleRate);
        $feature = EkgFeature::create([
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

        if ($payload['raw_signal'] !== []) {
            EkgRawSignal::create([
                'recording_session_id' => $session->id,
                'voltage_values' => $processor->roundSeries($payload['raw_signal'], 6),
                'filtered_values' => $processed['filtered_values'],
                'r_peak_indices' => $processed['r_peak_indices'],
                'sample_rate' => $sampleRate,
                'total_samples' => count($payload['raw_signal']),
            ]);
        }

        $prediction = $classifier->classify($payload['raw_signal'], $sampleRate);
        Prediction::create([
            'recording_session_id' => $session->id,
            'label' => $prediction['label'],
            'confidence' => $prediction['confidence'],
            'model_version' => $prediction['model_version'],
            'error_message' => $prediction['error_message'],
            'predicted_at' => now(),
        ]);

        $this->info("Session saved: #{$session->id}, Patient={$patient->name}, BPM={$payload['bpm']}");
        $this->buffer = [];
    }

    private function hasCompleteFeaturePayload(): bool
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (! array_key_exists($field, $this->buffer)) {
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

    private function resolveDevice(): Device
    {
        $deviceUid = (string) env('MQTT_DEVICE_UID', 'EKG-001');
        $device = Device::query()->where('device_uid', $deviceUid)->first();

        if ($device) {
            return $device;
        }

        $puskesmas = Puskesmas::query()->firstOrCreate(
            ['code' => 'PKM-001'],
            ['name' => 'Puskesmas 1']
        );

        return Device::query()->create([
            'puskesmas_id' => $puskesmas->id,
            'name' => 'Alat EKG 1',
            'device_uid' => $deviceUid,
            'mqtt_client_id' => env('MQTT_CLIENT_ID'),
            'status' => 'unknown',
        ]);
    }
}
