<?php

namespace app\model;

use think\Model;

class Grant extends Model
{
    protected $name = 'grants';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'course_id' => 'integer',
        'granted_by_admin_id' => 'integer',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }

    public function grantedByAdmin()
    {
        return $this->belongsTo(User::class, 'granted_by_admin_id', 'id');
    }
}
