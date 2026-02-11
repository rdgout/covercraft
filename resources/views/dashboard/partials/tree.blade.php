@foreach($tree as $name => $node)
    @if($node['type'] === 'directory')
        <details class="group" {{ $depth === 0 ? 'open' : '' }}>
            <summary class="flex items-center justify-between px-4 py-2 hover:bg-gray-50 cursor-pointer" style="padding-left: {{ ($depth * 1.5) + 1 }}rem">
                <div class="flex items-center space-x-2">
                    <svg class="w-4 h-4 text-blue-500 group-open:rotate-90 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-sm font-medium text-gray-700">{{ $name }}/</span>
                    @if(isset($node['file_count']))
                        <span class="text-xs text-gray-400">({{ $node['file_count'] }} files)</span>
                    @endif
                </div>
                @if(isset($node['coverage']))
                    @php $pct = $node['coverage']; @endphp
                    <span class="text-xs font-medium {{ $pct >= 80 ? 'text-green-600' : ($pct >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ number_format($pct, 1) }}%
                    </span>
                @endif
            </summary>
            <div>
                @include('dashboard.partials.tree', ['tree' => $node['children'], 'depth' => $depth + 1])
            </div>
        </details>
    @else
        <div class="flex items-center justify-between px-4 py-2 hover:bg-gray-50" style="padding-left: {{ ($depth * 1.5) + 2.5 }}rem">
            <div class="flex items-center space-x-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                @if($node['covered'])
                    <a href="{{ route('dashboard.file', [$repository, $report->branch, 'path' => $node['path']]) }}" class="text-sm text-blue-600 hover:underline">{{ $name }}</a>
                @else
                    <span class="text-sm text-gray-500">{{ $name }}</span>
                @endif
            </div>
            @php $pct = $node['coverage']; @endphp
            <span class="text-xs font-medium {{ $node['covered'] ? ($pct >= 80 ? 'text-green-600' : ($pct >= 50 ? 'text-yellow-600' : 'text-red-600')) : 'text-gray-400' }}">
                {{ $node['covered'] ? number_format($pct, 1) . '%' : 'N/A' }}
            </span>
        </div>
    @endif
@endforeach
