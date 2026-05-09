<?php

namespace app\service;

use app\model\Course;
use app\model\Grant;
use app\model\Lesson;
use app\model\User;
use RuntimeException;

class CourseService
{
    private array $levelRank = ['NORMAL' => 1, 'VIP' => 2, 'SVIP' => 3];

    public function list(array $filters)
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = Course::where('status', 'PUBLISHED');

        if (!empty($filters['level'])) {
            $query->where('required_level', (string) $filters['level']);
        }

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($subQuery) use ($q) {
                $subQuery->whereLike('title', '%' . $q . '%')->whereOr('description', 'like', '%' . $q . '%');
            });
        }

        return $query->order('created_at', 'desc')->paginate(['list_rows' => 12, 'page' => $page, 'query' => $filters]);
    }

    public function detailData(int $id, ?User $user = null): array
    {
        $course = Course::where('status', 'PUBLISHED')->find($id);

        if (!$course) {
            throw new RuntimeException('课程不存在', 404);
        }

        return [
            'course' => $course,
            'lessons' => $this->publishedLessons($id),
            'access' => $this->userCanAccess($user, $id),
        ];
    }

    private function publishedLessons(int $courseId)
    {
        return Lesson::where('course_id', $courseId)
            ->where('status', 'PUBLISHED')
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select();
    }

    public function lessonPageData(User $user, int $courseId, int $lessonId): array
    {
        $course = Course::where('status', 'PUBLISHED')->find($courseId);

        if (!$course) {
            throw new RuntimeException('课程不存在', 404);
        }

        $lesson = Lesson::where('id', $lessonId)->where('course_id', $courseId)->where('status', 'PUBLISHED')->find();

        if (!$lesson) {
            throw new RuntimeException('课时不存在', 404);
        }

        $access = (new PlayAuthService())->canAccessCourse($user, $courseId);

        return [
            'course' => $course,
            'lesson' => $lesson,
            'lessons' => $this->publishedLessons($courseId),
            'access' => $access,
        ];
    }

    private function userCanAccess(?User $user, int $courseId): array
    {
        if (!$user) {
            return ['allowed' => false, 'reason' => '请先登录', 'access_type' => 'guest'];
        }

        return (new PlayAuthService())->canAccessCourse($user, $courseId);
    }

    public function myCourses(User $user): array
    {
        $levels = $this->allowedLevels((string) $user->level);
        $now = date('Y-m-d H:i:s');
        $courses = [];

        foreach (Course::where('status', 'PUBLISHED')->where(function ($query) use ($levels) {
            $query->whereNull('required_level')->whereOr('required_level', 'in', $levels);
        })->select() as $course) {
            $courses[(int) $course->id] = ['course' => $course, 'access_type' => $course->required_level ? 'level' : 'public'];
        }

        $grants = Grant::with(['course'])
            ->where('user_id', (int) $user->id)
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')->whereOr('expires_at', '>', $now);
            })
            ->order('created_at', 'desc')
            ->select();

        foreach ($grants as $grant) {
            if ($grant->course && $grant->course->status === 'PUBLISHED') {
                $courses[(int) $grant->course->id] = ['course' => $grant->course, 'access_type' => 'grant', 'grant' => $grant];
            }
        }

        return array_values($courses);
    }

    private function allowedLevels(string $level): array
    {
        $rank = $this->levelRank[$level] ?? 0;

        return array_keys(array_filter($this->levelRank, fn ($value) => $value <= $rank));
    }
}
