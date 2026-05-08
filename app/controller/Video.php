<?php

namespace app\controller;

use app\model\Category;
use app\service\PlayAuthService;
use app\service\VideoService;
use RuntimeException;
use think\Request;

class Video extends WebController
{
    public function index(Request $request)
    {
        $filters = $request->get();
        $data = $this->viewData([
            'videos' => (new VideoService())->list($filters),
            'categories' => Category::order('created_at', 'desc')->select(),
            'filters' => $filters,
        ]);
        $this->clearFlash();

        return view('video/index', $data);
    }

    public function read(Request $request, int $id)
    {
        try {
            $data = (new VideoService())->detailData($id, $this->currentUser());
            $data = $this->viewData($data);
            $this->clearFlash();
            return view('video/detail', $data);
        } catch (RuntimeException $e) {
            abort($e->getCode() ?: 404, $e->getMessage());
        }
    }

    public function playAuth(Request $request, int $id)
    {
        $user = $this->requireAjaxUser($request);

        if (!$user) {
            return $this->error('请先登录', 401);
        }

        $result = (new PlayAuthService())->canPlayVideo($user, $id);

        if (!$result['allowed']) {
            return $this->error($result['reason'], 403);
        }

        return $this->success($result);
    }

    public function like(Request $request, int $id)
    {
        return $this->interaction($request, fn ($user) => (new VideoService())->toggleLike($user, $id));
    }

    public function favorite(Request $request, int $id)
    {
        return $this->interaction($request, fn ($user) => (new VideoService())->toggleFavorite($user, $id));
    }

    public function comment(Request $request, int $id)
    {
        return $this->interaction($request, fn ($user) => (new VideoService())->addComment($user, $id, (string) $request->post('content', '')));
    }

    private function interaction(Request $request, callable $callback)
    {
        $user = $this->requireAjaxUser($request);

        if (!$user) {
            return $this->error('请先登录', 401);
        }

        try {
            return $this->success($callback($user));
        } catch (RuntimeException $e) {
            $code = (int) $e->getCode();
            return $this->error($e->getMessage(), $code >= 400 && $code < 600 ? $code : 400);
        }
    }
}
