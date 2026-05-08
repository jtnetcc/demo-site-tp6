<?php

namespace app\service;

use app\model\Course;
use app\model\User;
use app\model\Video;

class AdminOptionService
{
    public function users()
    {
        return User::order('created_at', 'desc')->select();
    }

    public function courses()
    {
        return Course::order('created_at', 'desc')->select();
    }

    public function videos()
    {
        return Video::order('created_at', 'desc')->select();
    }
}
