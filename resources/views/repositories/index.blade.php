<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Repositories</h1>
                <a href="{{ route('repositories.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-blue-700 transition ease-in-out duration-150">Add Repository</a>
            </div>

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if($repositories->isEmpty())
                <div class="bg-white rounded-lg shadow p-6 text-center text-gray-500">
                    No repositories linked yet.
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Repository</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Default Branch</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Coverage</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($repositories as $repository)
                                <tr>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('dashboard.repository', $repository) }}" class="text-blue-600 hover:underline font-medium">
                                            {{ $repository->owner }}/{{ $repository->name }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $repository->default_branch }}</td>
                                    <td class="px-6 py-4">
                                        @if($repository->latestCoverageReport)
                                            @php $pct = $repository->latestCoverageReport->coverage_percentage; @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pct >= 80 ? 'bg-green-100 text-green-800' : ($pct >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                                {{ $pct }}%
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-sm">No data</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <a href="{{ route('repositories.edit', $repository) }}" class="text-blue-600 hover:underline text-sm">Edit</a>
                                        <form action="{{ route('repositories.destroy', $repository) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline text-sm" onclick="return confirm('Delete this repository?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
