<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Sesi</th>
                <th>Pasien</th>
                <th>Puskesmas</th>
                <th>BPM</th>
                <th>RR</th>
                <th>Prediksi</th>
                <th>Waktu</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions as $session)
                @php($afLabels = ['AF', 'PERSISTENT_AF', 'PAROXYSMAL_AF'])
                @php($predictionLabel = $session->prediction?->label)
                @php($isAf = in_array($predictionLabel, $afLabels, true))
                @php($predictionText = match ($predictionLabel) {
                    'PERSISTENT_AF' => 'Persistent AF',
                    'PAROXYSMAL_AF' => 'Paroxysmal AF',
                    'NON_AF' => 'Non-AF',
                    default => $predictionLabel ?? 'PENDING',
                })
                <tr>
                    <td>#{{ $session->id }}</td>
                    <td>
                        <div class="entity-cell">
                            <div class="entity-avatar {{ $isAf ? 'danger' : ($predictionLabel === 'NON_AF' ? 'success' : '') }}">
                                <i class="bi bi-heart-pulse"></i>
                            </div>
                            <div>
                                <div class="fw-bold">{{ $session->patient?->name ?? '-' }}</div>
                                <div class="small text-secondary">{{ $session->device?->name ?? 'Alat belum tercatat' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $session->puskesmas?->name ?? '-' }}</td>
                    <td class="fw-bold">{{ $session->feature?->bpm ?? '-' }}</td>
                    <td>{{ $session->feature?->rr ?? '-' }}</td>
                    <td><span class="badge {{ $isAf ? 'text-bg-danger' : ($predictionLabel === 'NON_AF' ? 'text-bg-success' : 'text-bg-secondary') }}">{{ $predictionText }}</span></td>
                    <td>{{ $session->recorded_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('recordings.show', $session) }}"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state">Belum ada sesi rekaman.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($sessions instanceof \Illuminate\Pagination\LengthAwarePaginator)
    @include('partials.pagination', ['records' => $sessions])
@endif
