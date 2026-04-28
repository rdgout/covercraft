@props(['paginator'])

@if($paginator->hasPages())
    <div class="flex items-center justify-between px-6 py-3 border-t border-gray-200 bg-white">
        <p class="text-sm text-gray-500">
            Showing {{ $paginator->count() }} result{{ $paginator->count() === 1 ? '' : 's' }}
        </p>
        <div class="flex gap-2">
            @if($paginator->previousCursor())
                <a href="{{ $paginator->previousPageUrl() }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition ease-in-out duration-150">
                    &larr; Previous
                </a>
            @endif
            @if($paginator->nextCursor())
                <a href="{{ $paginator->nextPageUrl() }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition ease-in-out duration-150">
                    Next &rarr;
                </a>
            @endif
        </div>
    </div>
@endif
