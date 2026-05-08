<?php

namespace app\service;

use app\model\Course;
use app\model\Grant;
use app\model\User;
use RuntimeException;

class GrantService
{
    public function list(array $filters = [])
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 20)));
        $query = Grant::with(['user', 'course', 'grantedByAdmin'])->order('created_at', 'desc');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['course_id'])) {
            $query->where('course_id', (int) $filters['course_id']);
        }

        return $query->paginate(['list_rows' => $limit, 'page' => $page, 'query' => $filters]);
    }

    public function find(int $id): ?Grant
    {
        return Grant::with(['user', 'course', 'grantedByAdmin'])->find($id);
    }

    public function create(array $data, User $admin): Grant
    {
        if ($admin->role !== 'ADMIN') {
            throw new RuntimeException('需要管理员权限', 403);
        }

        $userId = (int) ($data['user_id'] ?? 0);
        $courseId = (int) ($data['course_id'] ?? 0);
        $expiresAt = $data['expires_at'] ?? null;

        if ($userId <= 0 || $courseId <= 0) {
            throw new RuntimeException('用户和课程不能为空', 400);
        }

        if (!User::find($userId)) {
            throw new RuntimeException('用户不存在', 404);
        }

        if (!Course::find($courseId)) {
            throw new RuntimeException('课程不存在', 404);
        }

        if ($expiresAt && strtotime((string) $expiresAt) === false) {
            throw new RuntimeException('授权过期时间格式不正确', 400);
        }

        $grant = Grant::where('user_id', $userId)->where('course_id', $courseId)->find();
        $payload = [
            'user_id' => $userId,
            'course_id' => $courseId,
            'granted_by_admin_id' => (int) $admin->id,
            'expires_at' => $expiresAt ?: null,
        ];

        if ($grant) {
            $grant->save($payload);
            return $grant;
        }

        return Grant::create($payload);
    }

    public function update(int $id, array $data, User $admin): Grant
    {
        if ($admin->role !== 'ADMIN') {
            throw new RuntimeException('需要管理员权限', 403);
        }

        $grant = Grant::find($id);

        if (!$grant) {
            throw new RuntimeException('授权不存在', 404);
        }

        $expiresAt = $data['expires_at'] ?? null;

        if ($expiresAt && strtotime((string) $expiresAt) === false) {
            throw new RuntimeException('授权过期时间格式不正确', 400);
        }

        $grant->save([
            'expires_at' => $expiresAt ?: null,
            'granted_by_admin_id' => (int) $admin->id,
        ]);

        return $grant;
    }

    public function delete(int $id): bool
    {
        $grant = Grant::find($id);

        if (!$grant) {
            throw new RuntimeException('授权不存在', 404);
        }

        return (bool) $grant->delete();
    }

    public function isGrantValid(int $userId, int $courseId): bool
    {
        $now = date('Y-m-d H:i:s');

        return Grant::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')->whereOr('expires_at', '>', $now);
            })
            ->find() !== null;
    }
}
