<?php

namespace App\Support;

use App\Models\RecordEkg;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RecordEkgService
{
    public static function filteredQuery(Request $request): Builder
    {
        $query = RecordEkg::query()->orderByDesc('id');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('nama', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%")
                    ->orWhere('tglrekam', 'like', "%{$search}%")
                    ->orWhere('bpm', 'like', "%{$search}%")
                    ->orWhere('tspt', 'like', "%{$search}%");
            });
        }

        $gender = trim((string) $request->query('jk', ''));
        if ($gender !== '') {
            $query->where('jk', 'like', "%{$gender}%");
        }

        $status = trim((string) $request->query('status', ''));
        if ($status === 'complete') {
            $query->whereNotIn('tspt', ['', '0', '0.0'])
                ->whereNotIn('bpm', ['', '0', '0.0']);
        } elseif ($status === 'empty') {
            $query->where(function (Builder $builder) {
                $builder->whereIn('tspt', ['', '0', '0.0'])
                    ->orWhereIn('bpm', ['', '0', '0.0']);
            });
        }

        if ($request->filled('bpm_min')) {
            $query->whereRaw('CAST(bpm AS DECIMAL(10,2)) >= ?', [(float) $request->query('bpm_min')]);
        }

        if ($request->filled('bpm_max')) {
            $query->whereRaw('CAST(bpm AS DECIMAL(10,2)) <= ?', [(float) $request->query('bpm_max')]);
        }

        return $query;
    }

    public static function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);
        return min(max($perPage, 5), 50);
    }

    public static function dashboard(): array
    {
        $records = RecordEkg::query()->orderBy('id')->get();
        $bpmValues = $records
            ->map(fn (RecordEkg $record) => $record->bpmValue())
            ->filter(fn ($value) => $value !== null && $value > 0)
            ->values();

        $chartRecords = $records
            ->filter(fn (RecordEkg $record) => $record->bpmValue() !== null && $record->bpmValue() > 0)
            ->take(-12)
            ->values();

        return [
            'total_patients' => $records->count(),
            'completed_records' => $records->filter(fn (RecordEkg $record) => $record->isComplete())->count(),
            'avg_bpm' => $bpmValues->isNotEmpty() ? round($bpmValues->avg(), 1) : 0,
            'latest_record' => optional($records->last())->tglrekam ?? '-',
            'chart_labels' => $chartRecords->pluck('nama')->values(),
            'chart_bpm' => $chartRecords->map(fn (RecordEkg $record) => round((float) $record->bpm, 2))->values(),
        ];
    }
}
