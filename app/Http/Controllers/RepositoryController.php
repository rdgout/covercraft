<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRepositoryRequest;
use App\Models\Repository;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepositoryController extends Controller
{
    public function __construct(private GitHubService $githubService) {}

    public function index(): View
    {
        $repositories = Repository::query()
            ->with('latestCoverageReport')
            ->get();

        return view('repositories.index', compact('repositories'));
    }

    public function create(): View
    {
        $githubRepos = [];

        try {
            $githubRepos = $this->githubService->listUserRepositories();
        } catch (\Throwable) {
            // GitHub token may not be configured
        }

        return view('repositories.create', compact('githubRepos'));
    }

    public function fetchBranches(Request $request): JsonResponse
    {
        $request->validate([
            'owner' => ['required', 'string'],
            'name' => ['required', 'string'],
        ]);

        try {
            $branches = $this->githubService->listBranches($request->input('owner'), $request->input('name'));
        } catch (\Throwable) {
            $branches = [];
        }

        return response()->json(['branches' => $branches]);
    }

    public function store(StoreRepositoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $repository = Repository::create([
            'owner' => $validated['owner'],
            'name' => $validated['name'],
            'github_url' => "https://github.com/{$validated['owner']}/{$validated['name']}",
            'default_branch' => $validated['default_branch'],
            'webhook_secret' => bin2hex(random_bytes(20)),
        ]);

        return redirect()
            ->route('repositories.index')
            ->with('success', "Repository {$repository->owner}/{$repository->name} added.");
    }

    public function edit(Repository $repository): View
    {
        return view('repositories.edit', compact('repository'));
    }

    public function update(Request $request, Repository $repository): RedirectResponse
    {
        $validated = $request->validate([
            'default_branch' => ['required', 'string', 'max:255'],
        ]);

        $repository->update($validated);

        return redirect()
            ->route('repositories.index')
            ->with('success', 'Repository updated.');
    }

    public function destroy(Repository $repository): RedirectResponse
    {
        $repository->delete();

        return redirect()
            ->route('repositories.index')
            ->with('success', 'Repository deleted.');
    }
}
