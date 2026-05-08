<?php

namespace app\controller;

use app\service\AdminOptionService;
use app\service\AdminWatchHistoryService;
use RuntimeException;
use think\Request;

class AdminWatchHistory extends AdminBase
{
    public function index(Request $request)
    {
        $options = new AdminOptionService();

        return $this->render('admin/watch_history/index', [
            'title' => '观看记录',
            'histories' => (new AdminWatchHistoryService())->list($request->get()),
            'users' => $options->users(),
            'videos' => $options->videos(),
            'filters' => $request->get(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->form(null, '/admin/watch-history', '新增观看记录');
    }

    public function save(Request $request)
    {
        try {
            (new AdminWatchHistoryService())->create($request->post());
            return $this->ok('/admin/watch-history', '观看记录已创建');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/watch-history/create', $e->getMessage());
        }
    }

    public function edit(Request $request, int $id)
    {
        $history = (new AdminWatchHistoryService())->find($id);

        if (!$history) {
            return $this->fail('/admin/watch-history', '观看记录不存在');
        }

        return $this->form($history, '/admin/watch-history/' . $id, '编辑观看记录');
    }

    public function update(Request $request, int $id)
    {
        try {
            (new AdminWatchHistoryService())->update($id, $request->post());
            return $this->ok('/admin/watch-history', '观看记录已更新');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/watch-history/' . $id . '/edit', $e->getMessage());
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            (new AdminWatchHistoryService())->delete($id);
            return $this->ok('/admin/watch-history', '观看记录已删除');
        } catch (\Throwable $e) {
            return $this->fail('/admin/watch-history', $this->friendlyError($e));
        }
    }

    private function form($history, string $action, string $title)
    {
        $options = new AdminOptionService();

        return $this->render('admin/watch_history/form', [
            'title' => $title,
            'history' => $history,
            'users' => $options->users(),
            'videos' => $options->videos(),
            'action' => $action,
        ]);
    }
}
