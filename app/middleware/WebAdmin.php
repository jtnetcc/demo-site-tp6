<?php

namespace app\middleware;

use app\model\User;
use app\service\AuthService;
use Closure;
use RuntimeException;

class WebAdmin
{
    public function handle($request, Closure $next)
    {
        $userId = (int) (session('web_user_id') ?: 0);

        if ($userId <= 0) {
            return $this->redirectToLogin($request);
        }

        $user = User::find($userId);

        if (!$user) {
            session('web_user_id', null);
            session('web_user', null);
            return $this->redirectToLogin($request);
        }

        try {
            (new AuthService())->assertUserCanLogin($user);
        } catch (RuntimeException $e) {
            session('web_user_id', null);
            session('web_user', null);
            return $this->redirectToLogin($request, $e->getMessage());
        }

        if ($user->role !== 'ADMIN') {
            return response('需要管理员权限', 403);
        }

        if ($this->isPost($request) && !$this->validCsrfToken($request)) {
            session('flash_error', '页面已过期，请重新提交');
            return redirect($this->refererPath($request));
        }

        $request->user = $user;

        return $next($request);
    }

    private function isPost($request): bool
    {
        return method_exists($request, 'isPost') ? $request->isPost() : strtoupper((string) $request->method()) === 'POST';
    }

    private function validCsrfToken($request): bool
    {
        $sessionToken = (string) (session('admin_csrf_token') ?: '');
        $postedToken = (string) ($request->post('_csrf') ?: '');
        $headerToken = (string) ($request->header('X-CSRF-Token') ?: $request->header('X-CSRF-TOKEN') ?: '');
        $token = $headerToken !== '' ? $headerToken : $postedToken;

        return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
    }

    private function refererPath($request): string
    {
        $referer = (string) ($request->header('referer') ?: '');
        $path = $referer !== '' ? (parse_url($referer, PHP_URL_PATH) ?: '') : '';

        return str_starts_with($path, '/admin') ? $path : '/admin';
    }

    private function redirectToLogin($request, string $message = '请先登录'): \think\response\Redirect
    {
        session('flash_error', $message);

        return redirect('/login?returnTo=' . urlencode($request->url(true)));
    }
}
