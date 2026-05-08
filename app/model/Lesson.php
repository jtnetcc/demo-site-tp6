<?php

namespace app\model;

use think\Model;

class Lesson extends Model
{
    protected $name = 'lessons';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $type = [
        'id' => 'integer',
        'course_id' => 'integer',
        'duration_sec' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }

    public function importTasks()
    {
        return $this->hasMany(ImportTask::class, 'lesson_id', 'id');
    }
}
