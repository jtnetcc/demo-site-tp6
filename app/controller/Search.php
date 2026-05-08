<?php

namespace app\controller;

use app\service\SearchService;
use think\Request;

class Search extends WebController
{
    public function index(Request $request)
    {
        $data = (new SearchService())->search($request->get());
        $data = $this->viewData($data);
        $this->clearFlash();

        return view('search/index', $data);
    }
}
