<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('dashboard.repository', $repository) }}" class="text-blue-600 hover:underline text-sm">&larr; {{ $repository->owner }}/{{ $repository->name }}</a>
                <div class="flex items-center justify-between mt-2">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $report->branch }}</h1>
                        <div class="flex items-center space-x-4 mt-1">
                            <span class="text-sm text-gray-500">Commit: <code class="font-mono">{{ substr($report->commit_sha, 0, 8) }}</code></span>
                            @if($report->coverage_percentage !== null)
                                @php $pct = $report->coverage_percentage; @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pct >= 80 ? 'bg-green-100 text-green-800' : ($pct >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $pct }}%
                                </span>
                                @if($defaultBranchReport)
                                    @php $diff = round($report->coverage_percentage - $defaultBranchReport->coverage_percentage, 2); @endphp
                                    <span class="text-sm {{ $diff >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $diff >= 0 ? '+' : '' }}{{ $diff }}% vs {{ $repository->default_branch }}
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-700">Show:</span>
                        <a href="{{ route('dashboard.branch', [$repository, $report->branch, 'covered_only' => 1]) }}"
                           class="px-3 py-1.5 text-sm rounded {{ $showOnlyCovered ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                            Covered Only
                        </a>
                        <a href="{{ route('dashboard.branch', [$repository, $report->branch]) }}"
                           class="px-3 py-1.5 text-sm rounded {{ !$showOnlyCovered ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                            All Files
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                @if(empty($fileTree))
                    <div class="p-6 text-center text-gray-500">No file tree data available. Push to this branch to populate the file cache.</div>
                @else
                    <div class="divide-y divide-gray-100">
                        @include('dashboard.partials.tree', ['tree' => $fileTree, 'depth' => 0])
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
