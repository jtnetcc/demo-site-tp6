<?php

namespace app\service;

use app\model\SiteSetting;
use RuntimeException;

class AdminSettingService
{
    private array $fields = ['base_info', 'header', 'footer', 'seo', 'other'];

    public function formData(): array
    {
        $setting = $this->setting();
        $form = [];
        $json = [];

        foreach ($this->fields as $field) {
            $value = $this->mergeDefaults($field, is_array($setting->$field) ? $setting->$field : []);
            $form[$field] = $value;
            $json[$field] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $form['header']['socialLinkSlots'] = $this->linkSlots($form['header']['socialLinks'], 3);
        $form['footer']['menuLinkSlots'] = $this->linkSlots($form['footer']['menuLinks'], 5);
        $form['seo']['homeKeywordsText'] = implode(',', $form['seo']['homeKeywords']);
        $form['storage'] = $form['other']['storage'];
        $form['homepage'] = $this->homepage($form['other']['homepage'] ?? []);
        $form['netdisk'] = $form['other']['netdisk'];
        $form['passwordRecovery'] = $this->passwordRecovery($form['other']['passwordRecovery'] ?? [], $form['other']['passwordRecovery'] ?? []);

        return ['setting' => $setting, 'form' => $form, 'json' => $json];
    }

    public function update(array $data): SiteSetting
    {
        $setting = $this->setting();
        $existingOther = $this->mergeDefaults('other', is_array($setting->other) ? $setting->other : []);
        $payload = [
            'base_info' => [
                'siteName' => $this->string($data['base_info']['siteName'] ?? ''),
                'siteSubtitle' => $this->string($data['base_info']['siteSubtitle'] ?? ''),
                'siteUrl' => $this->string($data['base_info']['siteUrl'] ?? ''),
                'recordNumber' => $this->string($data['base_info']['recordNumber'] ?? ''),
                'contactPhone' => $this->string($data['base_info']['contactPhone'] ?? ''),
                'contactEmail' => $this->string($data['base_info']['contactEmail'] ?? ''),
                'logoObjectKey' => $this->string($data['base_info']['logoObjectKey'] ?? ''),
                'faviconObjectKey' => $this->string($data['base_info']['faviconObjectKey'] ?? ''),
            ],
            'header' => [
                'announcementEnabled' => !empty($data['header']['announcementEnabled']),
                'announcementText' => $this->string($data['header']['announcementText'] ?? ''),
                'contactPhone' => $this->string($data['header']['contactPhone'] ?? ''),
                'contactEmail' => $this->string($data['header']['contactEmail'] ?? ''),
                'socialLinks' => $this->links($data['header']['socialLinks'] ?? []),
                'customHtml' => $this->string($data['header']['customHtml'] ?? ''),
            ],
            'footer' => [
                'copyrightText' => $this->string($data['footer']['copyrightText'] ?? ''),
                'menuLinks' => $this->links($data['footer']['menuLinks'] ?? []),
                'techSupportText' => $this->string($data['footer']['techSupportText'] ?? ''),
                'customHtml' => $this->string($data['footer']['customHtml'] ?? ''),
            ],
            'seo' => [
                'homeTitle' => $this->string($data['seo']['homeTitle'] ?? ''),
                'homeKeywords' => $this->keywords($data['seo']['homeKeywords'] ?? ''),
                'homeDescription' => $this->string($data['seo']['homeDescription'] ?? ''),
                'ogImageObjectKey' => $this->string($data['seo']['ogImageObjectKey'] ?? ''),
                'shareImageObjectKey' => $this->string($data['seo']['shareImageObjectKey'] ?? ''),
                'analyticsHtml' => $this->string($data['seo']['analyticsHtml'] ?? ''),
            ],
            'other' => [
                'maintenanceEnabled' => !empty($data['other']['maintenanceEnabled']),
                'maintenanceNotice' => $this->string($data['other']['maintenanceNotice'] ?? ''),
                'defaultLanguage' => $this->string($data['other']['defaultLanguage'] ?? 'zh-CN'),
                'timezone' => $this->string($data['other']['timezone'] ?? 'Asia/Shanghai'),
                'storage' => [
                    'driver' => $this->storageDriver($data['storage']['driver'] ?? 'local'),
                    'uploadPath' => $this->string($data['storage']['uploadPath'] ?? 'public/uploads'),
                    'publicBaseUrl' => $this->string($data['storage']['publicBaseUrl'] ?? ''),
                    'netdiskLinkFormat' => $this->string($data['storage']['netdiskLinkFormat'] ?? ''),
                ],
                'homepage' => $this->homepage($data['other']['homepage'] ?? []),
                'netdisk' => [
                    'baidu' => [
                        'enabled' => !empty($data['netdisk']['baidu']['enabled']),
                        'mode' => $this->string($data['netdisk']['baidu']['mode'] ?? 'direct-or-external'),
                        'resolverEndpoint' => $this->string($data['netdisk']['baidu']['resolverEndpoint'] ?? ''),
                        'accessToken' => $this->string($data['netdisk']['baidu']['accessToken'] ?? ''),
                        'cookie' => $this->string($data['netdisk']['baidu']['cookie'] ?? ''),
                        'directUrlTtlSec' => max(60, (int) ($data['netdisk']['baidu']['directUrlTtlSec'] ?? 900)),
                        'externalFallback' => !empty($data['netdisk']['baidu']['externalFallback']),
                    ],
                ],
                'passwordRecovery' => $this->passwordRecovery($data['passwordRecovery'] ?? [], $existingOther['passwordRecovery'] ?? []),
            ],
        ];

        $advanced = $data['advanced_json'] ?? [];

        if (!empty($data['apply_advanced_json'])) {
            foreach ($this->fields as $field) {
                if (!empty($advanced[$field])) {
                    $decoded = json_decode((string) $advanced[$field], true);

                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                        throw new RuntimeException($field . ' 高级 JSON 必须是有效对象', 400);
                    }

                    $payload[$field] = $this->mergeDefaults($field, $decoded);
                }
            }
        }

        $payload = $this->applyDefaultResets($payload, (array) ($data['reset_defaults'] ?? []));
        $setting->save($payload);

        return $setting;
    }

