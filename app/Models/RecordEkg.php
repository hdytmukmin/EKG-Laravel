<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordEkg extends Model
{
    protected $table = 'recordekg';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nama',
        'umur',
        'jk',
        'alamat',
        'tglrekam',
        'tspt',
        'bpm',
        'irr',
        'irrlokal',
        'hr',
        'filtered',
    ];

    public function isComplete(): bool
    {
        return !in_array((string) $this->tspt, ['', '0', '0.0'], true)
            && !in_array((string) $this->bpm, ['', '0', '0.0'], true);
    }

    public function bpmValue(): ?float
    {
        return is_numeric($this->bpm) ? (float) $this->bpm : null;
    }

    public function status(): array
    {
        if (! $this->isComplete()) {
            return ['label' => 'Belum lengkap', 'class' => 'text-bg-secondary', 'icon' => 'bi-hourglass-split'];
        }

        $bpm = $this->bpmValue();
        if ($bpm === null) {
            return ['label' => 'Data perlu cek', 'class' => 'text-bg-warning', 'icon' => 'bi-exclamation-triangle'];
        }

        if ($bpm < 60) {
            return ['label' => 'BPM rendah', 'class' => 'text-bg-info', 'icon' => 'bi-arrow-down-circle'];
        }

        if ($bpm > 100) {
            return ['label' => 'BPM tinggi', 'class' => 'text-bg-danger', 'icon' => 'bi-arrow-up-circle'];
        }

        return ['label' => 'Normal', 'class' => 'text-bg-success', 'icon' => 'bi-check-circle'];
    }

    public function heartRateSeries(): array
    {
        if (! $this->hr) {
            return [];
        }

        $decoded = json_decode($this->hr, true);
        if (is_array($decoded)) {
            return array_values($decoded);
        }

        return [];
    }
}
