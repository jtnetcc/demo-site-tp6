<?php

namespace app\controller;

use app\model\User;
use app\service\AuthService;
use app\service\SiteSettingService;
use think\Request;

abstract class WebController
{
    protected function currentUser(): ?User
    {
        $userId = (int) (session('web_user_id') ?: 0);

        if ($userId <= 0) {
            return null;
        }

        $user = User::find($userId);

        if (!$user) {
            return null;
        }

        try {
            (new AuthService())->assertUserCanLogin($user);
        } catch (\RuntimeException) {
            return null;
        }

        return $user;
    }

    protected function viewData(array $data = []): array
    {
        $path = '/' . trim(request()->pathinfo(), '/');

        $siteSettings = (new SiteSettingService())->settings();

        return array_merge([
            'currentUser' => $this->currentUser(),
            'currentPath' => $path === '/index' ? '/' : $path,
            'flashSuccess' => session('flash_success'),
            'flashError' => session('flash_error'),
            'webCsrfToken' => $this->webCsrfToken(),
            'siteSettings' => $siteSettings,
            'siteName' => $siteSettings['base_info']['siteName'],
            'maintenanceEnabled' => (bool) ($siteSettings['other']['maintenanceEnabled'] ?? false),
            'maintenanceNotice' => (string) ($siteSettings['other']['maintenanceNotice'] ?? ''),
        ], $data);
    }

    protected function webCsrfToken(): string
    {
        $token = (string) (session('web_csrf_token') ?: '');

        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            session('web_csrf_token', $token);
        }

        return $token;
    }

    protected function clearFlash(): void
    {
        session('flash_success', null);
        session('flash_error', null);
    }

    protected function safeReturnTo(?string $returnTo, string $fallback = '/'): string
    {
        if (!$returnTo || str_starts_with($returnTo, '//') || preg_match('#^[a-z][a-z0-9+.-]*://#i', $returnTo)) {
            return $fallback;
        }

        return str_starts_with($returnTo, '/') ? $returnTo : $fallback;
    }

    protected function requireAjaxUser(Request $request): ?User
    {
        $user = $this->currentUser();

        return $user ?: null;
    }

    protected function success(array $data = [], string $message = '操作成功')
    {
        return json(['data' => $data, 'message' => $message]);
    }

    protected function error(string $message, int $code)
    {
        return json(['error' => $message, 'code' => $code], $code);
    }
}
