<?php

namespace app\controller;

use app\service\PlayAuthService;
use think\Request;

class PlayAuth
{
    public function video(Request $request, int $id)
    {
        $user = $request->user ?? null;

        if (!$user) {
            return $this->error('请先登录', 401);
        }

        $result = (new PlayAuthService())->canPlayVideo($user, $id);

        if (!$result['allowed']) {
            return $this->error($result['reason'], 403);
        }

        return $this->success($result);
    }

    public function lesson(Request $request, int $id)
    {
        $user = $request->user ?? null;

        if (!$user) {
            return $this->error('请先登录', 401);
        }

        $result = (new PlayAuthService())->canPlayLesson($user, $id);

        if (!$result['allowed']) {
            return $this->error($result['reason'], 403);
        }

        return $this->success($result);
    }

    private function success(array $data = [], string $message = '操作成功')
    {
        return json(['data' => $data, 'message' => $message]);
    }

    private function error(string $message, int $code)
    {
        return json(['error' => $message, 'code' => $code], $code);
    }
}
