<?php

namespace app\service;

use app\model\User;
use RuntimeException;

class AdminUserService
{
    public function list(array $filters)
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = User::order('created_at', 'desc');

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($subQuery) use ($q) {
                $subQuery->whereLike('username', '%' . $q . '%')
                    ->whereOr('display_name', 'like', '%' . $q . '%')
                    ->whereOr('email', 'like', '%' . $q . '%')
                    ->whereOr('phone', 'like', '%' . $q . '%');
            });
        }

        foreach (['role', 'level', 'status'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, (string) $filters[$field]);
            }
        }

        return $query->paginate(['list_rows' => 20, 'page' => $page, 'query' => $filters]);
    }

    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function create(array $data): User
    {
        $payload = $this->payload($data, true);

        return User::create($payload);
    }

    public function update(int $id, array $data, ?User $admin = null): User
    {
        $user = User::find($id);

        if (!$user) {
            throw new RuntimeException('用户不存在', 404);
        }

        $payload = $this->payload($data, false);

        if ($admin && (int) $admin->id === (int) $user->id) {
            if (($payload['role'] ?? $user->role) !== 'ADMIN') {
                throw new RuntimeException('不能取消当前管理员自己的管理员权限', 400);
            }

            if (($payload['status'] ?? $user->status) !== 'ACTIVE') {
                throw new RuntimeException('不能禁用当前管理员自己的账号', 400);
            }
        }

        $user->save($payload);

        return $user;
    }

    public function delete(int $id, ?User $admin = null): bool
    {
        $user = User::find($id);

        if (!$user) {
            throw new RuntimeException('用户不存在', 404);
        }

        if ($admin && (int) $admin->id === (int) $user->id) {
            throw new RuntimeException('不能删除当前登录管理员', 400);
        }

        return (bool) $user->delete();
    }

    private function payload(array $data, bool $creating): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $displayName = trim((string) ($data['display_name'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '') {
            throw new RuntimeException('用户名不能为空', 400);
        }

        if ($displayName === '') {
            $displayName = $username;
        }

        if ($creating && $password === '') {
            throw new RuntimeException('密码不能为空', 400);
        }

        if ($password !== '' && mb_strlen($password) < 6) {
            throw new RuntimeException('密码不能少于6位', 400);
        }

        $payload = [
            'username' => $username,
            'display_name' => $displayName,
            'email' => $this->nullable($data['email'] ?? null),
            'phone' => $this->nullable($data['phone'] ?? null),
            'avatar_url' => $this->nullable($data['avatar_url'] ?? null),
            'role' => in_array(($data['role'] ?? 'USER'), ['ADMIN', 'USER'], true) ? $data['role'] : 'USER',
            'level' => in_array(($data['level'] ?? 'NORMAL'), ['NORMAL', 'VIP', 'SVIP'], true) ? $data['level'] : 'NORMAL',
            'status' => in_array(($data['status'] ?? 'ACTIVE'), ['ACTIVE', 'DISABLED', 'PENDING'], true) ? $data['status'] : 'ACTIVE',
            'valid_until' => $this->nullableDate($data['valid_until'] ?? null),
        ];

        if ($password !== '') {
            $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        return $payload;
    }

    private function nullable($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableDate($value): ?string
    {
        $value = $this->nullable($value);

        if ($value !== null && strtotime($value) === false) {
            throw new RuntimeException('有效期格式不正确', 400);
        }

        return $value;
    }
}
