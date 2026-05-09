<?php

namespace app\service;

use app\model\Comment;
use app\model\Favorite;
use app\model\Video;
use app\model\VideoLike;
use app\model\User;
use RuntimeException;

class VideoService
{
    public function list(array $filters)
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = Video::with(['category', 'tags'])->where('status', 'PUBLISHED');

        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['level'])) {
            $query->where('required_level', (string) $filters['level']);
        }

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($subQuery) use ($q) {
                $subQuery->whereLike('title', '%' . $q . '%')->whereOr('description', 'like', '%' . $q . '%');
            });
        }

        if (($filters['sort'] ?? '') === 'popular') {
            $query->order('play_count', 'desc')->order('created_at', 'desc');
        } else {
            $query->order('created_at', 'desc');
        }

        return $query->paginate(['list_rows' => 12, 'page' => $page, 'query' => $filters]);
    }

    public function detailData(int $id, ?User $user = null): array
    {
        $video = Video::with(['category', 'tags', 'assets', 'creator'])->where('status', 'PUBLISHED')->find($id);

        if (!$video) {
            throw new RuntimeException('视频不存在', 404);
        }

        $comments = Comment::with(['user'])
            ->where('video_id', $id)
            ->where('status', 'VISIBLE')
            ->order('created_at', 'desc')
            ->paginate(['list_rows' => 10]);

        return [
            'video' => $video,
            'comments' => $comments,
            'stats' => $this->stats($id, $user),
            'relatedVideos' => Video::with(['category'])
                ->where('status', 'PUBLISHED')
                ->where('id', '<>', $id)
                ->where('category_id', $video->category_id)
                ->order('created_at', 'desc')
                ->limit(4)
                ->select(),
        ];
    }

    private function stats(int $videoId, ?User $user = null): array
    {
        $userId = $user ? (int) $user->id : 0;

        return [
            'likeCount' => VideoLike::where('video_id', $videoId)->count(),
            'favoriteCount' => Favorite::where('video_id', $videoId)->count(),
            'commentCount' => Comment::where('video_id', $videoId)->where('status', 'VISIBLE')->count(),
            'liked' => $userId > 0 && VideoLike::where('user_id', $userId)->where('video_id', $videoId)->find() !== null,
            'favorited' => $userId > 0 && Favorite::where('user_id', $userId)->where('video_id', $videoId)->find() !== null,
        ];
    }

    public function toggleLike(User $user, int $videoId): array
    {
        $this->assertPublishedVideo($videoId);
        $like = VideoLike::where('user_id', (int) $user->id)->where('video_id', $videoId)->find();

        if ($like) {
            $like->delete();
        } else {
            VideoLike::create(['user_id' => (int) $user->id, 'video_id' => $videoId]);
        }

        return $this->stats($videoId, $user);
    }

    public function toggleFavorite(User $user, int $videoId): array
    {
        $this->assertPublishedVideo($videoId);
        $favorite = Favorite::where('user_id', (int) $user->id)->where('video_id', $videoId)->find();

        if ($favorite) {
            $favorite->delete();
        } else {
            Favorite::create(['user_id' => (int) $user->id, 'video_id' => $videoId]);
        }

        return $this->stats($videoId, $user);
    }

    public function addComment(User $user, int $videoId, string $content): array
    {
        $video = $this->assertPublishedVideo($videoId);

        if (!$video->allow_comments) {
            throw new RuntimeException('该视频已关闭评论', 403);
        }

        $content = trim($content);

        if ($content === '') {
            throw new RuntimeException('评论内容不能为空', 400);
        }

        $comment = Comment::create([
            'user_id' => (int) $user->id,
            'video_id' => $videoId,
            'content' => $content,
            'status' => 'VISIBLE',
        ]);

        return [
            'stats' => $this->stats($videoId, $user),
            'comment' => [
                'id' => (int) $comment->id,
                'content' => $content,
                'user_name' => (string) ($user->display_name ?: $user->username),
                'created_at' => (string) $comment->created_at,
            ],
        ];
    }

    private function assertPublishedVideo(int $videoId): Video
    {
        $video = Video::where('status', 'PUBLISHED')->find($videoId);

        if (!$video) {
            throw new RuntimeException('视频不存在', 404);
        }

        return $video;
    }
}
