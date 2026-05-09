<?php

namespace app\service;

use RuntimeException;

class SmsSenderService
{
    public function send(array $config, string $phone, string $code, int $minutes): void
    {
        $provider = (string) ($config['provider'] ?? 'none');

        if ($provider === 'none') {
            throw new RuntimeException('短信服务商未配置');
        }

        if ($provider !== 'generic_http') {
            throw new RuntimeException('不支持的短信服务商');
        }

        $this->sendGenericHttp($config, $phone, $code, $minutes);
    }

    private function sendGenericHttp(array $config, string $phone, string $code, int $minutes): void
    {
        $endpoint = trim((string) ($config['endpoint'] ?? ''));

        if ($endpoint === '') {
            throw new RuntimeException('短信接口地址未配置');
        }

        $payload = [
            'phone' => $phone,
            'code' => $code,
            'minutes' => $minutes,
            'message' => $this->message($config, $phone, $code, $minutes),
            'signName' => (string) ($config['signName'] ?? ''),
            'apiKey' => (string) ($config['apiKey'] ?? ''),
            'secret' => (string) ($config['secret'] ?? ''),
        ];
        $method = strtoupper((string) ($config['method'] ?? 'POST')) === 'GET' ? 'GET' : 'POST';
        $headers = $this->headers($config);

        if (function_exists('curl_init')) {
            $this->sendWithCurl($endpoint, $method, $headers, $payload);
            return;
        }

        $this->sendWithStream($endpoint, $method, $headers, $payload);
    }

    private function sendWithCurl(string $endpoint, string $method, array $headers, array $payload): void
    {
        $url = $method === 'GET' ? $this->urlWithQuery($endpoint, $payload) : $endpoint;
        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException('无法初始化短信请求');
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge(['Content-Type: application/json'], $headers));

        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($error !== '' || $status >= 400 || $status === 0) {
            throw new RuntimeException('短信接口请求失败');
        }
    }

    private function sendWithStream(string $endpoint, string $method, array $headers, array $payload): void
    {
        $url = $method === 'GET' ? $this->urlWithQuery($endpoint, $payload) : $endpoint;
        $headerLines = array_merge(['Content-Type: application/json'], $headers);
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ];

        if ($method === 'POST') {
            $options['http']['content'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        $result = @file_get_contents($url, false, stream_context_create($options));

        if ($result === false) {
            throw new RuntimeException('短信接口请求失败');
        }
    }

    private function headers(array $config): array
    {
        $json = trim((string) ($config['headersJson'] ?? ''));

        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return [];
        }

        $headers = [];

        foreach ($decoded as $name => $value) {
            $name = trim((string) $name);
            $value = trim((string) $value);

            if ($name !== '' && $value !== '') {
                $headers[] = $name . ': ' . $value;
            }
        }

        return $headers;
    }

    private function message(array $config, string $phone, string $code, int $minutes): string
    {
        $template = (string) ($config['template'] ?? '您的验证码是 {code}，{minutes} 分钟内有效。');

        return str_replace(['{phone}', '{code}', '{minutes}'], [$phone, $code, (string) $minutes], $template);
    }

    private function urlWithQuery(string $url, array $payload): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($payload);
    }
}
