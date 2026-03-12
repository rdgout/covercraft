<?php

namespace App\Http\Middleware;

use App\Models\TeamAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $accessToken = TeamAccessToken::findToken($token);

        if (! $accessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $request->attributes->set('authenticated_team_id', $accessToken->team_id);
        $request->attributes->set('team_access_token', $accessToken);

        dispatch(fn () => $accessToken->markAsUsed())->afterResponse();

        return $next($request);
    }
}
