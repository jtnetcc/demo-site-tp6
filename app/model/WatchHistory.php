<?php

namespace app\model;

use think\Model;

class WatchHistory extends Model
{
    protected $name = 'watch_histories';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
    protected $createTime = false;
    protected $updateTime = false;

    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'video_id' => 'integer',
        'last_position_sec' => 'integer',
        'progress_sec' => 'integer',
        'watched_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function video()
    {
        return $this->belongsTo(Video::class, 'video_id', 'id');
    }
}
