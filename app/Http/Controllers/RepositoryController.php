<?php

namespace App\Http\Controllers;

use App\Contracts\GitHubServiceInterface;
use App\Http\Requests\CreateRepositoryRequest;
use App\Http\Requests\DeleteRepositoryRequest;
use App\Http\Requests\StoreRepositoryRequest;
use App\Http\Requests\UpdateRepositoryRequest;
use App\Http\Requests\ViewRepositoryRequest;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepositoryController extends Controller
{
    public function __construct(private GitHubServiceInterface $githubService) {}

    public function index(): View
    {
        $user = auth()->user();
        $teamIds = $user->getTeamIds();

        $repositories = Repository::query()
            ->forTeams($teamIds)
            ->with('latestCoverageReport', 'team')
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate(15);

        return view('repositories.index', compact('repositories'));
    }

    public function create(CreateRepositoryRequest $request): View
    {
        $githubRepos = [];

        try {
            $githubRepos = $this->githubService->listUserRepositories();
        } catch (\Throwable) {
            // GitHub token may not be configured
        }

        $teams = auth()->user()->teams;

        return view('repositories.create', compact('githubRepos', 'teams'));
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
            'team_id' => $validated['team_id'],
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

    public function edit(ViewRepositoryRequest $request, Repository $repository): View
    {
        return view('repositories.edit', compact('repository'));
    }

    public function update(UpdateRepositoryRequest $request, Repository $repository): RedirectResponse
    {
        $validated = $request->validated();

        $repository->update($validated);

        return redirect()
            ->route('repositories.index')
            ->with('success', 'Repository updated.');
    }

    public function destroy(DeleteRepositoryRequest $request, Repository $repository): RedirectResponse
    {
        $repository->delete();

        return redirect()
            ->route('repositories.index')
            ->with('success', 'Repository deleted.');
    }
}
