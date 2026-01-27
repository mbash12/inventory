<?php

namespace Src\Middleware;

use Closure;
use JWTAuth;
use Exception;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;

class JwtMiddleware extends BaseMiddleware
{

    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (Exception $e) {
            return response()->json(['code' => 401]);
            // if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
            //     return response()->json(['code' => 401, 'message' => 'Token is Invalid']);
            // } else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
            //     return response()->json(['code' => 401, 'message' => 'Token is Expired']);
            // } else {
            //     return response()->json(['code' => 401, 'message' => 'Authorization Token not found']);
            // }
        }
        return $next($request);
    }
}
