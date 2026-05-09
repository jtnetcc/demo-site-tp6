<?php

namespace app\service;

use app\model\AccountVerification;
use app\model\User;
use RuntimeException;
use think\Request;
use Throwable;

class AccountVerificationService
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

    public function requestCode(string $purpose, string $channel, string $recipient, ?User $user, Request $request): void
    {
        $purpose = $this->purpose($purpose);
        $channel = $this->channel($channel);
        $recipient = trim($recipient);
        $config = $this->config();
        $this->assertChannelEnabled($config, $channel);
        $this->assertChannelConfigured($config, $channel);
        $this->assertCooldown($purpose, $channel, $recipient, $user, (int) ($config['resendCooldownSeconds'] ?? 60));
        $this->consumeActive($purpose, $channel, $recipient, $user);

        $code = $this->numericCode((int) ($config['codeLength'] ?? 6));
        $verification = AccountVerification::create([
            'user_id' => $user ? (int) $user->id : null,
            'purpose' => $purpose,
            'channel' => $channel,
            'recipient_hash' => $this->recipientHash($recipient),
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'request_ip' => method_exists($request, 'ip') ? (string) $request->ip() : '',
            'user_agent' => substr((string) $request->header('User-Agent'), 0, 500),
            'expires_at' => date('Y-m-d H:i:s', time() + (int) ($config['expiresMinutes'] ?? 30) * 60),
        ]);

        try {
            if ($channel === 'email') {
                $this->sendEmail($config, $recipient, $code, $purpose);
                return;
            }

            (new SmsSenderService())->send($config['phone'] ?? [], $recipient, $code, (int) ($config['expiresMinutes'] ?? 30));
        } catch (Throwable) {
            $verification->save(['consumed_at' => date('Y-m-d H:i:s')]);
            throw new RuntimeException($channel === 'email' ? '邮件验证码发送失败，请检查后台 SMTP 配置' : '短信验证码发送失败，请检查后台短信配置', 500);
        }
    }

    public function verifyCode(string $purpose, string $channel, string $recipient, string $code, ?User $user): void
    {
        $purpose = $this->purpose($purpose);
        $channel = $this->channel($channel);
        $verification = $this->activeQuery($purpose, $channel, $recipient, $user)
            ->order('created_at', 'desc')
            ->find();

        if (!$verification) {
            throw new RuntimeException('验证码无效或已过期', 400);
        }

        $this->assertCanTry($verification);

        if (!password_verify(trim($code), (string) $verification->code_hash)) {
            $verification->save(['attempt_count' => (int) $verification->attempt_count + 1]);
            throw new RuntimeException('验证码无效或已过期', 400);
        }

        $this->consumeActive($purpose, $channel, $recipient, $user);
    }

    private function sendEmail(array $config, string $email, string $code, string $purpose): void
    {
        $minutes = (int) ($config['expiresMinutes'] ?? 30);
        $subject = $purpose === 'register' ? '注册验证码' : '绑定邮箱验证码';
        $body = "您的验证码是：{$code}\n\n验证码将在 {$minutes} 分钟后失效。";

        (new EmailSenderService())->send($config['email'] ?? [], $email, $subject, $body);
    }

    private function assertCanTry(AccountVerification $verification): void
    {
        $config = $this->config();

        if (strtotime((string) $verification->expires_at) < time()) {
            $verification->save(['consumed_at' => date('Y-m-d H:i:s')]);
            throw new RuntimeException('验证码已过期', 400);
        }

        if ((int) $verification->attempt_count >= (int) ($config['maxAttempts'] ?? 5)) {
            throw new RuntimeException('错误次数过多，请重新获取验证码', 429);
        }
    }

    private function assertCooldown(string $purpose, string $channel, string $recipient, ?User $user, int $cooldown): void
    {
        $latest = $this->activeQuery($purpose, $channel, $recipient, $user)
            ->order('created_at', 'desc')
            ->find();

        if ($latest && time() - strtotime((string) $latest->created_at) < $cooldown) {
            throw new RuntimeException('请求过于频繁，请稍后再试', 429);
        }
    }

    private function consumeActive(string $purpose, string $channel, string $recipient, ?User $user): void
    {
        $this->activeQuery($purpose, $channel, $recipient, $user)
            ->update(['consumed_at' => date('Y-m-d H:i:s')]);
    }

    private function activeQuery(string $purpose, string $channel, string $recipient, ?User $user)
    {
        $query = AccountVerification::where('purpose', $purpose)
            ->where('channel', $channel)
            ->where('recipient_hash', $this->recipientHash($recipient))
            ->whereNull('consumed_at');

        return $user ? $query->where('user_id', (int) $user->id) : $query->whereNull('user_id');
    }

    private function assertChannelEnabled(array $config, string $channel): void
    {
        if (empty($config['enabled'])) {
            throw new RuntimeException('验证码发送功能暂未开启', 400);
        }

        if (empty($config[$channel]['enabled'])) {
            throw new RuntimeException($channel === 'email' ? '邮箱验证码暂未开启' : '短信验证码暂未开启', 400);
        }
    }

    private function assertChannelConfigured(array $config, string $channel): void
    {
        if ($channel === 'email') {
            $email = $config['email'] ?? [];
            $smtp = $email['smtp'] ?? [];

            if (($email['driver'] ?? 'smtp') === 'smtp' && (empty($smtp['host']) || empty($email['fromEmail']))) {
                throw new RuntimeException('邮箱验证码尚未配置 SMTP', 400);
            }

            if (($email['driver'] ?? 'smtp') === 'mail' && empty($email['fromEmail'])) {
                throw new RuntimeException('邮箱验证码尚未配置发件邮箱', 400);
            }

            return;
        }

        $phone = $config['phone'] ?? [];

        if (($phone['provider'] ?? 'none') !== 'generic_http' || empty($phone['endpoint'])) {
            throw new RuntimeException('短信验证码尚未配置短信接口', 400);
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

    private function purpose(string $purpose): string
    {
        if (!in_array($purpose, ['register', 'bind'], true)) {
            throw new RuntimeException('验证码用途不正确', 400);
        }

        return $purpose;
    }

    private function channel(string $channel): string
    {
        if (!in_array($channel, ['email', 'phone'], true)) {
            throw new RuntimeException('验证码渠道不正确', 400);
        }

        return $channel;
    }

    private function recipientHash(string $recipient): string
    {
        return hash('sha256', strtolower(trim($recipient)));
    }
}
