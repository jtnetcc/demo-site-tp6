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
        $redirect = $this->settingsRedirect($request);

        try {
            (new AdminSettingService())->update($request->post());
            return $this->ok($redirect, '站点设置已保存');
        } catch (RuntimeException $e) {
            return $this->fail($redirect, $e->getMessage());
        }
    }

    private function settingsRedirect(Request $request): string
    {
        $tab = (string) $request->post('active_tab', '');
        $allowed = ['settings-base', 'settings-header', 'settings-footer', 'settings-seo', 'settings-storage', 'settings-recovery', 'settings-other', 'settings-json'];

        if (!in_array($tab, $allowed, true)) {
            $resetTabs = [
                'base_info' => 'settings-base',
                'header' => 'settings-header',
                'footer' => 'settings-footer',
                'seo' => 'settings-seo',
                'storage' => 'settings-storage',
                'passwordRecovery' => 'settings-recovery',
                'other' => 'settings-other',
                'homepage' => 'settings-other',
            ];
            $resets = (array) $request->post('reset_defaults', []);

            foreach ($resetTabs as $key => $value) {
                if (!empty($resets[$key])) {
                    $tab = $value;
                    break;
                }
            }
        }

        if (!in_array($tab, $allowed, true)) {
            $tab = 'settings-base';
        }

        return '/admin/settings#' . $tab;
    }
}
