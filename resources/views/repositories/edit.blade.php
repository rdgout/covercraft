@extends('layouts.dashboard')

@section('title', 'Edit Repository - Coverage Tracker')

@section('content')
    <div class="mb-6">
        <a href="{{ route('repositories.index') }}" class="text-blue-600 hover:underline text-sm">&larr; Back</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Edit {{ $repository->owner }}/{{ $repository->name }}</h1>
    </div>

    <div class="bg-white rounded-lg shadow p-6 max-w-lg">
        <form action="{{ route('repositories.update', $repository) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Repository</label>
                <p class="text-gray-600">{{ $repository->owner }}/{{ $repository->name }}</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Secret</label>
                <code class="text-sm bg-gray-100 px-2 py-1 rounded">{{ $repository->webhook_secret ?? 'Not set' }}</code>
            </div>

            <div class="mb-6">
                <label for="default_branch" class="block text-sm font-medium text-gray-700 mb-1">Default Branch</label>
                <input type="text" name="default_branch" id="default_branch" value="{{ old('default_branch', $repository->default_branch) }}" class="w-full border-gray-300 rounded-lg shadow-sm">
                @error('default_branch')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Update</button>
        </form>
    </div>
@endsection