    private function setting(): SiteSetting
    {
        $setting = SiteSetting::find(1);

        if ($setting) {
            return $setting;
        }

        return SiteSetting::create(array_merge(['id' => 1], $this->defaults()));
    }

    private function applyDefaultResets(array $payload, array $resets): array
    {
        $defaults = $this->defaults();

        foreach (['base_info', 'header', 'footer', 'seo'] as $field) {
            if (!empty($resets[$field])) {
                $payload[$field] = $defaults[$field];
            }
        }

        if (!empty($resets['storage'])) {
            $payload['other']['storage'] = $defaults['other']['storage'];
            $payload['other']['netdisk'] = $defaults['other']['netdisk'];
        }

        if (!empty($resets['passwordRecovery'])) {
            $payload['other']['passwordRecovery'] = $defaults['other']['passwordRecovery'];
        }

        if (!empty($resets['homepage'])) {
            $payload['other']['homepage'] = $defaults['other']['homepage'];
        }

        if (!empty($resets['other'])) {
            foreach (['maintenanceEnabled', 'maintenanceNotice', 'defaultLanguage', 'timezone'] as $key) {
                $payload['other'][$key] = $defaults['other'][$key];
            }
        }

        return $payload;
    }

    private function defaults(): array
    {
        return (new SiteSettingService())->defaults();
    }

    private function mergeDefaults(string $field, array $value): array
    {
        return array_replace_recursive($this->defaults()[$field], $value);
    }

    private function string(mixed $value): string
    {
        return trim((string) $value);
    }

    private function storageDriver(mixed $value): string
    {
        $driver = $this->string($value);

        return in_array($driver, ['local', 'netdisk'], true) ? $driver : 'local';
    }

