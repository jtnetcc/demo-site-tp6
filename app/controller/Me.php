<?php

namespace app\controller;

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
}
