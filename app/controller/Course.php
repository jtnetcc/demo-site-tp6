<?php

namespace app\controller;

use app\service\CourseService;
use RuntimeException;
use think\Request;

class Course extends WebController
{
    public function index(Request $request)
    {
        $filters = $request->get();
        $data = $this->viewData([
            'courses' => (new CourseService())->list($filters),
            'filters' => $filters,
        ]);
        $this->clearFlash();

        return view('course/index', $data);
    }

    public function read(Request $request, int $id)
    {
        try {
            $data = (new CourseService())->detailData($id, $this->currentUser());
            $data = $this->viewData($data);
            $this->clearFlash();
            return view('course/detail', $data);
        } catch (RuntimeException $e) {
            abort($e->getCode() ?: 404, $e->getMessage());
        }
    }
}
