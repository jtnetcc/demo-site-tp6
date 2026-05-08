<?php

namespace app\middleware;

use app\service\AuthService;
use Closure;
use RuntimeException;

class Admin
{
    public function handle($request, Closure $next)
    {
        try {
            $user = (new AuthService())->userFromRequest($request);

            if ($user->role !== 'ADMIN') {
                return json(['error' => '需要管理员权限', 'code' => 403], 403);
            }

            $request->user = $user;
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
