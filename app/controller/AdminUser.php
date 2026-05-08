<?php

namespace app\controller;

use app\service\AdminUserService;
use RuntimeException;
use think\Request;

class AdminUser extends AdminBase
{
    public function index(Request $request)
    {
        return $this->render('admin/users/index', [
            'title' => '用户管理',
            'users' => (new AdminUserService())->list($request->get()),
            'filters' => $request->get(),
            'adminUser' => $this->adminUser($request),
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('admin/users/form', ['title' => '新增用户', 'user' => null, 'action' => '/admin/users']);
    }

    public function save(Request $request)
    {
        try {
            (new AdminUserService())->create($request->post());
            return $this->ok('/admin/users', '用户已创建');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/users/create', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/users/create', $this->friendlyError($e));
        }
    }

    public function edit(Request $request, int $id)
    {
        $user = (new AdminUserService())->find($id);

        if (!$user) {
            return $this->fail('/admin/users', '用户不存在');
        }

        return $this->render('admin/users/form', ['title' => '编辑用户', 'user' => $user, 'action' => '/admin/users/' . $id]);
    }

    public function update(Request $request, int $id)
    {
        try {
            (new AdminUserService())->update($id, $request->post(), $this->adminUser($request));
            return $this->ok('/admin/users', '用户已更新');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/users/' . $id . '/edit', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/users/' . $id . '/edit', $this->friendlyError($e));
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            (new AdminUserService())->delete($id, $this->adminUser($request));
            return $this->ok('/admin/users', '用户已删除');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/users', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/users', $this->friendlyError($e));
        }
    }
}
