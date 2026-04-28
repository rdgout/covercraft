<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('repositories.index') }}" class="text-blue-600 hover:underline text-sm">&larr; Back</a>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Add Repository</h1>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 max-w-lg">
                <form action="{{ route('repositories.store') }}" method="POST" id="repo-form">
                    @csrf

                    <div class="mb-4">
                        <label for="team_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Team <span class="text-red-600">*</span></label>
                        <select name="team_id" id="team_id" required class="w-full border-gray-300 rounded-lg shadow-sm">
                            <option value="">Select a team...</option>
                            @foreach($teams as $team)
                                <option value="{{ $team->id }}" {{ old('team_id') == $team->id ? 'selected' : '' }}>
                                    {{ $team->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('team_id')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="github_repo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">GitHub Repository</label>
                        @if(count($githubRepos) > 0)
                            <select id="github_repo" class="w-full border-gray-300 rounded-lg shadow-sm" onchange="onRepoSelect(this)">
                                <option value="">Select a repository...</option>
                                @foreach($githubRepos as $repo)
                                    <option value="{{ $repo['owner'] }}|{{ $repo['name'] }}|{{ $repo['default_branch'] }}">
                                        {{ $repo['full_name'] }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">No GitHub token configured. Enter details manually.</p>
                        @endif
                    </div>

                    <input type="hidden" name="owner" id="owner" value="{{ old('owner') }}">
                    <input type="hidden" name="name" id="name" value="{{ old('name') }}">

                    <div class="mb-4">
                        <label for="owner_display" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Owner</label>
                        <input type="text" id="owner_display" value="{{ old('owner') }}" class="w-full border-gray-300 rounded-lg shadow-sm" oninput="document.getElementById('owner').value=this.value" {{ count($githubRepos) > 0 ? 'readonly' : '' }}>
                        @error('owner')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="name_display" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                        <input type="text" id="name_display" value="{{ old('name') }}" class="w-full border-gray-300 rounded-lg shadow-sm" oninput="document.getElementById('name').value=this.value" {{ count($githubRepos) > 0 ? 'readonly' : '' }}>
                        @error('name')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="default_branch" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Branch</label>
                        <select name="default_branch" id="default_branch" class="w-full border-gray-300 rounded-lg shadow-sm">
                            <option value="main" {{ old('default_branch', 'main') === 'main' ? 'selected' : '' }}>main</option>
                        </select>
                        @error('default_branch')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-primary-button>Add Repository</x-primary-button>
                </form>
            </div>

            <script>
                function onRepoSelect(select) {
                    const parts = select.value.split('|');
                    if (parts.length === 3) {
                        document.getElementById('owner').value = parts[0];
                        document.getElementById('owner_display').value = parts[0];
                        document.getElementById('name').value = parts[1];
                        document.getElementById('name_display').value = parts[1];

                        fetch('{{ route("repositories.branches") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ owner: parts[0], name: parts[1] }),
                        })
                        .then(r => r.json())
                        .then(data => {
                            const branchSelect = document.getElementById('default_branch');
                            branchSelect.innerHTML = '';
                            (data.branches || []).forEach(b => {
                                const opt = document.createElement('option');
                                opt.value = b;
                                opt.textContent = b;
                                if (b === parts[2]) opt.selected = true;
                                branchSelect.appendChild(opt);
                            });
                        });
                    }
                }
            </script>
        </div>
    </div>
</x-app-layout>
