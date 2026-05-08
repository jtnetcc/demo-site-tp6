<?php

namespace app\controller;

use app\service\HomePageService;
use think\Request;

class Index extends WebController
{
    public function index(Request $request)
    {
        $data = (new HomePageService())->data($this->currentUser());
        $data = $this->viewData($data);
        $this->clearFlash();

        return view('index/index', $data);
    }
}
