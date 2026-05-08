<?php

namespace app\model;

use think\Model;

class VideoAsset extends Model
{
    protected $name = 'video_assets';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $type = [
        'id' => 'integer',
        'video_id' => 'integer',
        'size_bytes' => 'integer',
        'duration_sec' => 'integer',
        'resolver_meta' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class, 'video_id', 'id');
    }
}
