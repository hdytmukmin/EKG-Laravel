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
}
