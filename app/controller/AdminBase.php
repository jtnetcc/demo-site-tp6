<?php

namespace app\controller;

use app\model\User;
use Throwable;
use think\Request;

abstract class AdminBase extends WebController
{
    protected function adminUser(Request $request): User
    {
        return $request->user ?: $this->currentUser();
    }

    protected function render(string $template, array $data = [])
    {
        $data = $this->viewData(array_merge([
            'adminCsrfToken' => $this->csrfToken(),
        ], $data));
        $this->clearFlash();

        return view($template, $data);
    }

    protected function csrfToken(): string
    {
        $token = (string) (session('admin_csrf_token') ?: '');

        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            session('admin_csrf_token', $token);
        }

        return $token;
    }

    protected function friendlyError(Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'Duplicate entry')) {
            return '保存失败：记录已存在，请检查唯一字段';
        }

        if (str_contains($message, 'foreign key constraint') || str_contains($message, 'Integrity constraint violation')) {
            return '操作失败：存在关联数据，不能删除或修改';
        }

        return $message;
    }

    protected function ok(string $path, string $message)
    {
        session('flash_success', $message);

        return redirect($path);
    }

    protected function fail(string $path, string $message)
    {
        session('flash_error', $message);

        return redirect($path);
    }
}
