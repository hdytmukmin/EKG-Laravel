<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RecordingSession extends Model
{
    protected $fillable = [
        'puskesmas_id',
        'device_id',
        'patient_id',
        'recorded_at',
        'status',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function puskesmas(): BelongsTo
    {
        return $this->belongsTo(Puskesmas::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function feature(): HasOne
    {
        return $this->hasOne(EkgFeature::class);
    }

    public function rawSignal(): HasOne
    {
        return $this->hasOne(EkgRawSignal::class);
    }

    public function prediction(): HasOne
    {
        return $this->hasOne(Prediction::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('recording_sessions.puskesmas_id', $user->puskesmas_id);
    }
}
