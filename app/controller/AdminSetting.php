<?php

namespace app\controller;

use app\service\AdminSettingService;
use RuntimeException;
use think\Request;

class AdminSetting extends AdminBase
{
    public function index(Request $request)
    {
        return $this->render('admin/settings/index', array_merge([
            'title' => '站点设置',
        ], (new AdminSettingService())->formData()));
    }

    public function update(Request $request)
    {
        try {
            (new AdminSettingService())->update($request->post());
            return $this->ok('/admin/settings', '站点设置已保存');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/settings', $e->getMessage());
        }
    }
}
