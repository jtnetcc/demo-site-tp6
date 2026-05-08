<?php

namespace app\controller;

use app\service\GrantService;
use RuntimeException;
use think\Request;

class Grant
{
    public function index(Request $request)
    {
        $filters = [
            'user_id' => $request->get('user_id'),
            'course_id' => $request->get('course_id'),
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 20),
        ];

        return $this->success((new GrantService())->list($filters));
    }

    public function read(Request $request, int $id)
    {
        $grant = (new GrantService())->find($id);

        if (!$grant) {
            return $this->error('授权不存在', 404);
        }

        return $this->success($grant->toArray());
    }

    public function save(Request $request)
    {
        $admin = $request->user ?? null;

        if (!$admin) {
            return $this->error('请先登录', 401);
        }

        try {
            $grant = (new GrantService())->create($request->post(), $admin);
            return $this->success($grant->toArray());
        } catch (RuntimeException $e) {
            return $this->exception($e);
        }
    }

    public function update(Request $request, int $id)
    {
        $admin = $request->user ?? null;

        if (!$admin) {
            return $this->error('请先登录', 401);
        }

        try {
            $grant = (new GrantService())->update($id, $request->post(), $admin);
            return $this->success($grant->toArray());
        } catch (RuntimeException $e) {
            return $this->exception($e);
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            (new GrantService())->delete($id);
            return $this->success(['deleted' => true]);
        } catch (RuntimeException $e) {
            return $this->exception($e);
        }
    }

    private function success(array $data = [], string $message = '操作成功')
    {
        return json(['data' => $data, 'message' => $message]);
    }

    private function error(string $message, int $code)
    {
        return json(['error' => $message, 'code' => $code], $code);
    }

    private function exception(RuntimeException $e)
    {
        $code = (int) $e->getCode();
        $code = $code >= 400 && $code < 600 ? $code : 400;

        return $this->error($e->getMessage(), $code);
    }
}
