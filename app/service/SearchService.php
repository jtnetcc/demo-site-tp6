<?php

namespace app\service;

use app\model\Course;
use app\model\Video;

class SearchService
{
    public function search(array $filters): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $type = (string) ($filters['type'] ?? 'all');
        $page = max(1, (int) ($filters['page'] ?? 1));

        if ($q === '') {
            return [
                'q' => $q,
                'type' => $type,
                'videos' => [],
                'courses' => [],
            ];
        }

        return [
            'q' => $q,
            'type' => $type,
            'videos' => $type === 'courses' ? [] : $this->videos($q, $page, $type === 'all' ? 8 : 12, $type),
            'courses' => $type === 'videos' ? [] : $this->courses($q, $page, $type === 'all' ? 8 : 12, $type),
        ];
    }

    public function videos(string $q, int $page = 1, int $limit = 12, string $type = 'all')
    {
        return Video::with(['category'])
            ->where('status', 'PUBLISHED')
            ->where(function ($query) use ($q) {
                $query->whereLike('title', '%' . $q . '%')->whereOr('description', 'like', '%' . $q . '%');
            })
            ->order('created_at', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page, 'query' => ['q' => $q, 'type' => $type]]);
    }

    public function courses(string $q, int $page = 1, int $limit = 12, string $type = 'all')
    {
        return Course::where('status', 'PUBLISHED')
            ->where(function ($query) use ($q) {
                $query->whereLike('title', '%' . $q . '%')->whereOr('description', 'like', '%' . $q . '%');
            })
            ->order('created_at', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page, 'query' => ['q' => $q, 'type' => $type]]);
    }
}
