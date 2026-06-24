@if ($records instanceof \Illuminate\Pagination\LengthAwarePaginator)
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mt-3">
        <div class="text-secondary small">
            Menampilkan {{ $records->firstItem() ?? 0 }}-{{ $records->lastItem() ?? 0 }} dari {{ $records->total() }} data
        </div>
        {{ $records->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
@endif
