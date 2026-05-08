<?php

namespace app\controller;

use app\service\AdminOptionService;
use app\service\GrantService;
use RuntimeException;
use think\Request;

class AdminGrant extends AdminBase
{
    public function index(Request $request)
    {
        $options = new AdminOptionService();

        return $this->render('admin/grants/index', [
            'title' => '课程授权',
            'grants' => (new GrantService())->list($request->get()),
            'users' => $options->users(),
            'courses' => $options->courses(),
            'filters' => $request->get(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->form(null, '/admin/grants', '新增授权');
    }

    public function save(Request $request)
    {
        try {
            (new GrantService())->create($request->post(), $this->adminUser($request));
            return $this->ok('/admin/grants', '授权已保存');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/grants/create', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/grants/create', $this->friendlyError($e));
        }
    }

    public function edit(Request $request, int $id)
    {
        $grant = (new GrantService())->find($id);

        if (!$grant) {
            return $this->fail('/admin/grants', '授权不存在');
        }

        return $this->form($grant, '/admin/grants/' . $id, '编辑授权');
    }

    public function update(Request $request, int $id)
    {
        try {
            (new GrantService())->update($id, $request->post(), $this->adminUser($request));
            return $this->ok('/admin/grants', '授权已更新');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/grants/' . $id . '/edit', $e->getMessage());
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            (new GrantService())->delete($id);
            return $this->ok('/admin/grants', '授权已删除');
        } catch (\Throwable $e) {
            return $this->fail('/admin/grants', $this->friendlyError($e));
        }
    }

    private function form($grant, string $action, string $title)
    {
        $options = new AdminOptionService();

        return $this->render('admin/grants/form', [
            'title' => $title,
            'grant' => $grant,
            'users' => $options->users(),
            'courses' => $options->courses(),
            'action' => $action,
        ]);
    }
}
