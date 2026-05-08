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

        return ['setting' => $setting, 'form' => $form, 'json' => $json];
    }

    public function update(array $data): SiteSetting
    {
        $setting = $this->setting();
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
