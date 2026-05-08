<?php

namespace app\controller;

use app\service\AdminVideoService;
use RuntimeException;
use think\Request;

class AdminVideo extends AdminBase
{
    public function index(Request $request)
    {
        $service = new AdminVideoService();

        return $this->render('admin/videos/index', [
            'title' => '视频管理',
            'videos' => $videos = $service->list($request->get()),
            'categories' => $service->categoryOptions(),
            'tags' => $service->tagOptions(),
            'assetMap' => $service->assetMap($videos),
            'selectedTagMap' => $service->selectedTagMap($videos),
            'filters' => $request->get(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->form(null, '/admin/videos', '新增视频');
    }

    public function save(Request $request)
    {
        try {
            (new AdminVideoService())->create($request->post(), $this->adminUser($request), $this->optionalFile($request, 'cover_file'), $this->optionalFile($request, 'video_file'));
            return $this->ok('/admin/videos', '视频已创建');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/videos/create', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/videos/create', $this->friendlyError($e));
        }
    }

    public function edit(Request $request, int $id)
    {
        $video = (new AdminVideoService())->find($id);

        if (!$video) {
            return $this->fail('/admin/videos', '视频不存在');
        }

        return $this->form($video, '/admin/videos/' . $id, '编辑视频');
    }

    public function update(Request $request, int $id)
    {
        try {
            (new AdminVideoService())->update($id, $request->post(), $this->adminUser($request), $this->optionalFile($request, 'cover_file'), $this->optionalFile($request, 'video_file'));
            return $this->ok('/admin/videos', '视频已更新');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/videos/' . $id . '/edit', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/videos/' . $id . '/edit', $this->friendlyError($e));
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            (new AdminVideoService())->delete($id);
            return $this->ok('/admin/videos', '视频已删除');
        } catch (\Throwable $e) {
            return $this->fail('/admin/videos', $this->friendlyError($e));
        }
    }

    private function optionalFile(Request $request, string $name)
    {
        try {
            return $request->file($name);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '没有文件被上传')) {
                return null;
            }

            throw $e;
        }
    }

    private function form($video, string $action, string $title)
    {
        $service = new AdminVideoService();

        $selectedTagIds = $service->selectedTagIds($video);

        return $this->render('admin/videos/form', [
            'title' => $title,
            'video' => $video,
            'asset' => $service->videoAsset($video),
            'selectedTagIds' => $selectedTagIds,
            'selectedTagIdsString' => implode(',', $selectedTagIds),
            'categories' => $service->categoryOptions(),
            'tags' => $service->tagOptions(),
            'action' => $action,
        ]);
    }
}
