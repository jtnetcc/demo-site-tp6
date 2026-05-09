<?php

namespace app\controller;

use app\service\AccountVerificationService;
use app\service\AuthService;
use app\service\PasswordRecoveryService;
use app\validate\ForgotPasswordValidate;
use app\validate\LoginValidate;
use app\validate\RegisterValidate;
use app\validate\ResetPasswordValidate;
use RuntimeException;
use think\Request;

class WebAuth extends WebController
{
    public function login(Request $request)
    {
        if ($request->isPost()) {
            if (!$this->validWebCsrf($request)) {
                session('flash_error', '页面已过期，请刷新后重试');
                return redirect('/login?returnTo=' . urlencode((string) $request->post('returnTo', '/me')));
            }

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
                session('flash_success', '登录成功');

                if (empty($result['user']['contact_bound'])) {
                    return redirect('/me/bind-contact');
                }

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
            if (!$this->validWebCsrf($request)) {
                session('flash_error', '页面已过期，请刷新后重试');
                return redirect('/register');
            }

            $auth = new AuthService();
            $data = $request->post();

            try {
                $data = $auth->normalizeRegisterData($data);
            } catch (RuntimeException $e) {
                session('flash_error', $e->getMessage());
                return redirect('/register');
            }

            $validate = new RegisterValidate();

            if (!$validate->check($data)) {
                session('flash_error', $validate->getError());
                return redirect('/register');
            }

            try {
                $result = $auth->register($data);
                session('web_user_id', (int) $result['user']['id']);
                session('web_user', $result['user']);
                session('flash_success', '注册成功');

                if (empty($result['user']['contact_bound'])) {
                    return redirect('/me/bind-contact');
                }

                return redirect('/me');
            } catch (RuntimeException $e) {
                session('flash_error', $e->getMessage());
                return redirect('/register');
            }
        }

        $data = $this->viewData(['channels' => (new AccountVerificationService())->channelOptions()]);
        $this->clearFlash();

        return view('web_auth/register', $data);
    }

    public function sendRegisterCode(Request $request)
    {
        if (!$this->validWebCsrf($request)) {
            return $this->codeResponse($request, '页面已过期，请刷新后重试', false, 419);
        }

        try {
            (new AuthService())->requestRegisterCode(
                (string) $request->post('channel', ''),
                (string) $request->post('account', ''),
                $request
            );

            return $this->codeResponse($request, '验证码已发送', true);
        } catch (RuntimeException $e) {
            $code = (int) $e->getCode();
            return $this->codeResponse($request, $e->getMessage(), false, $code >= 400 && $code < 600 ? $code : 400);
        }
    }

    public function forgotPassword(Request $request)
    {
        $service = new PasswordRecoveryService();

        if ($request->isPost()) {
            if (!$this->validWebCsrf($request)) {
                session('flash_error', '页面已过期，请刷新后重试');
                return redirect('/forgot-password');
            }

            $data = $request->post();
            $validate = new ForgotPasswordValidate();

            if (!$validate->check($data)) {
                session('flash_error', $validate->getError());
                return redirect('/forgot-password');
            }

            try {
                $service->requestReset((string) $data['account'], (string) $data['channel'], $request);
                session('flash_success', '如果账号信息存在，我们已发送重置说明。');

                if (($data['channel'] ?? '') === 'phone') {
                    return redirect('/reset-password?channel=phone&account=' . urlencode((string) $data['account']));
                }

                return redirect('/login');
            } catch (RuntimeException $e) {
                session('flash_error', $e->getMessage());
                return redirect('/forgot-password');
            }
        }

        $data = $this->viewData(['channels' => $service->channelOptions()]);
        $this->clearFlash();

        return view('web_auth/forgot_password', $data);
    }

    public function resetPassword(Request $request)
    {
        $service = new PasswordRecoveryService();

        if ($request->isPost()) {
            $data = $request->post();

            if (!$this->validWebCsrf($request)) {
                session('flash_error', '页面已过期，请刷新后重试');
                return redirect($this->resetPasswordUrl($data));
            }

            $validate = new ResetPasswordValidate();

            if (!$validate->check($data)) {
                session('flash_error', $validate->getError());
                return redirect($this->resetPasswordUrl($data));
            }

            if ((string) ($data['password'] ?? '') !== (string) ($data['password_confirm'] ?? '')) {
                session('flash_error', '两次输入的密码不一致');
                return redirect($this->resetPasswordUrl($data));
            }

            try {
                if (!empty($data['selector']) && !empty($data['token'])) {
                    $service->resetByEmailToken((string) $data['selector'], (string) $data['token'], (string) $data['password']);
                } else {
                    $service->resetByPhoneCode((string) ($data['account'] ?? ''), (string) ($data['code'] ?? ''), (string) $data['password']);
                }

                session('web_user_id', null);
                session('web_user', null);
                session('flash_success', '密码已重置，请使用新密码登录');
                return redirect('/login');
            } catch (RuntimeException $e) {
                session('flash_error', $e->getMessage());
                return redirect($this->resetPasswordUrl($data));
            }
        }

        $data = $this->viewData([
            'channels' => $service->channelOptions(),
            'selector' => (string) $request->get('selector', ''),
            'token' => (string) $request->get('token', ''),
            'channel' => (string) $request->get('channel', ''),
            'account' => (string) $request->get('account', ''),
        ]);
        $this->clearFlash();

        return view('web_auth/reset_password', $data);
    }

    public function logout()
    {
        session('web_user_id', null);
        session('web_user', null);
        (new AuthService())->logout();
        session('flash_success', '已退出登录');

        return redirect('/login');
    }

    private function codeResponse(Request $request, string $message, bool $success, int $status = 200)
    {
        if ($this->isAjax($request)) {
            return $success ? json(['message' => $message]) : json(['error' => $message, 'code' => $status], $status);
        }

        session($success ? 'flash_success' : 'flash_error', $message);

        return redirect('/register');
    }

    private function resetPasswordUrl(array $data): string
    {
        if (!empty($data['selector']) && !empty($data['token'])) {
            return '/reset-password?selector=' . urlencode((string) $data['selector']) . '&token=' . urlencode((string) $data['token']);
        }

        if (!empty($data['account'])) {
            return '/reset-password?channel=phone&account=' . urlencode((string) $data['account']);
        }

        return '/reset-password';
    }

    private function validWebCsrf(Request $request): bool
    {
        $sessionToken = (string) (session('web_csrf_token') ?: '');
        $postedToken = (string) ($request->post('_csrf') ?: '');

        return $sessionToken !== '' && $postedToken !== '' && hash_equals($sessionToken, $postedToken);
    }

    private function isAjax(Request $request): bool
    {
        return $request->isAjax() || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }
}
