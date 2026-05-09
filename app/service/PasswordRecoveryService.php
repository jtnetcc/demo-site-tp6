<?php

namespace app\service;

use app\model\PasswordReset;
use app\model\User;
use RuntimeException;
use think\Request;
use Throwable;

class PasswordRecoveryService
{
    public function channelOptions(): array
    {
        $config = $this->config();

        $emailEnabled = (bool) ($config['email']['enabled'] ?? false);
        $phoneEnabled = (bool) ($config['phone']['enabled'] ?? false);

        return [
            'enabled' => (bool) ($config['enabled'] ?? false) && ($emailEnabled || $phoneEnabled),
            'email' => $emailEnabled,
            'phone' => $phoneEnabled,
        ];
    }

    public function requestReset(string $account, string $channel, Request $request): void
    {
        $account = trim($account);
        $config = $this->config();
        $this->assertChannelEnabled($config, $channel);
        $this->assertChannelConfigured($config, $channel);
        $user = $this->userForChannel($account, $channel);

        if (!$user) {
            return;
        }

        $this->assertCooldown($user, $channel, (int) ($config['resendCooldownSeconds'] ?? 60));
        $this->consumeActiveResets($user);
        $expiresAt = date('Y-m-d H:i:s', time() + (int) ($config['expiresMinutes'] ?? 30) * 60);
        $recipient = $channel === 'email' ? (string) $user->email : (string) $user->phone;

        if ($channel === 'email') {
            $this->createEmailReset($user, $recipient, $expiresAt, $config, $request);
            return;
        }

        $this->createPhoneReset($user, $recipient, $expiresAt, $config, $request);
    }

    public function resetByEmailToken(string $selector, string $token, string $password): void
    {
        $this->assertPassword($password);
        $reset = PasswordReset::where('selector', trim($selector))->where('channel', 'email')->whereNull('consumed_at')->find();

        if (!$reset) {
            throw new RuntimeException('重置链接无效或已过期', 400);
        }

        $this->assertResetCanTry($reset);

        if (!password_verify($token, (string) $reset->token_hash)) {
            $this->recordFailedAttempt($reset);
            throw new RuntimeException('重置链接无效或已过期', 400);
        }

        $this->resetPassword($reset, $password);
    }

    public function resetByPhoneCode(string $account, string $code, string $password): void
    {
        $this->assertPassword($password);
        $user = $this->userForChannel($account, 'phone');

        if (!$user) {
            throw new RuntimeException('验证码无效或已过期', 400);
        }

        $reset = PasswordReset::where('user_id', (int) $user->id)
            ->where('channel', 'phone')
            ->whereNull('consumed_at')
            ->order('created_at', 'desc')
            ->find();

        if (!$reset) {
            throw new RuntimeException('验证码无效或已过期', 400);
        }

        $this->assertResetCanTry($reset);

        if (!password_verify(trim($code), (string) $reset->code_hash)) {
            $this->recordFailedAttempt($reset);
            throw new RuntimeException('验证码无效或已过期', 400);
        }

        $this->resetPassword($reset, $password);
    }

    private function createEmailReset(User $user, string $email, string $expiresAt, array $config, Request $request): void
    {
        $selector = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $reset = PasswordReset::create($this->payload($user, 'email', $email, $expiresAt, $request) + [
            'selector' => $selector,
            'token_hash' => password_hash($token, PASSWORD_DEFAULT),
        ]);
        $url = $this->baseUrl($request, $config) . '/reset-password?selector=' . urlencode($selector) . '&token=' . urlencode($token);
        $subject = '重置登录密码';
        $body = "请打开下面的链接重置密码：\n\n" . $url . "\n\n链接将在 " . (int) ($config['expiresMinutes'] ?? 30) . " 分钟后失效。";

        try {
            (new EmailSenderService())->send($config['email'] ?? [], $email, $subject, $body);
        } catch (Throwable $e) {
            $reset->save(['consumed_at' => date('Y-m-d H:i:s')]);
            throw new RuntimeException('邮件发送失败，请检查后台 SMTP 配置', 500);
        }
    }

    private function createPhoneReset(User $user, string $phone, string $expiresAt, array $config, Request $request): void
    {
        $code = $this->numericCode((int) ($config['codeLength'] ?? 6));
        $reset = PasswordReset::create($this->payload($user, 'phone', $phone, $expiresAt, $request) + [
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        ]);

        try {
            (new SmsSenderService())->send($config['phone'] ?? [], $phone, $code, (int) ($config['expiresMinutes'] ?? 30));
        } catch (Throwable $e) {
            $reset->save(['consumed_at' => date('Y-m-d H:i:s')]);
            throw new RuntimeException('短信发送失败，请检查后台短信配置', 500);
        }
    }

