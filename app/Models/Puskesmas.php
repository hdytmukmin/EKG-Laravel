<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Puskesmas extends Model
{
    protected $table = 'puskesmas';

    protected $fillable = ['name', 'code', 'address', 'contact'];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function deviceAssignments(): HasMany
    {
        return $this->hasMany(DeviceAssignment::class);
    }

    public function assignedDevices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'device_assignments')
            ->withPivot(['id', 'assigned_at', 'unassigned_at', 'status', 'notes'])
            ->withTimestamps();
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
