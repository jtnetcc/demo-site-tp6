<?php

namespace app\service;

use app\model\Comment;
use app\model\Course;
use app\model\Grant;
use app\model\Lesson;
use app\model\User;
use app\model\Video;
use app\model\WatchHistory;

class AdminDashboardService
{
    public function dashboard(): array
    {
        return [
            'stats' => [
                'users' => User::count(),
                'videos' => Video::count(),
                'courses' => Course::count(),
                'lessons' => Lesson::count(),
                'comments' => Comment::count(),
                'grants' => Grant::count(),
                'watchHistories' => WatchHistory::count(),
            ],
            'recentUsers' => User::order('created_at', 'desc')->limit(5)->select(),
            'recentVideos' => Video::with(['category'])->order('created_at', 'desc')->limit(5)->select(),
            'recentCourses' => Course::order('created_at', 'desc')->limit(5)->select(),
            'recentGrants' => Grant::with(['user', 'course', 'grantedByAdmin'])->order('created_at', 'desc')->limit(5)->select(),
        ];
    }
}
