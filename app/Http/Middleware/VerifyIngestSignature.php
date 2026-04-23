<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIngestSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->attributes->get('project');

        if (! $project instanceof Project) {
            $token = (string) $request->route('project_token');
            $project = Project::query()->where('token', $token)->first();

            if (! $project) {
                return response()->json(['message' => 'Unknown project token.'], 404);
            }

            $request->attributes->set('project', $project);
        }

        $header = (string) $request->header('X-Signature', '');
        $provided = str_starts_with($header, 'sha256=') ? substr($header, 7) : $header;

        $expected = hash_hmac('sha256', $request->getContent(), $project->secret);

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}
