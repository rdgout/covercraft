@props(['paginator'])
@if ($paginator->hasPages())
    <nav
        class="cc-pager"
        aria-label="Pagination"
    >
        <span>Showing {{ $paginator->count() }} {{ Str::plural('result', $paginator->count()) }}</span>
        <div class="flex gap-2">
            @if ($paginator->previousCursor())
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    class="cc-btn cc-btn--secondary cc-btn--sm"
                    rel="prev"
                >← Previous</a>
            @endif
            @if ($paginator->nextCursor())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    class="cc-btn cc-btn--secondary cc-btn--sm"
                    rel="next"
                >Next →</a>
            @endif
        </div>
    </nav>
@endif
