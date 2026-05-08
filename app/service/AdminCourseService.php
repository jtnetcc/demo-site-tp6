<?php

namespace app\service;

use app\model\Course;
use RuntimeException;

class AdminCourseService
{
    public function list(array $filters)
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = Course::order('created_at', 'desc');

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($subQuery) use ($q) {
                $subQuery->whereLike('title', '%' . $q . '%')->whereOr('description', 'like', '%' . $q . '%');
            });
        }

        foreach (['status', 'required_level'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, (string) $filters[$field]);
            }
        }

        return $query->paginate(['list_rows' => 20, 'page' => $page, 'query' => $filters]);
    }

    public function find(int $id): ?Course
    {
        return Course::find($id);
    }

    public function options()
    {
        return Course::order('created_at', 'desc')->select();
    }

    public function create(array $data): Course
    {
        return Course::create($this->payload($data));
    }

    public function update(int $id, array $data): Course
    {
        $course = Course::find($id);

        if (!$course) {
            throw new RuntimeException('课程不存在', 404);
        }

        $course->save($this->payload($data));

        return $course;
    }

    public function delete(int $id): bool
    {
        $course = Course::find($id);

        if (!$course) {
            throw new RuntimeException('课程不存在', 404);
        }

        return (bool) $course->delete();
    }

    private function payload(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('课程标题不能为空', 400);
        }

        return [
            'title' => $title,
            'description' => $this->nullable($data['description'] ?? null),
            'cover_url' => $this->nullable($data['cover_url'] ?? null),
            'required_level' => in_array(($data['required_level'] ?? null), ['NORMAL', 'VIP', 'SVIP'], true) ? $data['required_level'] : null,
            'status' => in_array(($data['status'] ?? 'DRAFT'), ['DRAFT', 'PUBLISHED'], true) ? $data['status'] : 'DRAFT',
        ];
    }

    private function nullable($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
