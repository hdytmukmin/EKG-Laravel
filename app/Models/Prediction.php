<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    protected $fillable = [
        'recording_session_id',
        'label',
        'confidence',
        'model_version',
        'error_message',
        'predicted_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'predicted_at' => 'datetime',
        ];
    }

    public function recordingSession(): BelongsTo
    {
        return $this->belongsTo(RecordingSession::class);
    }
}
