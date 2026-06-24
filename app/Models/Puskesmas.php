<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Puskesmas extends Model
{
    protected $table = 'puskesmas';

    protected $fillable = ['name', 'code', 'address'];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function recordingSessions(): HasMany
    {
        return $this->hasMany(RecordingSession::class);
    }
}
