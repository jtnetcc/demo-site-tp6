<?php

namespace app\controller;

use app\service\AdminDashboardService;
use think\Request;

class Admin extends AdminBase
{
    public function index(Request $request)
    {
        return $this->render('admin/index', array_merge([
            'title' => '后台概览',
        ], (new AdminDashboardService())->dashboard()));
    }
}
