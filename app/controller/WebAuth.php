<?php

namespace app\controller;

use app\service\AuthService;
use app\validate\LoginValidate;
use app\validate\RegisterValidate;
use RuntimeException;
use think\Request;

class WebAuth extends WebController
{
    public function login(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            $validate = new LoginValidate();

            if (!$validate->check($data)) {
                session('flash_error', $validate->getError());
                return redirect('/login?returnTo=' . urlencode((string) $request->post('returnTo', '/me')));
            }

            try {
                $result = (new AuthService())->login((string) $data['account'], (string) $data['password']);
                session('web_user_id', (int) $result['user']['id']);
                session('web_user', $result['user']);
                session('web_token', $result['token']);
                session('flash_success', '登录成功');
                return redirect($this->safeReturnTo($data['returnTo'] ?? null, '/me'));
            } catch (RuntimeException $e) {
                session('flash_error', $e->getMessage());
                return redirect('/login?returnTo=' . urlencode((string) ($data['returnTo'] ?? '/me')));
            }
        }

        $data = $this->viewData(['returnTo' => $request->get('returnTo', '/me')]);
        $this->clearFlash();

        return view('web_auth/login', $data);
    }

    public function register(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            $validate = new RegisterValidate();

            if (!$validate->check($data)) {
                session('flash_error', $validate->getError());
                return redirect('/register');
            }

            try {
                $result = (new AuthService())->register($data);
                session('web_user_id', (int) $result['user']['id']);
                session('web_user', $result['user']);
                session('web_token', $result['token']);
                session('flash_success', '注册成功');
                return redirect('/me');
            } catch (RuntimeException $e) {
                session('flash_error', $e->getMessage());
                return redirect('/register');
            }
        }

        $data = $this->viewData();
        $this->clearFlash();

        return view('web_auth/register', $data);
    }

    public function logout(Request $request)
    {
        session('web_user_id', null);
        session('web_user', null);
        session('web_token', null);
        (new AuthService())->logout();
        session('flash_success', '已退出登录');

        return redirect('/login');
    }
}
