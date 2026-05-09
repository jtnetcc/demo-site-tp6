<?php

namespace app\service;

use app\model\SiteSetting;

class SiteSettingService
{
    public function settings(): array
    {
        return $this->publicSettings($this->settingsWithSecrets());
    }

    public function settingsWithSecrets(): array
    {
        $setting = SiteSetting::find(1);
        $data = $setting ? [
            'base_info' => is_array($setting->base_info) ? $setting->base_info : [],
            'header' => is_array($setting->header) ? $setting->header : [],
            'footer' => is_array($setting->footer) ? $setting->footer : [],
            'seo' => is_array($setting->seo) ? $setting->seo : [],
            'other' => is_array($setting->other) ? $setting->other : [],
        ] : [];

        $settings = [];

        foreach ($this->defaults() as $field => $defaults) {
            $settings[$field] = array_replace_recursive($defaults, $data[$field] ?? []);
        }

        $settings['base_info']['logoUrl'] = $this->assetUrl((string) ($settings['base_info']['logoObjectKey'] ?? ''));
        $settings['base_info']['faviconUrl'] = $this->assetUrl((string) ($settings['base_info']['faviconObjectKey'] ?? ''));
        $settings['seo']['ogImageUrl'] = $this->assetUrl((string) ($settings['seo']['ogImageObjectKey'] ?? ''));
        $settings['seo']['shareImageUrl'] = $this->assetUrl((string) ($settings['seo']['shareImageObjectKey'] ?? ''));
        $settings['seo']['homeKeywordsText'] = implode(',', array_filter(array_map('strval', (array) ($settings['seo']['homeKeywords'] ?? []))));

        return $settings;
    }

    private function publicSettings(array $settings): array
    {
        if (isset($settings['other']['netdisk']['baidu'])) {
            $settings['other']['netdisk']['baidu']['accessToken'] = '';
            $settings['other']['netdisk']['baidu']['cookie'] = '';
        }

        if (isset($settings['other']['passwordRecovery']['email']['smtp'])) {
            $settings['other']['passwordRecovery']['email']['smtp']['password'] = '';
        }

        if (isset($settings['other']['passwordRecovery']['phone'])) {
            $settings['other']['passwordRecovery']['phone']['apiKey'] = '';
            $settings['other']['passwordRecovery']['phone']['secret'] = '';
            $settings['other']['passwordRecovery']['phone']['headersJson'] = '';
        }

        return $settings;
    }

    public function defaults(): array
    {
        return [
            'base_info' => [
                'siteName' => '在线学习平台',
                'siteSubtitle' => '服务端渲染课程与视频学习平台',
                'siteUrl' => '',
                'recordNumber' => '',
                'contactPhone' => '',
                'contactEmail' => '',
                'logoObjectKey' => '',
                'faviconObjectKey' => '',
            ],
            'header' => [
                'announcementEnabled' => false,
                'announcementText' => '',
                'contactPhone' => '',
                'contactEmail' => '',
                'socialLinks' => [],
                'customHtml' => '',
            ],
            'footer' => [
                'copyrightText' => '在线学习平台',
                'menuLinks' => [],
                'techSupportText' => '',
                'customHtml' => '',
            ],
            'seo' => [
                'homeTitle' => '在线学习平台',
                'homeKeywords' => [],
                'homeDescription' => '',
                'ogImageObjectKey' => '',
                'shareImageObjectKey' => '',
                'analyticsHtml' => '',
            ],
            'other' => [
                'maintenanceEnabled' => false,
                'maintenanceNotice' => '',
                'defaultLanguage' => 'zh-CN',
                'timezone' => 'Asia/Shanghai',
                'storage' => [
                    'driver' => 'local',
                    'uploadPath' => 'public/uploads',
                    'publicBaseUrl' => '',
                    'netdiskLinkFormat' => 'https://网盘域名/分享路径?pwd=提取码',
                ],
                'homepage' => [
                    'hero' => [
                        'kicker' => 'ONLINE LEARNING',
                        'title' => '系统学习，高效进阶',
                        'description' => '精选视频、专题课程、会员权限和学习记录全部服务端渲染，打开即学，跨设备延续你的学习进度。',
                        'primaryButtonText' => '浏览课程',
                        'primaryButtonUrl' => '/courses',
                        'secondaryButtonText' => '观看视频',
                        'secondaryButtonUrl' => '/videos',
                        'userButtonText' => '进入我的学习',
                        'userButtonUrl' => '/me',
                        'guestButtonText' => '免费注册',
                        'guestButtonUrl' => '/register',
                    ],
                    'featureCards' => [
                        ['badge' => '快速访问', 'title' => '服务端渲染', 'description' => '页面结构清晰，首屏打开即显示内容。'],
                        ['badge' => '权限体系', 'title' => '会员与授权', 'description' => '支持普通、会员、超级会员与课程单独授权。'],
                        ['badge' => '学习闭环', 'title' => '记录与收藏', 'description' => '保留观看历史和收藏内容，方便持续学习。'],
                        ['badge' => '安全播放', 'title' => '签名播放', 'description' => '按用户权限生成播放地址，减少资源被直接复制传播。'],
                        ['badge' => '移动适配', 'title' => '多端访问', 'description' => '兼容电脑、平板和手机页面，学习内容随时打开。'],
                    ],
                ],
                'netdisk' => [
                    'baidu' => [
                        'enabled' => false,
                        'mode' => 'direct-or-external',
                        'resolverEndpoint' => '',
                        'accessToken' => '',
                        'cookie' => '',
                        'directUrlTtlSec' => 900,
                        'externalFallback' => true,
                    ],
                ],
                'passwordRecovery' => [
                    'enabled' => false,
                    'expiresMinutes' => 30,
                    'codeLength' => 6,
                    'maxAttempts' => 5,
                    'resendCooldownSeconds' => 60,
                    'email' => [
                        'enabled' => false,
                        'driver' => 'smtp',
                        'fromEmail' => '',
                        'fromName' => '',
                        'smtp' => [
                            'host' => '',
                            'port' => 587,
                            'username' => '',
                            'password' => '',
                            'encryption' => 'tls',
                            'timeoutSeconds' => 10,
                        ],
                    ],
                    'phone' => [
                        'enabled' => false,
                        'provider' => 'none',
                        'endpoint' => '',
                        'method' => 'POST',
                        'apiKey' => '',
                        'secret' => '',
                        'headersJson' => '',
                        'template' => '您的验证码是 {code}，{minutes} 分钟内有效。',
                        'signName' => '',
                    ],
                ],
            ],
        ];
    }

    private function assetUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $value)) {
            return $value;
        }

        return '/' . ltrim($value, '/');
    }
}
