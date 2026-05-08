<?php

namespace app\model;

use think\Model;

class Course extends Model
{
    protected $name = 'courses';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $type = [
        'id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'course_id', 'id');
    }

    public function grants()
    {
        return $this->hasMany(Grant::class, 'course_id', 'id');
    }

    public function importTasks()
    {
        return $this->hasMany(ImportTask::class, 'course_id', 'id');
    }
}
