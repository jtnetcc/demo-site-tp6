<?php

namespace app\service;

use app\model\Course;
use app\model\Lesson;
use app\model\User;
use app\model\Video;
use app\model\VideoAsset;
use app\model\WatchHistory;
use RuntimeException;

class PlayAuthService
{
    private array $levelRank = [
        'NORMAL' => 1,
        'VIP' => 2,
        'SVIP' => 3,
    ];

    public function canPlayVideo(User $user, int $videoId): array
    {
        $userCheck = $this->checkUser($user);

        if (!$userCheck['allowed']) {
            return $userCheck;
        }

        $video = Video::find($videoId);

        if (!$video) {
            return $this->deny('视频不存在');
        }

        if ($video->status !== 'PUBLISHED') {
            return $this->deny('视频未发布');
        }

        if ($this->isPast($video->valid_until)) {
            return $this->deny('视频已过期');
        }

        if (!$this->hasRequiredLevel((string) $user->level, (string) $video->required_level)) {
            return $this->deny('用户等级不足');
        }

        $asset = VideoAsset::where('video_id', $videoId)->where('kind', 'VIDEO')->order('id', 'asc')->find();

        if (!$asset) {
            return $this->deny('视频暂无可播放资源');
        }

        $this->recordVideoPlay($user, $video);

        try {
            $signed = (new SignedPlaybackService())->issueAsset($user, 'VIDEO', $videoId, $asset);
        } catch (RuntimeException $e) {
            return $this->deny($e->getMessage());
        }

        return array_merge([
            'allowed' => true,
            'reason' => '允许播放',
            'video' => $video->toArray(),
        ], $signed);
    }

    public function canAccessCourse(User $user, int $courseId): array
    {
        $userCheck = $this->checkUser($user);

        if (!$userCheck['allowed']) {
            return $userCheck;
        }

        $course = Course::find($courseId);

        if (!$course) {
            return $this->deny('课程不存在');
        }

        if ($course->status !== 'PUBLISHED') {
            return $this->deny('课程未发布');
        }

        if ($this->hasRequiredLevel((string) $user->level, $course->required_level ? (string) $course->required_level : null)) {
            return [
                'allowed' => true,
                'reason' => '等级满足课程要求',
                'course' => $course->toArray(),
                'access_type' => 'level',
            ];
        }

        if ((new GrantService())->isGrantValid((int) $user->id, $courseId)) {
            return [
                'allowed' => true,
                'reason' => '课程授权有效',
                'course' => $course->toArray(),
                'access_type' => 'grant',
            ];
        }

        return $this->deny('课程权限不足');
    }

    public function canPlayLesson(User $user, int $lessonId): array
    {
        $lesson = Lesson::find($lessonId);

        if (!$lesson) {
            return $this->deny('课时不存在');
        }

        if ($lesson->status !== 'PUBLISHED') {
            return $this->deny('课时未发布');
        }

        if (!$lesson->video_object_key) {
            return $this->deny('课时暂无可播放资源');
        }

        $courseResult = $this->canAccessCourse($user, (int) $lesson->course_id);

        if (!$courseResult['allowed']) {
            return $courseResult;
        }

        try {
            $signed = (new SignedPlaybackService())->issue($user, 'LESSON', $lessonId, (string) $lesson->video_object_key);
        } catch (RuntimeException $e) {
            return $this->deny($e->getMessage());
        }

        return array_merge([
            'allowed' => true,
            'reason' => '允许播放课时',
            'lesson' => $lesson->toArray(),
            'course' => $courseResult['course'],
            'access_type' => $courseResult['access_type'],
        ], $signed);
    }

    private function hasRequiredLevel(string $userLevel, ?string $requiredLevel): bool
    {
        if (!$requiredLevel) {
            return true;
        }

        return ($this->levelRank[$userLevel] ?? 0) >= ($this->levelRank[$requiredLevel] ?? PHP_INT_MAX);
    }

    private function checkUser(User $user): array
    {
        if ($user->status !== 'ACTIVE') {
            return $this->deny('用户未激活');
        }

        if ($this->isPast($user->valid_until)) {
            return $this->deny('用户有效期已过');
        }

        return ['allowed' => true, 'reason' => '用户状态正常'];
    }

    private function recordVideoPlay(User $user, Video $video): void
    {
        $now = date('Y-m-d H:i:s');
        $history = WatchHistory::where('user_id', (int) $user->id)->where('video_id', (int) $video->id)->find();

        if ($history) {
            $history->save(['watched_at' => $now]);
        } else {
            WatchHistory::create([
                'user_id' => (int) $user->id,
                'video_id' => (int) $video->id,
                'watched_at' => $now,
                'last_position_sec' => 0,
                'progress_sec' => 0,
            ]);
        }

        $video->save(['play_count' => ((int) $video->play_count) + 1]);
    }

    private function deny(string $reason): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
        ];
    }

    private function isPast($value): bool
    {
        if (!$value) {
            return false;
        }

        if (is_object($value) && method_exists($value, 'getTimestamp')) {
            return $value->getTimestamp() < time();
        }

        $timestamp = strtotime((string) $value);

        return $timestamp !== false && $timestamp < time();
    }
}
