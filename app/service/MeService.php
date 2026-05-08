<?php

namespace app\service;

use app\model\Favorite;
use app\model\Grant;
use app\model\User;
use app\model\WatchHistory;
use RuntimeException;

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
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $avatarUrl = trim((string) ($data['avatar_url'] ?? ''));

        if ($displayName === '') {
            throw new RuntimeException('显示名不能为空', 400);
        }

        if (mb_strlen($displayName) > 100) {
            throw new RuntimeException('显示名不能超过100个字符', 400);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('邮箱格式不正确', 400);
        }

        if ($email !== '' && User::where('email', $email)->where('id', '<>', (int) $user->id)->find()) {
            throw new RuntimeException('邮箱已存在', 409);
        }

        if ($phone !== '' && User::where('phone', $phone)->where('id', '<>', (int) $user->id)->find()) {
            throw new RuntimeException('手机号已存在', 409);
        }

        $user->save([
            'display_name' => $displayName,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'avatar_url' => $avatarUrl ?: null,
        ]);

        return $user;
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
