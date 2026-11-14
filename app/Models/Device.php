<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'puskesmas_id',
        'name',
        'device_uid',
        'mqtt_client_id',
        'status',
        'last_seen_at',
        'topic_map',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'topic_map' => 'array',
        ];
    }

    public function puskesmas(): BelongsTo
    {
        return $this->belongsTo(Puskesmas::class);
    }

    public function recordingSessions(): HasMany
    {
        return $this->hasMany(RecordingSession::class);
    }

    public function effectiveStatus(): string
    {
        if ($this->status === 'maintenance') {
            return 'maintenance';
        }

        if (! $this->last_seen_at) {
            return 'offline';
        }

        $offlineAfterMinutes = max((int) config('monitoring.device_offline_after_minutes', 2), 1);
        if ($this->last_seen_at->lt(now()->subMinutes($offlineAfterMinutes))) {
            return 'offline';
        }

        return 'online';
    }

    public function statusLabel(): string
    {
        return match ($this->effectiveStatus()) {
            'online' => 'Online',
            'offline' => 'Offline',
            'maintenance' => 'Pemeliharaan',
            default => 'Tidak diketahui',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->effectiveStatus()) {
            'online' => 'text-bg-success',
            'maintenance' => 'text-bg-warning',
            'offline' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }
}
