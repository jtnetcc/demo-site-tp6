<?php

namespace app\model;

use think\Model;

class User extends Model
{
    protected $name = 'users';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $type = [
        'id' => 'integer',
        'valid_until' => 'datetime',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'password_hash',
        'activation_token',
    ];

    public function grants()
    {
        return $this->hasMany(Grant::class, 'user_id', 'id');
    }

    public function createdGrants()
    {
        return $this->hasMany(Grant::class, 'granted_by_admin_id', 'id');
    }

    public function importTasks()
    {
        return $this->hasMany(ImportTask::class, 'created_by_admin_id', 'id');
    }

    public function createdVideos()
    {
        return $this->hasMany(Video::class, 'created_by_id', 'id');
    }

    public function watchHistories()
    {
        return $this->hasMany(WatchHistory::class, 'user_id', 'id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'user_id', 'id');
    }

    public function likes()
    {
        return $this->hasMany(VideoLike::class, 'user_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id', 'id');
    }
}
