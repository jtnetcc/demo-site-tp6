<?php

namespace app\middleware;

use app\service\AuthService;
use Closure;
use RuntimeException;

class Auth
{
    public function handle($request, Closure $next)
    {
        try {
            $request->user = (new AuthService())->userFromRequest($request);
        } catch (RuntimeException $e) {
            $code = $this->statusCode($e, 401);
            return json(['error' => $e->getMessage(), 'code' => $code], $code);
        }

        return $next($request);
    }

    private function statusCode(RuntimeException $e, int $default): int
    {
        $code = (int) $e->getCode();

        return $code >= 400 && $code < 600 ? $code : $default;
    }
}
