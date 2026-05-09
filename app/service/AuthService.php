<?php

namespace app\service;

use app\model\User;
use RuntimeException;
use think\Request;

class AuthService
{
    public function login(string $account, string $password): array
    {
        $user = User::where('username', $account)
            ->whereOr('email', $account)
            ->whereOr('phone', $account)
            ->find();

        if (!$user || !password_verify($password, (string) $user->password_hash)) {
            throw new RuntimeException('账号或密码错误', 400);
        }

        $this->assertUserCanLogin($user);

        return $this->tokenPayload($user);
    }

    public function register(array $data): array
    {
        $data = $this->normalizeRegisterData($data);
        $type = $data['register_type'];
        $account = $data['account'];
        $displayName = $data['display_name'] !== '' ? $data['display_name'] : $account;
        $payload = [
            'display_name' => mb_substr($displayName, 0, 100),
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => 'USER',
            'level' => 'NORMAL',
            'status' => 'ACTIVE',
        ];

        if ($type === 'username') {
            $this->assertUsername($account);
            $this->assertUnique('username', $account, '用户名已存在');
            $payload += [
                'username' => $account,
                'email' => null,
                'phone' => null,
                'email_verified_at' => null,
                'phone_verified_at' => null,
            ];
        } elseif ($type === 'email') {
            $this->assertEmail($account);
            $this->assertUnique('email', $account, '邮箱已存在');
            $this->assertCode($data['code']);
            (new AccountVerificationService())->verifyCode('register', 'email', $account, $data['code'], null);
            $payload += [
                'username' => $this->generateUsername($account, 'email'),
                'email' => $account,
                'phone' => null,
                'email_verified_at' => date('Y-m-d H:i:s'),
                'phone_verified_at' => null,
            ];
        } else {
            $this->assertPhone($account);
            $this->assertUnique('phone', $account, '手机号已存在');
            $this->assertCode($data['code']);
            (new AccountVerificationService())->verifyCode('register', 'phone', $account, $data['code'], null);
            $payload += [
                'username' => $this->generateUsername($account, 'phone'),
                'email' => null,
                'phone' => $account,
                'email_verified_at' => null,
                'phone_verified_at' => date('Y-m-d H:i:s'),
            ];
        }

        return $this->tokenPayload(User::create($payload));
    }

    public function logout(): array
    {
        return ['logged_out' => true];
    }

    public function requestRegisterCode(string $channel, string $account, Request $request): void
    {
        $channel = trim($channel);
        $account = trim($account);

        if (!in_array($channel, ['email', 'phone'], true)) {
            throw new RuntimeException('验证码渠道不正确', 400);
        }

        $this->assertRegisterAccount($channel, $account);
        $this->assertUnique($channel, $account, $channel === 'email' ? '邮箱已存在' : '手机号已存在');
        (new AccountVerificationService())->requestCode('register', $channel, $account, null, $request);
    }

    public function userFromRequest(Request $request): User
    {
        $token = $this->parseBearerToken($request);

        if (!$token) {
            throw new RuntimeException('请先登录', 401);
        }

        $payload = $this->verifyToken($token);
        $user = User::find((int) ($payload['sub'] ?? 0));

        if (!$user) {
            throw new RuntimeException('登录用户不存在', 401);
        }

        $this->assertUserCanLogin($user);

        return $user;
    }

    public function currentUser(int $userId): ?User
    {
        return User::find($userId);
    }

    private function parseBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization') ?: $request->header('authorization');

        if (!$header || stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }

    private function makeToken(User $user): string
    {
        $config = $this->jwtConfig();
        $now = time();
        $ttl = (int) $config['ttl'];
        $payload = [
            'iss' => $config['issuer'],
            'iat' => $now,
            'exp' => $now + $ttl,
            'sub' => (int) $user->id,
            'role' => (string) $user->role,
        ];

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $config['secret'], true);

