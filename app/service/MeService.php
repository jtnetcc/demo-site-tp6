<?php

namespace app\service;

use app\model\Favorite;
use app\model\Grant;
use app\model\User;
use app\model\WatchHistory;
use RuntimeException;
use think\Request;

class MeService
{
    public function dashboard(User $user): array
    {
        return [
            'user' => (new AuthService())->sanitizeUser($user),
            'history' => WatchHistory::with(['video'])->where('user_id', (int) $user->id)->order('watched_at', 'desc')->limit(6)->select(),
            'favorites' => Favorite::with(['video'])->where('user_id', (int) $user->id)->order('created_at', 'desc')->limit(6)->select(),
            'grants' => $this->activeGrants($user),
        ];
    }

    public function profile(User $user): array
    {
        return ['user' => (new AuthService())->sanitizeUser($user)];
    }

    public function updateProfile(User $user, array $data): User
    {
        $displayName = trim((string) ($data['display_name'] ?? ''));
        $avatarUrl = trim((string) ($data['avatar_url'] ?? ''));

        if ($displayName === '') {
            throw new RuntimeException('显示名不能为空', 400);
        }

        if (mb_strlen($displayName) > 100) {
            throw new RuntimeException('显示名不能超过100个字符', 400);
        }

        $user->save([
            'display_name' => $displayName,
            'avatar_url' => $avatarUrl ?: null,
        ]);

        return $user;
    }

    public function requestBindCode(User $user, string $channel, string $account, Request $request): void
    {
        $channel = $this->contactChannel($channel);
        $account = trim($account);
        $auth = new AuthService();
        $auth->assertRegisterAccount($channel, $account);
        $auth->assertUnique($channel, $account, $channel === 'email' ? '邮箱已存在' : '手机号已存在', (int) $user->id);
        (new AccountVerificationService())->requestCode('bind', $channel, $account, $user, $request);
    }

    public function bindContact(User $user, array $data): User
    {
        $channel = $this->contactChannel((string) ($data['channel'] ?? ''));
        $account = trim((string) ($data['account'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        $auth = new AuthService();

        $auth->assertRegisterAccount($channel, $account);
        $auth->assertUnique($channel, $account, $channel === 'email' ? '邮箱已存在' : '手机号已存在', (int) $user->id);

        if ($code === '') {
            throw new RuntimeException('请输入验证码', 400);
        }

        (new AccountVerificationService())->verifyCode('bind', $channel, $account, $code, $user);

        if ($channel === 'email') {
            $user->save([
                'email' => $account,
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $user->save([
                'phone' => $account,
                'phone_verified_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return User::find((int) $user->id) ?: $user;
    }

    public function history(User $user, array $filters)
    {
        return WatchHistory::with(['video', 'video.category'])
            ->where('user_id', (int) $user->id)
            ->order('watched_at', 'desc')
            ->paginate(['list_rows' => 12, 'page' => max(1, (int) ($filters['page'] ?? 1))]);
    }

    public function favorites(User $user, array $filters)
    {
        return Favorite::with(['video', 'video.category'])
            ->where('user_id', (int) $user->id)
            ->order('created_at', 'desc')
            ->paginate(['list_rows' => 12, 'page' => max(1, (int) ($filters['page'] ?? 1))]);
    }

    public function courses(User $user): array
    {
        return (new CourseService())->myCourses($user);
    }

    private function contactChannel(string $channel): string
    {
        if (!in_array($channel, ['email', 'phone'], true)) {
            throw new RuntimeException('验证码渠道不正确', 400);
        }

        return $channel;
    }

    private function activeGrants(User $user)
    {
        $now = date('Y-m-d H:i:s');

        return Grant::with(['course'])
            ->where('user_id', (int) $user->id)
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')->whereOr('expires_at', '>', $now);
            })
            ->order('created_at', 'desc')
            ->limit(6)
            ->select();
    }
}
