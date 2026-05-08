<?php

namespace app\controller;

use app\service\AdminCommentService;
use RuntimeException;
use think\Request;

class AdminComment extends AdminBase
{
    public function index(Request $request)
    {
        return $this->render('admin/comments/index', [
            'title' => '评论审核',
            'comments' => (new AdminCommentService())->list($request->get()),
            'filters' => $request->get(),
        ]);
    }

    public function hide(Request $request, int $id)
    {
        try {
            (new AdminCommentService())->hide($id);
            return $this->ok('/admin/comments', '评论已隐藏');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/comments', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/comments', $this->friendlyError($e));
        }
    }

    public function show(Request $request, int $id)
    {
        try {
            (new AdminCommentService())->show($id);
            return $this->ok('/admin/comments', '评论已恢复显示');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/comments', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/comments', $this->friendlyError($e));
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            (new AdminCommentService())->delete($id);
            return $this->ok('/admin/comments', '评论已删除');
        } catch (RuntimeException $e) {
            return $this->fail('/admin/comments', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail('/admin/comments', $this->friendlyError($e));
        }
    }
}