        return $encodedHeader . '.' . $encodedPayload . '.' . $this->base64UrlEncode($signature);
    }

    private function verifyToken(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Token 格式不正确', 401);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $config = $this->jwtConfig();
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $config['secret'], true));

        if (!hash_equals($expected, $encodedSignature)) {
            throw new RuntimeException('Token 签名无效', 401);
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (!is_array($payload)) {
            throw new RuntimeException('Token 内容无效', 401);
        }

        if (($payload['iss'] ?? '') !== $config['issuer']) {
            throw new RuntimeException('Token 签发方无效', 401);
        }

        if ((int) ($payload['exp'] ?? 0) < time()) {
            throw new RuntimeException('Token 已过期', 401);
        }

        return $payload;
    }

    public function assertUserCanLogin(User $user): void
    {
        if ($user->status === 'DISABLED') {
            throw new RuntimeException('账号已被禁用', 403);
        }

        if ($user->status === 'PENDING') {
            throw new RuntimeException('账号待激活', 403);
        }

        if ($user->status !== 'ACTIVE') {
            throw new RuntimeException('账号状态异常', 403);
        }

        if ($this->isPast($user->valid_until)) {
            throw new RuntimeException('账号有效期已过', 403);
        }
    }

    public function sanitizeUser(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'display_name' => $user->display_name,
            'avatar_url' => $user->avatar_url,
            'role' => $user->role,
            'level' => $user->level,
            'status' => $user->status,
            'valid_until' => $this->formatDate($user->valid_until),
            'email_verified_at' => $this->formatDate($user->email_verified_at),
            'phone_verified_at' => $this->formatDate($user->phone_verified_at),
            'contact_bound' => $this->contactBound($user),
            'created_at' => $this->formatDate($user->created_at),
        ];
    }

    public function contactBound(User $user): bool
    {
        return ((string) ($user->email ?? '') !== '' && (bool) $user->email_verified_at)
            || ((string) ($user->phone ?? '') !== '' && (bool) $user->phone_verified_at);
    }

    public function isPast($value): bool
    {
        if (!$value) {
            return false;
        }

        $timestamp = $this->timestamp($value);

        return $timestamp !== null && $timestamp < time();
    }

    public function normalizeRegisterData(array $data): array
    {
        $type = trim((string) ($data['register_type'] ?? ''));

        if ($type === '') {
            if (!empty($data['email']) && empty($data['username'])) {
                $type = 'email';
                $data['account'] = $data['email'];
            } elseif (!empty($data['phone']) && empty($data['username'])) {
                $type = 'phone';
                $data['account'] = $data['phone'];
            } else {
                $type = 'username';
                $data['account'] = $data['username'] ?? '';
            }
        }

        if (!in_array($type, ['username', 'email', 'phone'], true)) {
            throw new RuntimeException('注册方式不正确', 400);
        }

        return [
            'register_type' => $type,
            'account' => trim((string) ($data['account'] ?? '')),
            'display_name' => trim((string) ($data['display_name'] ?? '')),
            'password' => (string) ($data['password'] ?? ''),
            'code' => trim((string) ($data['code'] ?? '')),
        ];
    }

    public function assertRegisterAccount(string $type, string $account): void
    {
        if ($type === 'username') {
            $this->assertUsername($account);
        } elseif ($type === 'email') {
            $this->assertEmail($account);
        } elseif ($type === 'phone') {
            $this->assertPhone($account);
        } else {
            throw new RuntimeException('注册方式不正确', 400);
        }
    }

    public function assertUnique(string $field, string $value, string $message, ?int $exceptUserId = null): void
    {
        $query = User::where($field, $value);

        if ($exceptUserId) {
            $query->where('id', '<>', $exceptUserId);
        }

        if ($query->find()) {
            throw new RuntimeException($message, 409);
        }
    }

    public function assertUsername(string $username): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{3,64}$/', $username)) {
            throw new RuntimeException('用户名只能包含字母、数字、下划线或短横线，长度为3到64位', 400);
        }
    }

    public function assertEmail(string $email): void
    {
        if ($email === '' || mb_strlen($email) > 191 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('邮箱格式不正确', 400);
        }
    }

    public function assertPhone(string $phone): void
    {
        if (!preg_match('/^\+?[0-9]{6,20}$/', $phone) || mb_strlen($phone) > 32) {
            throw new RuntimeException('手机号格式不正确', 400);
        }
    }

    private function assertCode(string $code): void
    {
        if ($code === '') {
            throw new RuntimeException('请输入验证码', 400);
        }
    }

    private function generateUsername(string $account, string $type): string
    {
        $base = $type === 'email' ? explode('@', $account)[0] : 'u' . substr(preg_replace('/\D+/', '', $account), -4);
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $base) ?: 'u';
        $base = trim($base, '_-') ?: 'u';
        $base = substr($base, 0, 40);

        for ($i = 0; $i < 8; $i++) {
            $username = $base . '_' . bin2hex(random_bytes(4));

            if (!User::where('username', $username)->find()) {
                return $username;
            }
        }

        throw new RuntimeException('无法生成唯一用户名，请稍后重试', 500);
    }

    private function tokenPayload(User $user): array
    {
        $config = $this->jwtConfig();

        return [
            'token' => $this->makeToken($user),
            'token_type' => 'Bearer',
            'expires_in' => (int) $config['ttl'],
            'user' => $this->sanitizeUser($user),
        ];
    }

    private function jwtConfig(): array
    {
        $jwt = config('jwt') ?: [];
        $secret = (string) ($jwt['secret'] ?? '');

        if ($secret === '' || $secret === 'change-this-secret-in-env') {
            throw new RuntimeException('JWT_SECRET 未配置，请先设置安全密钥', 500);
        }

        return [
            'secret' => $secret,
            'issuer' => (string) ($jwt['issuer'] ?? 'demo-site-tp6'),
            'ttl' => (int) ($jwt['ttl'] ?? 86400),
        ];
    }

    private function timestamp($value): ?int
    {
        if (!$value) {
            return null;
        }

        if (is_object($value) && method_exists($value, 'getTimestamp')) {
            return $value->getTimestamp();
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : $timestamp;
    }

    private function formatDate($value): ?string
    {
        $timestamp = $this->timestamp($value);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}
