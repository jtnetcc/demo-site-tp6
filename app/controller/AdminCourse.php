<?php

namespace app\controller;

use app\service\AdminCourseService;
use RuntimeException;
use think\Request;

class AdminCourse extends AdminBase
{
    public function index(Request $request)
    {
        return $this->render('admin/courses/index', [
            'title' => '课程管理',
            'courses' => (new AdminCourseService())->list($request->get()),
            'filters' => $request->get(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('admin/courses/form', ['title' => '新增课程', 'course' => null, 'action' => '/admin/courses']);
    }

    public function save(Request $request)
    {
        try {
            (new AdminCourseService())->create($request->post());
            return $this->ok('/admin/courses', '课程已创建');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/courses/create', $e->getMessage());
        }
    }

    public function edit(Request $request, int $id)
    {
        $course = (new AdminCourseService())->find($id);

        if (!$course) {
            return $this->fail('/admin/courses', '课程不存在');
        }

        return $this->render('admin/courses/form', ['title' => '编辑课程', 'course' => $course, 'action' => '/admin/courses/' . $id]);
    }

    public function update(Request $request, int $id)
    {
        try {
            (new AdminCourseService())->update($id, $request->post());
            return $this->ok('/admin/courses', '课程已更新');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/courses/' . $id . '/edit', $e->getMessage());
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            (new AdminCourseService())->delete($id);
            return $this->ok('/admin/courses', '课程已删除');
        } catch (\Throwable $e) {
            return $this->fail('/admin/courses', $this->friendlyError($e));
        }
    }
}
