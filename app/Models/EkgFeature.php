<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EkgFeature extends Model
{
    protected $fillable = [
        'recording_session_id',
        'subject',
        'interval_pt',
        'bpm',
        'rr',
        'rr_lokal',
        'status',
        'sdnn',
        'sns',
        'heart_rate',
    ];

    protected function casts(): array
    {
        return [
            'heart_rate' => 'array',
            'interval_pt' => 'float',
            'bpm' => 'float',
            'rr' => 'float',
            'rr_lokal' => 'float',
            'sdnn' => 'float',
            'sns' => 'float',
        ];
    }

    public function recordingSession(): BelongsTo
    {
        return $this->belongsTo(RecordingSession::class);
    }
}
