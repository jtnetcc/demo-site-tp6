<?php

namespace app\service;

use app\model\Course;
use app\model\Lesson;
use RuntimeException;

class AdminLessonService
{
    public function list(array $filters)
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = Lesson::with(['course'])->order('created_at', 'desc');

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($subQuery) use ($q) {
                $subQuery->whereLike('title', '%' . $q . '%')->whereOr('summary', 'like', '%' . $q . '%');
            });
        }

        if (!empty($filters['course_id'])) {
            $query->where('course_id', (int) $filters['course_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        return $query->paginate(['list_rows' => 20, 'page' => $page, 'query' => $filters]);
    }

    public function find(int $id): ?Lesson
    {
        return Lesson::with(['course'])->find($id);
    }

    public function options()
    {
        return Lesson::with(['course'])->order('created_at', 'desc')->select();
    }

    public function create(array $data): Lesson
    {
        return Lesson::create($this->payload($data));
    }

    public function update(int $id, array $data): Lesson
    {
        $lesson = Lesson::find($id);

        if (!$lesson) {
            throw new RuntimeException('课时不存在', 404);
        }

        $lesson->save($this->payload($data));

        return $lesson;
    }

    public function delete(int $id): bool
    {
        $lesson = Lesson::find($id);

        if (!$lesson) {
            throw new RuntimeException('课时不存在', 404);
        }

        return (bool) $lesson->delete();
    }

    private function payload(array $data): array
    {
        $courseId = (int) ($data['course_id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));

        if ($courseId <= 0 || !Course::find($courseId)) {
            throw new RuntimeException('请选择有效课程', 400);
        }

        if ($title === '') {
            throw new RuntimeException('课时标题不能为空', 400);
        }

        $videoObjectKey = $this->nullable($data['video_object_key'] ?? null);
        $status = in_array(($data['status'] ?? 'DRAFT'), ['DRAFT', 'PUBLISHED'], true) ? $data['status'] : 'DRAFT';

        if ($status === 'PUBLISHED' && $videoObjectKey === null) {
            throw new RuntimeException('课时发布前请先填写视频地址或上传课时视频', 400);
        }

        return [
            'course_id' => $courseId,
            'title' => $title,
            'summary' => $this->nullable($data['summary'] ?? null),
            'video_object_key' => $videoObjectKey,
            'duration_sec' => max(0, (int) ($data['duration_sec'] ?? 0)),
            'sort_order' => max(1, (int) ($data['sort_order'] ?? 1)),
            'status' => $status,
        ];
    }

    private function nullable($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
