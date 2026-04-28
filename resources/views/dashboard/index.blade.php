<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Repositories</h1>

            @if($repositories->isEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center text-gray-500 dark:text-gray-400">
                    No repositories found.
                    @if(Route::has('repositories.create'))
                        <a href="{{ route('repositories.create') }}" class="text-blue-600 hover:underline">Add one</a>.
                    @endif
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Repository</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Default Branch</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Coverage</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Reports</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Last Updated</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($repositories as $repository)
                                <tr>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('dashboard.repository', $repository) }}" class="text-blue-600 hover:underline font-medium">
                                            {{ $repository->owner }}/{{ $repository->name }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $repository->default_branch }}</td>
                                    <td class="px-6 py-4">
                                        @if($repository->latestCoverageReport)
                                            @php $pct = $repository->latestCoverageReport->coverage_percentage; @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pct >= 80 ? 'bg-green-100 text-green-800' : ($pct >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                                {{ $pct }}%
                                            </span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 text-sm">No data</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $repository->coverage_reports_count }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $repository->latestCoverageReport?->created_at?->diffForHumans() ?? '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <x-cursor-pagination :paginator="$repositories" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
