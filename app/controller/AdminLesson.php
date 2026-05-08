<?php

namespace app\controller;

use app\service\AdminCourseService;
use app\service\AdminLessonService;
use RuntimeException;
use think\Request;

class AdminLesson extends AdminBase
{
    public function index(Request $request)
    {
        return $this->render('admin/lessons/index', [
            'title' => '课时管理',
            'lessons' => (new AdminLessonService())->list($request->get()),
            'courses' => (new AdminCourseService())->options(),
            'filters' => $request->get(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('admin/lessons/form', [
            'title' => '新增课时',
            'lesson' => null,
            'courses' => (new AdminCourseService())->options(),
            'action' => '/admin/lessons',
        ]);
    }

    public function save(Request $request)
    {
        try {
            (new AdminLessonService())->create($request->post());
            return $this->ok('/admin/lessons', '课时已创建');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/lessons/create', $e->getMessage());
        }
    }

    public function edit(Request $request, int $id)
    {
        $lesson = (new AdminLessonService())->find($id);

        if (!$lesson) {
            return $this->fail('/admin/lessons', '课时不存在');
        }

        return $this->render('admin/lessons/form', [
            'title' => '编辑课时',
            'lesson' => $lesson,
            'courses' => (new AdminCourseService())->options(),
            'action' => '/admin/lessons/' . $id,
        ]);
    }

    public function update(Request $request, int $id)
    {
        try {
            (new AdminLessonService())->update($id, $request->post());
            return $this->ok('/admin/lessons', '课时已更新');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/lessons/' . $id . '/edit', $e->getMessage());
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            (new AdminLessonService())->delete($id);
            return $this->ok('/admin/lessons', '课时已删除');
        } catch (\Throwable $e) {
            return $this->fail('/admin/lessons', $this->friendlyError($e));
        }
    }
}
