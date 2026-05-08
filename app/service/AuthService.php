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
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;

        if (!$email && !$phone) {
            throw new RuntimeException('邮箱或手机号至少填写一个', 400);
        }

        if (User::where('username', $data['username'])->find()) {
            throw new RuntimeException('用户名已存在', 409);
        }

        if ($email && User::where('email', $email)->find()) {
            throw new RuntimeException('邮箱已存在', 409);
        }

        if ($phone && User::where('phone', $phone)->find()) {
            throw new RuntimeException('手机号已存在', 409);
        }

        $displayName = $data['display_name'] ?? $data['username'];

        $user = User::create([
            'username' => $data['username'],
            'email' => $email,
            'phone' => $phone,
            'display_name' => $displayName ?: $data['username'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => 'USER',
            'level' => 'NORMAL',
            'status' => 'ACTIVE',
            'email_verified_at' => $email ? date('Y-m-d H:i:s') : null,
            'activation_token' => bin2hex(random_bytes(32)),
        ]);

        return $this->tokenPayload($user);
    }

    public function logout(): array
    {
        return ['logged_out' => true];
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

    public function parseBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization') ?: $request->header('authorization');

        if (!$header || stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }

    public function makeToken(User $user): string
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

    public function verifyToken(string $token): array
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
            'created_at' => $this->formatDate($user->created_at),
        ];
    }

    public function isPast($value): bool
    {
        if (!$value) {
            return false;
        }

        $timestamp = $this->timestamp($value);

        return $timestamp !== null && $timestamp < time();
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
