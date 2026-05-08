<?php

namespace app\controller;

use app\service\AdminTaxonomyService;
use RuntimeException;
use think\Request;

class AdminTag extends AdminBase
{
    public function index(Request $request)
    {
        return $this->render('admin/tags/index', [
            'title' => '标签管理',
            'tags' => (new AdminTaxonomyService())->tags($request->get()),
            'filters' => $request->get(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('admin/tags/form', ['title' => '新增标签', 'tag' => null, 'action' => '/admin/tags']);
    }

    public function save(Request $request)
    {
        try {
            (new AdminTaxonomyService())->createTag($request->post());
            return $this->ok('/admin/tags', '标签已创建');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/tags/create', $e->getMessage());
        }
    }

    public function edit(Request $request, int $id)
    {
        $tag = (new AdminTaxonomyService())->findTag($id);

        if (!$tag) {
            return $this->fail('/admin/tags', '标签不存在');
        }

        return $this->render('admin/tags/form', ['title' => '编辑标签', 'tag' => $tag, 'action' => '/admin/tags/' . $id]);
    }

    public function update(Request $request, int $id)
    {
        try {
            (new AdminTaxonomyService())->updateTag($id, $request->post());
            return $this->ok('/admin/tags', '标签已更新');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/tags/' . $id . '/edit', $e->getMessage());
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            (new AdminTaxonomyService())->deleteTag($id);
            return $this->ok('/admin/tags', '标签已删除');
        } catch (\Throwable $e) {
            return $this->fail('/admin/tags', $this->friendlyError($e));
        }
    }
}
