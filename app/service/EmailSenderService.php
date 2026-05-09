<?php

namespace app\service;

use RuntimeException;

class EmailSenderService
{
    public function send(array $config, string $to, string $subject, string $body): void
    {
        $driver = (string) ($config['driver'] ?? 'smtp');

        if ($driver === 'mail') {
            $this->sendByMail($config, $to, $subject, $body);
            return;
        }

        $this->sendBySmtp($config, $to, $subject, $body);
    }

    private function sendByMail(array $config, string $to, string $subject, string $body): void
    {
        if (!function_exists('mail')) {
            throw new RuntimeException('当前 PHP 环境不支持 mail 发送');
        }

        $fromEmail = trim((string) ($config['fromEmail'] ?? ''));
        $fromName = trim((string) ($config['fromName'] ?? ''));

        if ($fromEmail === '') {
            throw new RuntimeException('发件邮箱未配置');
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->mailbox($fromEmail, $fromName),
        ];

        if (!mail($to, $this->encodedHeader($subject), $body, implode("\r\n", $headers))) {
            throw new RuntimeException('邮件发送失败');
        }
    }

    private function sendBySmtp(array $config, string $to, string $subject, string $body): void
    {
        $smtp = $config['smtp'] ?? [];
        $host = trim((string) ($smtp['host'] ?? ''));
        $port = (int) ($smtp['port'] ?? 587);
        $encryption = (string) ($smtp['encryption'] ?? 'tls');
        $timeout = max(3, min(30, (int) ($smtp['timeoutSeconds'] ?? 10)));
        $fromEmail = trim((string) ($config['fromEmail'] ?? ''));
        $fromName = trim((string) ($config['fromName'] ?? ''));
        $username = trim((string) ($smtp['username'] ?? ''));
        $password = (string) ($smtp['password'] ?? '');

        if ($host === '' || $fromEmail === '') {
            throw new RuntimeException('SMTP 主机或发件邮箱未配置');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $errno = 0;
        $errstr = '';
        $socket = stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

        if (!$socket) {
            throw new RuntimeException('无法连接 SMTP 服务：' . $errstr);
        }

        stream_set_timeout($socket, $timeout);
        $this->expect($socket, [220]);
        $this->command($socket, 'EHLO localhost', [250]);

        if ($encryption === 'tls') {
            $this->command($socket, 'STARTTLS', [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new RuntimeException('SMTP TLS 握手失败');
            }

            $this->command($socket, 'EHLO localhost', [250]);
        }

        if ($username !== '' || $password !== '') {
            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($username), [334]);
            $this->command($socket, base64_encode($password), [235]);
        }

        $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        $this->command($socket, 'DATA', [354]);

        $message = implode("\r\n", [
            'From: ' . $this->mailbox($fromEmail, $fromName),
            'To: <' . $to . '>',
            'Subject: ' . $this->encodedHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            str_replace("\n.", "\n..", str_replace("\r\n", "\n", $body)),
            '.',
        ]);

        $this->command($socket, $message, [250]);
        $this->command($socket, 'QUIT', [221]);
        fclose($socket);
    }

    private function command($socket, string $command, array $codes): string
    {
        fwrite($socket, $command . "\r\n");

        return $this->expect($socket, $codes);
    }

    private function expect($socket, array $codes): string
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;

            if (preg_match('/^(\d{3})\s/', $line, $matches)) {
                $code = (int) $matches[1];

                if (!in_array($code, $codes, true)) {
                    throw new RuntimeException('SMTP 返回异常：' . trim($response));
                }

                return $response;
            }
        }

        throw new RuntimeException('SMTP 无响应');
    }

    private function mailbox(string $email, string $name): string
    {
        return $name !== '' ? $this->encodedHeader($name) . ' <' . $email . '>' : '<' . $email . '>';
    }

    private function encodedHeader(string $value): string
    {
        return function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($value, 'UTF-8') : $value;
    }
}
