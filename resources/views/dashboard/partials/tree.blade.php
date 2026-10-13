@foreach ($tree as $name => $node)
    @php($path = ltrim($prefix . '/' . $name, '/'))
    @if ($node['type'] === 'directory')
        <details
            @if ($depth < 2) open @endif
            x-show="directoryMatches(@js($path))"
            :open="query ? true : $el.open"
        >
            <summary
                class="cc-tree-row cc-tree-dir"
                style="padding-left: {{ 12 + $depth * 20 }}px"
            >
                <span class="cc-tree-name">
                    <x-icon
                        name="chevron"
                        class="cc-tree-chevron h-3 w-3 shrink-0"
                    />
                    <x-icon
                        name="folder"
                        class="cc-tree-folder shrink-0"
                    />
                    <span>{{ $name }}</span>
                    <span class="cc-tree-count">{{ $node['file_count'] }}
                        files</span>
                </span>
                <x-coverage-meter
                    :value="$node['coverage']"
                    class="cc-meter--sm"
                />
            </summary>
            @include('dashboard.partials.tree', [
                'tree' => $node['children'],
                'depth' => $depth + 1,
                'prefix' => $path,
            ])
        </details>
    @else
        <div
            class="cc-tree-row cc-tree-file"
            data-file-path="{{ $node['path'] }}"
            x-show="matches(@js($node['path']))"
            style="padding-left: {{ 44 + $depth * 20 }}px"
        >
            <span class="cc-tree-name">
                <x-icon
                    name="file"
                    class="shrink-0"
                />
                @if ($node['covered'])
                    <a
                        class="truncate"
                        href="{{ route('dashboard.file', [$repository, $report->branch, 'path' => $node['path']]) }}"
                    >{{ $name }}</a>
                @else
                    <span>{{ $name }}</span>
                @endif
            </span>
            @if ($node['covered'])
                <x-coverage-meter
                    :value="$node['coverage']"
                    class="cc-meter--sm"
                />
            @else
                <span class="cc-tree-na">No data</span>
            @endif
        </div>
    @endif
@endforeach
