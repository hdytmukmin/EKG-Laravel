<?php

namespace App\Console\Commands;

use App\Models\RecordEkg;
use Illuminate\Console\Command;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Throwable;

class MqttListenCommand extends Command
{
    protected $signature = 'mqtt:listen {--once : Stop after one complete EKG payload is saved}';

    protected $description = 'Listen to EKG MQTT topics and update the recordekg table.';

    private const TOPIC_TO_FIELD = [
        'building/subjek' => 'nama',
        'building/tspt' => 'tspt',
        'building/bpm' => 'bpm',
        'building/RR' => 'irr',
        'building/rrlokal' => 'irrlokal',
        'building/hrr' => 'hr',
    ];

    private const REQUIRED_FIELDS = ['nama', 'tspt', 'bpm', 'irr', 'irrlokal', 'hr'];

    private array $buffer = [];

    public function handle(): int
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
                $mqtt->subscribe($topic, function (string $topic, string $message) use ($mqtt): void {
                    $this->handleMessage($topic, $message);

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

    private function handleMessage(string $topic, string $message): void
    {
        $field = self::TOPIC_TO_FIELD[$topic] ?? null;
        if ($field === null) {
            return;
        }

        if ($field === 'nama') {
            $this->buffer = [];
        }

        $this->buffer[$field] = $message;
        $this->line("MQTT received: {$topic} => {$message}");

        if (! $this->hasCompletePayload()) {
            return;
        }

        $payload = $this->normalizePayload($this->buffer);
        if ($payload === null) {
            $this->buffer = [];
            return;
        }

        $autoCreate = filter_var(env('MQTT_AUTO_CREATE_PATIENT', true), FILTER_VALIDATE_BOOL);

        $record = RecordEkg::query()
            ->where('nama', $payload['nama'])
            ->orderByDesc('id')
            ->first();

        if (! $record && ! $autoCreate) {
            $this->warn("Patient not found and auto-create disabled: {$payload['nama']}");
            $this->buffer = [];
            return;
        }

        if (! $record) {
            $record = RecordEkg::create([
                'nama' => $payload['nama'],
                'umur' => 0,
                'jk' => '',
                'alamat' => '',
                'tspt' => 0,
                'bpm' => 0,
                'irr' => 0,
                'irrlokal' => 0,
                'hr' => '[]',
            ]);

            $this->info("Patient created: ID={$record->id}, Name={$record->nama}");
        }

        $record->update([
            'tglrekam' => now()->format('Y-m-d H:i:s'),
            'tspt' => $payload['tspt'],
            'bpm' => $payload['bpm'],
            'irr' => $payload['irr'],
            'irrlokal' => $payload['irrlokal'],
            'hr' => $payload['hr'],
        ]);

        $this->info("Database updated: ID={$record->id}, BPM={$payload['bpm']}");
        $this->buffer = [];
    }

    private function hasCompletePayload(): bool
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
        $name = trim(preg_replace('/\s+/', ' ', (string) $data['nama']));
        if ($name === '') {
            $this->warn('Payload nama pasien kosong.');
            return null;
        }

        $numbers = [];
        foreach (['tspt', 'bpm', 'irr', 'irrlokal'] as $field) {
            if (! is_numeric(trim((string) $data[$field]))) {
                $this->warn("Payload {$field} tidak valid: {$data[$field]}");
                return null;
            }

            $numbers[$field] = (float) $data[$field];
        }

        return [
            'nama' => $name,
            'tspt' => $numbers['tspt'],
            'bpm' => $numbers['bpm'],
            'irr' => $numbers['irr'],
            'irrlokal' => $numbers['irrlokal'],
            'hr' => $this->normalizeHeartRate((string) $data['hr']),
        ];
    }

    private function normalizeHeartRate(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '[]';
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = [$raw];
        }

        return json_encode(array_values($decoded), JSON_THROW_ON_ERROR);
    }
}
