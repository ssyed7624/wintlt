<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;

class Authenticate
{
    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if ($this->auth->guard($guard)->guest()) {

            $responseData['status']         = 'failed';
            $responseData['status_code']    = 401;
            $responseData['message']        = 'Unauthorized Access';
            $responseData['short_text']     = 'unauthorized';
            $responseData['errors']         = ['error' => ['Unauthorized Access']];
            return response()->json($responseData);
        }

        $request->guard = $guard;

        return $next($request);
    }
}
