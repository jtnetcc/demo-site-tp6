<?php

namespace app\service;

use app\model\Category;
use app\model\Course;
use app\model\Video;
use app\model\User;

class HomePageService
{
    public function data(?User $user = null): array
    {
        return [
            'currentUser' => $user,
            'categories' => Category::order('created_at', 'desc')->limit(12)->select(),
            'latestVideos' => Video::with(['category'])->where('status', 'PUBLISHED')->order('created_at', 'desc')->limit(4)->select(),
            'popularVideos' => Video::with(['category'])->where('status', 'PUBLISHED')->order('play_count', 'desc')->order('created_at', 'desc')->limit(4)->select(),
            'latestCourses' => Course::where('status', 'PUBLISHED')->order('created_at', 'desc')->limit(4)->select(),
        ];
    }
}
