<?php

namespace app\controller;

use app\service\AdminCourseService;
use app\service\AdminImportTaskService;
use app\service\AdminLessonService;
use app\service\AdminOptionService;
use RuntimeException;
use think\Request;

class AdminImportTask extends AdminBase
{
    public function index(Request $request)
    {
        $options = new AdminOptionService();

        return $this->render('admin/import_tasks/index', [
            'title' => '导入任务',
            'tasks' => (new AdminImportTaskService())->list($request->get()),
            'users' => $options->users(),
            'videos' => $options->videos(),
            'courses' => $options->courses(),
            'lessons' => (new AdminLessonService())->options(),
            'filters' => $request->get(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('admin/import_tasks/form', [
            'title' => '新增导入任务',
            'videos' => (new AdminOptionService())->videos(),
            'courses' => (new AdminCourseService())->options(),
            'lessons' => (new AdminLessonService())->options(),
            'defaults' => $request->get(),
            'action' => '/admin/import-tasks',
        ]);
    }

    public function save(Request $request)
    {
        try {
            $task = (new AdminImportTaskService())->create($request->post(), $this->adminUser($request), $request->file('file'));
            $message = (string) $task->status === 'PENDING' ? '网盘导入任务已创建，请在任务列表执行转存' : '导入任务已完成';
            return $this->ok('/admin/import-tasks', $message);
        } catch (RuntimeException $e) {
            return $this->fail('/admin/import-tasks/create', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/import-tasks/create', $this->friendlyError($e));
        }
    }

    public function process(Request $request, int $id)
    {
        try {
            (new AdminImportTaskService())->process($id);
            return $this->ok('/admin/import-tasks/' . $id, '网盘导入任务已完成');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/import-tasks/' . $id, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/import-tasks/' . $id, $this->friendlyError($e));
        }
    }

    public function read(Request $request, int $id)
    {
        $task = (new AdminImportTaskService())->find($id);

        if (!$task) {
            return $this->fail('/admin/import-tasks', '导入任务不存在');
        }

        return $this->render('admin/import_tasks/read', [
            'title' => '导入任务详情',
            'task' => $task,
        ]);
    }

    public function update(Request $request, int $id)
    {
        try {
            (new AdminImportTaskService())->update($id, $request->post());
            return $this->ok('/admin/import-tasks', '导入任务已更新');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/import-tasks', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/import-tasks', $this->friendlyError($e));
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            (new AdminImportTaskService())->delete($id);
            return $this->ok('/admin/import-tasks', '导入任务已删除');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/import-tasks', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/import-tasks', $this->friendlyError($e));
        }
    }
}
