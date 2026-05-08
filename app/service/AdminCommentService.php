<?php

namespace app\service;

use app\model\Comment;
use app\model\User;
use app\model\Video;
use RuntimeException;

class AdminCommentService
{
    public function list(array $filters)
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = Comment::with(['user', 'video'])->order('created_at', 'desc');

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $userIds = User::whereLike('username', '%' . $q . '%')->column('id');
            $videoIds = Video::whereLike('title', '%' . $q . '%')->column('id');
            $query->where(function ($subQuery) use ($q, $userIds, $videoIds) {
                $subQuery->whereLike('content', '%' . $q . '%');

                if ($userIds) {
                    $subQuery->whereOr('user_id', 'in', $userIds);
                }

                if ($videoIds) {
                    $subQuery->whereOr('video_id', 'in', $videoIds);
                }
            });
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['VISIBLE', 'HIDDEN'], true)) {
            $query->where('status', (string) $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['video_id'])) {
            $query->where('video_id', (int) $filters['video_id']);
        }

        return $query->paginate(['list_rows' => 20, 'page' => $page, 'query' => $filters]);
    }

    public function hide(int $id): Comment
    {
        return $this->setStatus($id, 'HIDDEN');
    }

    public function show(int $id): Comment
    {
        return $this->setStatus($id, 'VISIBLE');
    }

    public function delete(int $id): bool
    {
        $comment = Comment::find($id);

        if (!$comment) {
            throw new RuntimeException('评论不存在', 404);
        }

        return (bool) $comment->delete();
    }

    private function setStatus(int $id, string $status): Comment
    {
        $comment = Comment::find($id);

        if (!$comment) {
            throw new RuntimeException('评论不存在', 404);
        }

        $comment->save(['status' => $status]);

        return $comment;
    }
}
