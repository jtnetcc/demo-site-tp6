<?php

namespace app\service;

use app\model\Category;
use app\model\Tag;
use RuntimeException;

class AdminTaxonomyService
{
    public function categories(array $filters)
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = Category::order('created_at', 'desc');

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($subQuery) use ($q) {
                $subQuery->whereLike('name', '%' . $q . '%')
                    ->whereOr('slug', 'like', '%' . $q . '%')
                    ->whereOr('description', 'like', '%' . $q . '%');
            });
        }

        return $query->paginate(['list_rows' => 20, 'page' => $page, 'query' => $filters]);
    }

    public function tags(array $filters)
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = Tag::order('created_at', 'desc');

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($subQuery) use ($q) {
                $subQuery->whereLike('name', '%' . $q . '%')->whereOr('slug', 'like', '%' . $q . '%');
            });
        }

        return $query->paginate(['list_rows' => 20, 'page' => $page, 'query' => $filters]);
    }

    public function findCategory(int $id): ?Category
    {
        return Category::find($id);
    }

    public function findTag(int $id): ?Tag
    {
        return Tag::find($id);
    }

    public function createCategory(array $data): Category
    {
        return Category::create($this->categoryPayload($data));
    }

    public function updateCategory(int $id, array $data): Category
    {
        $category = Category::find($id);

        if (!$category) {
            throw new RuntimeException('分类不存在', 404);
        }

        $category->save($this->categoryPayload($data));

        return $category;
    }

    public function deleteCategory(int $id): bool
    {
        $category = Category::find($id);

        if (!$category) {
            throw new RuntimeException('分类不存在', 404);
        }

        return (bool) $category->delete();
    }

    public function createTag(array $data): Tag
    {
        return Tag::create($this->tagPayload($data));
    }

    public function updateTag(int $id, array $data): Tag
    {
        $tag = Tag::find($id);

        if (!$tag) {
            throw new RuntimeException('标签不存在', 404);
        }

        $tag->save($this->tagPayload($data));

        return $tag;
    }

    public function deleteTag(int $id): bool
    {
        $tag = Tag::find($id);

        if (!$tag) {
            throw new RuntimeException('标签不存在', 404);
        }

        return (bool) $tag->delete();
    }

    private function categoryPayload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($name === '' || $slug === '') {
            throw new RuntimeException('名称和 slug 不能为空', 400);
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => $this->nullable($data['description'] ?? null),
        ];
    }

    private function tagPayload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($name === '' || $slug === '') {
            throw new RuntimeException('名称和 slug 不能为空', 400);
        }

        return ['name' => $name, 'slug' => $slug];
    }

    private function nullable($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
