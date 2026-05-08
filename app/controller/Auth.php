<?php

namespace app\controller;

use app\service\AuthService;
use app\validate\LoginValidate;
use app\validate\RegisterValidate;
use RuntimeException;
use think\Request;

class Auth
{
    public function login(Request $request)
    {
        $data = $request->post();
        $validate = new LoginValidate();

        if (!$validate->check($data)) {
            return $this->error($validate->getError(), 400);
        }

        try {
            $result = (new AuthService())->login((string) $data['account'], (string) $data['password']);
            return $this->success($result);
        } catch (RuntimeException $e) {
            return $this->exception($e);
        }
    }

    public function register(Request $request)
    {
        $data = $request->post();
        $validate = new RegisterValidate();

        if (!$validate->check($data)) {
            return $this->error($validate->getError(), 400);
        }

        try {
            $result = (new AuthService())->register($data);
            return $this->success($result);
        } catch (RuntimeException $e) {
            return $this->exception($e);
        }
    }

    public function logout(Request $request)
    {
        return $this->success((new AuthService())->logout());
    }

    public function user(Request $request)
    {
        $user = $request->user ?? null;

        if (!$user) {
            return $this->error('请先登录', 401);
        }

        return $this->success((new AuthService())->sanitizeUser($user));
    }

    private function success(array $data = [], string $message = '操作成功')
    {
        return json(['data' => $data, 'message' => $message]);
    }

    private function error(string $message, int $code)
    {
        return json(['error' => $message, 'code' => $code], $code);
    }

    private function exception(RuntimeException $e)
    {
        $code = (int) $e->getCode();
        $code = $code >= 400 && $code < 600 ? $code : 400;

        return $this->error($e->getMessage(), $code);
    }
}
