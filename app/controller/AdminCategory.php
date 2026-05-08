<?php

namespace app\controller;

use app\service\AdminTaxonomyService;
use RuntimeException;
use think\Request;

class AdminCategory extends AdminBase
{
    public function index(Request $request)
    {
        return $this->render('admin/categories/index', [
            'title' => '分类管理',
            'categories' => (new AdminTaxonomyService())->categories($request->get()),
            'filters' => $request->get(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('admin/categories/form', ['title' => '新增分类', 'category' => null, 'action' => '/admin/categories']);
    }

    public function save(Request $request)
    {
        try {
            (new AdminTaxonomyService())->createCategory($request->post());
            return $this->ok('/admin/categories', '分类已创建');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/categories/create', $e->getMessage());
        }
    }

    public function edit(Request $request, int $id)
    {
        $category = (new AdminTaxonomyService())->findCategory($id);

        if (!$category) {
            return $this->fail('/admin/categories', '分类不存在');
        }

        return $this->render('admin/categories/form', ['title' => '编辑分类', 'category' => $category, 'action' => '/admin/categories/' . $id]);
    }

    public function update(Request $request, int $id)
    {
        try {
            (new AdminTaxonomyService())->updateCategory($id, $request->post());
            return $this->ok('/admin/categories', '分类已更新');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/categories/' . $id . '/edit', $e->getMessage());
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            (new AdminTaxonomyService())->deleteCategory($id);
            return $this->ok('/admin/categories', '分类已删除');
        } catch (\Throwable $e) {
            return $this->fail('/admin/categories', $this->friendlyError($e));
        }
    }
}
