<?php

namespace app\controller;

use app\service\AccountVerificationService;
use app\service\AuthService;
use app\service\MeService;
use RuntimeException;
use think\Request;

class Me extends WebController
{
    public function index(Request $request)
    {
        $data = (new MeService())->dashboard($request->user);
        $data = $this->viewData($data);
        $this->clearFlash();

        return view('me/index', $data);
    }

    public function profile(Request $request)
    {
        $service = new MeService();

        if ($request->isPost()) {
            try {
                $service->updateProfile($request->user, $request->post());
                session('flash_success', '资料已更新');
                return redirect('/me/profile');
            } catch (RuntimeException $e) {
                session('flash_error', $e->getMessage());
                return redirect('/me/profile');
            }
        }

        $data = $this->viewData($service->profile($request->user));
        $this->clearFlash();

        return view('me/profile', $data);
    }

    public function bindContact(Request $request)
    {
        $service = new MeService();

        if ($request->isPost()) {
            try {
                $user = $service->bindContact($request->user, $request->post());
                session('web_user', (new AuthService())->sanitizeUser($user));
                session('flash_success', '联系方式已绑定');
                return redirect('/me');
            } catch (RuntimeException $e) {
                session('flash_error', $e->getMessage());
                return redirect('/me/bind-contact');
            }
        }

        $data = $this->viewData([
            'user' => (new AuthService())->sanitizeUser($request->user),
            'channels' => (new AccountVerificationService())->channelOptions(),
        ]);
        $this->clearFlash();

        return view('me/bind_contact', $data);
    }

    public function sendBindContactCode(Request $request)
    {
        try {
            (new MeService())->requestBindCode(
                $request->user,
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

    public function history(Request $request)
    {
        $data = $this->viewData(['histories' => (new MeService())->history($request->user, $request->get())]);
        $this->clearFlash();

        return view('me/history', $data);
    }

    public function favorites(Request $request)
    {
        $data = $this->viewData(['favorites' => (new MeService())->favorites($request->user, $request->get())]);
        $this->clearFlash();

        return view('me/favorites', $data);
    }

    public function courses(Request $request)
    {
        $data = $this->viewData(['courses' => (new MeService())->courses($request->user)]);
        $this->clearFlash();

        return view('me/courses', $data);
    }

    private function codeResponse(Request $request, string $message, bool $success, int $status = 200)
    {
        if ($request->isAjax() || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest') {
            return $success ? json(['message' => $message]) : json(['error' => $message, 'code' => $status], $status);
        }

        session($success ? 'flash_success' : 'flash_error', $message);

        return redirect('/me/bind-contact');
    }
}