    private function payload(User $user, string $channel, string $recipient, string $expiresAt, Request $request): array
    {
        return [
            'user_id' => (int) $user->id,
            'channel' => $channel,
            'recipient_hash' => hash('sha256', strtolower(trim($recipient))),
            'request_ip' => method_exists($request, 'ip') ? (string) $request->ip() : '',
            'user_agent' => substr((string) $request->header('User-Agent'), 0, 500),
            'expires_at' => $expiresAt,
        ];
    }

    private function resetPassword(PasswordReset $reset, string $password): void
    {
        $user = User::find((int) $reset->user_id);

        if (!$user) {
            throw new RuntimeException('账号不存在', 400);
        }

        $user->save(['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        $this->consumeActiveResets($user);
    }

    private function consumeActiveResets(User $user): void
    {
        PasswordReset::where('user_id', (int) $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => date('Y-m-d H:i:s')]);
    }

    private function assertCooldown(User $user, string $channel, int $cooldown): void
    {
        $latest = PasswordReset::where('user_id', (int) $user->id)
            ->where('channel', $channel)
            ->whereNull('consumed_at')
            ->order('created_at', 'desc')
            ->find();

        if ($latest && time() - strtotime((string) $latest->created_at) < $cooldown) {
            throw new RuntimeException('请求过于频繁，请稍后再试', 429);
        }
    }

    private function assertResetCanTry(PasswordReset $reset): void
    {
        $config = $this->config();

        if (strtotime((string) $reset->expires_at) < time()) {
            $reset->save(['consumed_at' => date('Y-m-d H:i:s')]);
            throw new RuntimeException('重置凭证已过期', 400);
        }

        if ((int) $reset->attempt_count >= (int) ($config['maxAttempts'] ?? 5)) {
            throw new RuntimeException('错误次数过多，请重新获取验证码或链接', 429);
        }
    }

    private function recordFailedAttempt(PasswordReset $reset): void
    {
        $reset->save(['attempt_count' => (int) $reset->attempt_count + 1]);
    }

    private function assertChannelEnabled(array $config, string $channel): void
    {
        if (empty($config['enabled'])) {
            throw new RuntimeException('找回密码功能暂未开启', 400);
        }

        if (!in_array($channel, ['email', 'phone'], true) || empty($config[$channel]['enabled'])) {
            throw new RuntimeException('该找回方式暂未开启', 400);
        }
    }

    private function assertChannelConfigured(array $config, string $channel): void
    {
        if ($channel === 'email') {
            $email = $config['email'] ?? [];
            $smtp = $email['smtp'] ?? [];

            if (($email['driver'] ?? 'smtp') === 'smtp' && (empty($smtp['host']) || empty($email['fromEmail']))) {
                throw new RuntimeException('邮箱找回尚未配置 SMTP', 400);
            }

            if (($email['driver'] ?? 'smtp') === 'mail' && empty($email['fromEmail'])) {
                throw new RuntimeException('邮箱找回尚未配置发件邮箱', 400);
            }

            return;
        }

        $phone = $config['phone'] ?? [];

        if (($phone['provider'] ?? 'none') !== 'generic_http' || empty($phone['endpoint'])) {
            throw new RuntimeException('手机找回尚未配置短信接口', 400);
        }
    }

    private function userForChannel(string $account, string $channel): ?User
    {
        $account = trim($account);

        if ($account === '') {
            return null;
        }

        return $channel === 'email'
            ? User::where('email', $account)->find()
            : User::where('phone', $account)->find();
    }

    private function assertPassword(string $password): void
    {
        if (mb_strlen($password) < 6) {
            throw new RuntimeException('密码不能少于6位', 400);
        }
    }

    private function numericCode(int $length): string
    {
        $length = max(4, min(8, $length));
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }

    private function config(): array
    {
        $settings = (new SiteSettingService())->settingsWithSecrets();

        return $settings['other']['passwordRecovery'] ?? [];
    }

    private function baseUrl(Request $request, array $config): string
    {
        $settings = (new SiteSettingService())->settingsWithSecrets();
        $base = trim((string) ($settings['base_info']['siteUrl'] ?? ''));
        $parts = $base !== '' ? parse_url($base) : false;
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';

        if ($base === '' || !is_array($parts) || !in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            throw new RuntimeException('请先在后台基础信息配置有效的站点地址', 500);
        }

        return rtrim($base, '/');
    }
}
