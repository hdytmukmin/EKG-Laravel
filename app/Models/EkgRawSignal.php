<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EkgRawSignal extends Model
{
    protected $fillable = [
        'recording_session_id',
        'voltage_values',
        'filtered_values',
        'r_peak_indices',
        'sample_rate',
        'total_samples',
    ];

    protected function casts(): array
    {
        return [
            'voltage_values' => 'array',
            'filtered_values' => 'array',
            'r_peak_indices' => 'array',
        ];
    }

    public function recordingSession(): BelongsTo
    {
        return $this->belongsTo(RecordingSession::class);
    }
}
