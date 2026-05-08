<?php

namespace app\middleware;

use app\model\User;
use app\service\AuthService;
use Closure;
use RuntimeException;

class WebAuth
{
    public function handle($request, Closure $next)
    {
        $userId = (int) (session('web_user_id') ?: 0);

        if ($userId <= 0) {
            return $this->unauthenticated($request);
        }

        $user = User::find($userId);

        if (!$user) {
            session('web_user_id', null);
            return $this->unauthenticated($request);
        }

        try {
            (new AuthService())->assertUserCanLogin($user);
        } catch (RuntimeException $e) {
            session('web_user_id', null);
            session('web_user', null);
            return $this->unauthenticated($request, $e->getMessage());
        }

        if ($this->isStateChanging($request) && !$this->validCsrfToken($request)) {
            return $this->csrfFailure($request);
        }

        $request->user = $user;

        return $next($request);
    }

    private function isStateChanging($request): bool
    {
        $method = strtoupper((string) (method_exists($request, 'method') ? $request->method() : 'GET'));

        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function validCsrfToken($request): bool
    {
        $sessionToken = (string) (session('web_csrf_token') ?: '');
        $postedToken = (string) ($request->post('_csrf') ?: '');
        $headerToken = (string) ($request->header('X-CSRF-Token') ?: $request->header('X-CSRF-TOKEN') ?: '');
        $token = $headerToken !== '' ? $headerToken : $postedToken;

        return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
    }

    private function csrfFailure($request)
    {
        if ($this->isAjax($request)) {
            return json(['error' => '页面已过期，请刷新后重试', 'code' => 419], 419);
        }

        session('flash_error', '页面已过期，请重新提交');

        return redirect($this->refererPath($request));
    }

    private function unauthenticated($request, string $message = '请先登录')
    {
        $returnTo = urlencode($request->url(true));
        $loginUrl = '/login?returnTo=' . $returnTo;

        if ($this->isAjax($request)) {
            return json(['error' => $message, 'code' => 401, 'login_url' => $loginUrl], 401);
        }

        session('flash_error', $message);

        return redirect($loginUrl);
    }

    private function isAjax($request): bool
    {
        return $request->isAjax() || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }

    private function refererPath($request): string
    {
        $referer = (string) ($request->header('referer') ?: '');
        $path = $referer !== '' ? (parse_url($referer, PHP_URL_PATH) ?: '') : '';
        $query = $referer !== '' ? (parse_url($referer, PHP_URL_QUERY) ?: '') : '';

        if ($path === '' || !str_starts_with($path, '/')) {
            return '/';
        }

        return $query !== '' ? $path . '?' . $query : $path;
    }
}
