<?php

namespace app\controller;

use app\service\CourseService;
use app\service\PlayAuthService;
use RuntimeException;
use think\Request;

class Lesson extends WebController
{
    public function read(Request $request, int $courseId, int $lessonId)
    {
        $user = $request->user ?? $this->currentUser();

        try {
            $data = (new CourseService())->lessonPageData($user, $courseId, $lessonId);
            $data = $this->viewData($data);
            $this->clearFlash();
            return view('lesson/detail', $data);
        } catch (RuntimeException $e) {
            abort($e->getCode() ?: 404, $e->getMessage());
        }
    }

    public function playAuth(Request $request, int $courseId, int $lessonId)
    {
        $user = $this->requireAjaxUser($request);

        if (!$user) {
            return $this->error('请先登录', 401);
        }

        $result = (new PlayAuthService())->canPlayLesson($user, $lessonId);

        if (!$result['allowed']) {
            return $this->error($result['reason'], 403);
        }

        if ((int) ($result['lesson']['course_id'] ?? 0) !== $courseId) {
            return $this->error('课时不属于该课程', 400);
        }

        return $this->success($result);
    }
}