    private function keywords(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map([$this, 'string'], $value)));
        }

        return array_values(array_filter(array_map([$this, 'string'], preg_split('/[,，\r\n]+/', (string) $value))));
    }

    private function passwordRecovery(array $data, array $existing): array
    {
        $defaults = $this->defaults()['other']['passwordRecovery'];
        $existing = array_replace_recursive($defaults, $existing);
        $data = array_replace_recursive($defaults, $data);
        $smtpPassword = $this->string($data['email']['smtp']['password'] ?? '');
        $phoneApiKey = $this->string($data['phone']['apiKey'] ?? '');
        $phoneSecret = $this->string($data['phone']['secret'] ?? '');
        $phoneHeadersJson = $this->string($data['phone']['headersJson'] ?? '');

        return [
            'enabled' => !empty($data['enabled']),
            'expiresMinutes' => $this->intRange($data['expiresMinutes'] ?? 30, 5, 120),
            'codeLength' => $this->intRange($data['codeLength'] ?? 6, 4, 8),
            'maxAttempts' => $this->intRange($data['maxAttempts'] ?? 5, 3, 10),
            'resendCooldownSeconds' => $this->intRange($data['resendCooldownSeconds'] ?? 60, 30, 600),
            'email' => [
                'enabled' => !empty($data['email']['enabled']),
                'driver' => $this->enum($data['email']['driver'] ?? 'smtp', ['smtp', 'mail'], 'smtp'),
                'fromEmail' => $this->string($data['email']['fromEmail'] ?? ''),
                'fromName' => $this->string($data['email']['fromName'] ?? ''),
                'smtp' => [
                    'host' => $this->string($data['email']['smtp']['host'] ?? ''),
                    'port' => $this->intRange($data['email']['smtp']['port'] ?? 587, 1, 65535),
                    'username' => $this->string($data['email']['smtp']['username'] ?? ''),
                    'password' => $smtpPassword !== '' ? $smtpPassword : $this->string($existing['email']['smtp']['password'] ?? ''),
                    'encryption' => $this->enum($data['email']['smtp']['encryption'] ?? 'tls', ['none', 'ssl', 'tls'], 'tls'),
                    'timeoutSeconds' => $this->intRange($data['email']['smtp']['timeoutSeconds'] ?? 10, 3, 30),
                ],
            ],
            'phone' => [
                'enabled' => !empty($data['phone']['enabled']),
                'provider' => $this->enum($data['phone']['provider'] ?? 'none', ['none', 'generic_http'], 'none'),
                'endpoint' => $this->string($data['phone']['endpoint'] ?? ''),
                'method' => $this->enum(strtoupper($this->string($data['phone']['method'] ?? 'POST')), ['GET', 'POST'], 'POST'),
                'apiKey' => $phoneApiKey !== '' ? $phoneApiKey : $this->string($existing['phone']['apiKey'] ?? ''),
                'secret' => $phoneSecret !== '' ? $phoneSecret : $this->string($existing['phone']['secret'] ?? ''),
                'headersJson' => $phoneHeadersJson !== '' ? $phoneHeadersJson : $this->string($existing['phone']['headersJson'] ?? ''),
                'template' => $this->string($data['phone']['template'] ?? '您的验证码是 {code}，{minutes} 分钟内有效。'),
                'signName' => $this->string($data['phone']['signName'] ?? ''),
            ],
        ];
    }

    private function intRange(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    private function enum(mixed $value, array $allowed, string $default): string
    {
        $value = $this->string($value);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function homepage(array $data): array
    {
        $defaults = $this->defaults()['other']['homepage'];
        $data = array_replace_recursive($defaults, $data);
        $hero = $data['hero'] ?? [];

        return [
            'hero' => [
                'kicker' => $this->string($hero['kicker'] ?? ''),
                'title' => $this->string($hero['title'] ?? ''),
                'description' => $this->string($hero['description'] ?? ''),
                'primaryButtonText' => $this->string($hero['primaryButtonText'] ?? ''),
                'primaryButtonUrl' => $this->string($hero['primaryButtonUrl'] ?? ''),
                'secondaryButtonText' => $this->string($hero['secondaryButtonText'] ?? ''),
                'secondaryButtonUrl' => $this->string($hero['secondaryButtonUrl'] ?? ''),
                'userButtonText' => $this->string($hero['userButtonText'] ?? ''),
                'userButtonUrl' => $this->string($hero['userButtonUrl'] ?? ''),
                'guestButtonText' => $this->string($hero['guestButtonText'] ?? ''),
                'guestButtonUrl' => $this->string($hero['guestButtonUrl'] ?? ''),
            ],
            'featureCards' => $this->homepageFeatureCards($data['featureCards'] ?? []),
        ];
    }

    private function homepageFeatureCards(array $cards): array
    {
        $defaults = $this->defaults()['other']['homepage']['featureCards'];
        $cards = array_replace_recursive($defaults, $cards);
        $normalized = [];

        for ($i = 0; $i < 5; $i++) {
            $card = $cards[$i] ?? [];
            $normalized[] = [
                'badge' => $this->string($card['badge'] ?? ''),
                'title' => $this->string($card['title'] ?? ''),
                'description' => $this->string($card['description'] ?? ''),
            ];
        }

        return $normalized;
    }

    private function links(array $data): array
    {
        $labels = $data['label'] ?? [];
        $urls = $data['url'] ?? [];
        $links = [];

        foreach ((array) $labels as $index => $label) {
            $label = $this->string($label);
            $url = $this->string($urls[$index] ?? '');

            if ($label !== '' && $url !== '') {
                $links[] = ['label' => $label, 'url' => $url];
            }
        }

        return $links;
    }

    private function linkSlots(array $links, int $count): array
    {
        $slots = [];

        for ($i = 0; $i < $count; $i++) {
            $slots[] = [
                'label' => $this->string($links[$i]['label'] ?? ''),
                'url' => $this->string($links[$i]['url'] ?? ''),
            ];
        }

        return $slots;
    }
}
