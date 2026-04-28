<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline text-sm">&larr; All Repositories</a>
                <h1 class="text-2xl font-bold text-gray-900 mt-2">{{ $repository->owner }}/{{ $repository->name }}</h1>
                <p class="text-sm text-gray-500">Default branch: {{ $repository->default_branch }}</p>
            </div>

            @if(! $defaultBranchReport && $branches->isEmpty())
                <div class="bg-white rounded-lg shadow p-6 text-center text-gray-500">
                    No coverage reports yet.
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Coverage</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">vs {{ $repository->default_branch }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Updated</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @if($defaultBranchReport)
                                <tr>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('dashboard.branch', [$repository, $defaultBranchReport->branch]) }}" class="text-blue-600 hover:underline font-medium">
                                            {{ $defaultBranchReport->branch }}
                                        </a>
                                        <span class="ml-1 text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">default</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($defaultBranchReport->coverage_percentage !== null)
                                            @php $pct = $defaultBranchReport->coverage_percentage; @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pct >= 80 ? 'bg-green-100 text-green-800' : ($pct >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                                {{ $pct }}%
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-sm">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm"><span class="text-gray-400">-</span></td>
                                    <td class="px-6 py-4 text-sm text-gray-500 font-mono">{{ substr($defaultBranchReport->commit_sha, 0, 8) }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $defaultBranchReport->status === 'completed' ? 'bg-green-100 text-green-800' : ($defaultBranchReport->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                            {{ $defaultBranchReport->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $defaultBranchReport->created_at->diffForHumans() }}</td>
                                </tr>
                            @endif
                            @foreach($branches as $report)
                                @php
                                    $diff = $defaultBranchReport && $report->coverage_percentage !== null
                                        ? round($report->coverage_percentage - $defaultBranchReport->coverage_percentage, 2)
                                        : null;
                                @endphp
                                <tr>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('dashboard.branch', [$repository, $report->branch]) }}" class="text-blue-600 hover:underline font-medium">
                                            {{ $report->branch }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($report->coverage_percentage !== null)
                                            @php $pct = $report->coverage_percentage; @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pct >= 80 ? 'bg-green-100 text-green-800' : ($pct >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                                {{ $pct }}%
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-sm">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($diff !== null)
                                            <span class="{{ $diff >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $diff >= 0 ? '+' : '' }}{{ $diff }}%
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 font-mono">{{ substr($report->commit_sha, 0, 8) }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $report->status === 'completed' ? 'bg-green-100 text-green-800' : ($report->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                            {{ $report->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $report->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <x-cursor-pagination :paginator="$branches" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
