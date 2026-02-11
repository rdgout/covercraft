@extends('layouts.dashboard')

@section('title', 'API Tokens')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">API Tokens</h1>
    </div>

    @if(session('token'))
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        <strong>Save this token now - it will not be shown again:</strong>
                    </p>
                    <div class="mt-2 flex items-center space-x-2">
                        <code id="token-value" class="block bg-white px-3 py-2 rounded border border-yellow-200 text-sm font-mono">{{ session('token') }}</code>
                        <button onclick="copyToken()" class="px-3 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 text-sm">
                            Copy
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-400 p-4">
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    @if($teams->count() > 1)
        <div class="bg-white shadow rounded-lg p-4">
            <label for="team-select" class="block text-sm font-medium text-gray-700 mb-2">Filter by Team</label>
            <select id="team-select" onchange="window.location.href = this.value" class="block w-full md:w-auto rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="{{ route('tokens.index') }}" {{ !$selectedTeamId ? 'selected' : '' }}>All Teams</option>
                @foreach($teams as $team)
                    <option value="{{ route('tokens.index', ['team' => $team->id]) }}" {{ $selectedTeamId == $team->id ? 'selected' : '' }}>
                        {{ $team->name }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Your Tokens</h2>
            <button onclick="toggleCreateForm()" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                Create New Token
            </button>
        </div>

        <div id="create-form" class="hidden mb-6 p-4 bg-gray-50 rounded border border-gray-200">
            <form action="{{ route('tokens.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="team_id" class="block text-sm font-medium text-gray-700">Team</label>
                    <select name="team_id" id="team_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select a team</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}" {{ $selectedTeamId == $team->id ? 'selected' : '' }}>
                                {{ $team->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('team_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Token Name</label>
                    <input type="text" name="name" id="name" required placeholder="e.g., CI/CD Pipeline" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex space-x-2">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                        Create Token
                    </button>
                    <button type="button" onclick="toggleCreateForm()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        @if($tokens->isEmpty())
            <p class="text-gray-500 text-center py-8">No tokens created yet. Create your first token to get started.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            @if($teams->count() > 1)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team</th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Used</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($tokens as $token)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $token->name }}
                                </td>
                                @if($teams->count() > 1)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $token->team->name }}
                                    </td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $token->createdBy?->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $token->created_at->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Never' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <form action="{{ route('tokens.destroy', $token) }}" method="POST" onsubmit="return confirm('Are you sure you want to revoke this token?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded">
            <p class="text-sm text-blue-700">
                <strong>⚠️ Important:</strong> Tokens provide access to upload coverage for ALL repositories in the selected team.
                Anyone with this token can upload coverage data. Keep tokens secure and revoke them if they are compromised.
            </p>
        </div>
    </div>
</div>

<script>
function toggleCreateForm() {
    const form = document.getElementById('create-form');
    form.classList.toggle('hidden');
}

function copyToken() {
    const tokenValue = document.getElementById('token-value').textContent;
    navigator.clipboard.writeText(tokenValue).then(() => {
        alert('Token copied to clipboard!');
    });
}
</script>
@endsection
