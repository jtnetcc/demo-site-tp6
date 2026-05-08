<?php

namespace app\service;

use app\model\User;
use app\model\Video;
use app\model\WatchHistory;
use RuntimeException;

class AdminWatchHistoryService
{
    public function list(array $filters)
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = WatchHistory::with(['user', 'video'])->order('watched_at', 'desc');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['video_id'])) {
            $query->where('video_id', (int) $filters['video_id']);
        }

        return $query->paginate(['list_rows' => 20, 'page' => $page, 'query' => $filters]);
    }

    public function find(int $id): ?WatchHistory
    {
        return WatchHistory::with(['user', 'video'])->find($id);
    }

    public function create(array $data): WatchHistory
    {
        $payload = $this->payload($data);
        $existing = WatchHistory::where('user_id', $payload['user_id'])->where('video_id', $payload['video_id'])->find();

        if ($existing) {
            throw new RuntimeException('该用户的视频观看记录已存在，请编辑既有记录', 400);
        }

        return WatchHistory::create($payload);
    }

    public function update(int $id, array $data): WatchHistory
    {
        $history = WatchHistory::find($id);

        if (!$history) {
            throw new RuntimeException('观看记录不存在', 404);
        }

        $payload = $this->payload($data);
        $existing = WatchHistory::where('user_id', $payload['user_id'])->where('video_id', $payload['video_id'])->find();

        if ($existing && (int) $existing->id !== (int) $history->id) {
            throw new RuntimeException('该用户的视频观看记录已存在，请编辑既有记录', 400);
        }

        $history->save($payload);

        return $history;
    }

    public function delete(int $id): bool
    {
        $history = WatchHistory::find($id);

        if (!$history) {
            throw new RuntimeException('观看记录不存在', 404);
        }

        return (bool) $history->delete();
    }

    private function payload(array $data): array
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $videoId = (int) ($data['video_id'] ?? 0);
        $watchedAt = trim((string) ($data['watched_at'] ?? ''));

        if ($userId <= 0 || !User::find($userId)) {
            throw new RuntimeException('请选择有效用户', 400);
        }

        if ($videoId <= 0 || !Video::find($videoId)) {
            throw new RuntimeException('请选择有效视频', 400);
        }

        if ($watchedAt === '') {
            $watchedAt = date('Y-m-d H:i:s');
        }

        if (strtotime($watchedAt) === false) {
            throw new RuntimeException('观看时间格式不正确', 400);
        }

        return [
            'user_id' => $userId,
            'video_id' => $videoId,
            'watched_at' => $watchedAt,
            'last_position_sec' => max(0, (int) ($data['last_position_sec'] ?? 0)),
            'progress_sec' => max(0, (int) ($data['progress_sec'] ?? 0)),
        ];
    }
}
