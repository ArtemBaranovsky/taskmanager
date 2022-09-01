<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * This middleware check if the request has _token key and adds this into the Authorization header to take advantage of
 * the sanctum middleware
 */
class CheckTokenAndAddToHeaderMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $all = $request->all();

        if (isset($all['api_token'])) {
            Log::debug('token from http param', [$all['_token']]);
            $request->headers->set('Authorization', sprintf('%s %s', 'Bearer', $all['_token']));
        }

        if ($request->bearerToken()) {
            Log::debug('token from bearer param ', [$request->bearerToken()]);
            $request->headers->set('Authorization', sprintf('%s %s', 'Bearer', $request->bearerToken()));
            auth()->setUser(User::where('api_token', '=' , request()->bearerToken())->first());
        }

        return $next($request);
    }
}
