<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamAccessTokenRequest;
use App\Models\TeamAccessToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamAccessTokenController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $selectedTeamId = $request->query('team');

        $teamIds = ($selectedTeamId && $user->teams()->where('teams.id', $selectedTeamId)->exists())
            ? collect([$selectedTeamId])
            : $user->getTeamIds();

        $tokens = TeamAccessToken::whereIn('team_id', $teamIds)
            ->with(['team', 'createdBy'])
            ->latest()
            ->get();

        return view('tokens.index', [
            'tokens' => $tokens,
            'teams' => $user->teams,
            'selectedTeamId' => $selectedTeamId,
        ]);
    }

    public function store(StoreTeamAccessTokenRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);

        TeamAccessToken::create([
            'team_id' => $validated['team_id'],
            'created_by_user_id' => auth()->id(),
            'name' => $validated['name'],
            'token' => $hashedToken,
        ]);

        return redirect()->route('tokens.index', ['team' => $validated['team_id']])
            ->with('token', $plainToken)
            ->with('success', 'Token created successfully! Save this token now - it will not be shown again.');
    }

    public function destroy(TeamAccessToken $token): RedirectResponse
    {
        abort_unless(
            auth()->user()->belongsToTeam($token->team),
            403,
            'You do not have permission to manage this team\'s tokens.'
        );

        $teamId = $token->team_id;
        $token->delete();

        return redirect()->route('tokens.index', ['team' => $teamId])
            ->with('success', 'Token revoked successfully.');
    }
}
