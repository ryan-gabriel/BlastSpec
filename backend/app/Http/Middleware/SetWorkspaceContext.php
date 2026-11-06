<?php

namespace BlastSpec\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class SetWorkspaceContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user !== null && isset($user->workspace_id)) {
            $request->attributes->set('workspace_id', (string) $user->workspace_id);
        }

        return $next($request);
    }
}
