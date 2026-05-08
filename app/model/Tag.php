<?php

namespace app\model;

use think\Model;

class Tag extends Model
{
    protected $name = 'tags';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $type = [
        'id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function videoTags()
    {
        return $this->hasMany(VideoTag::class, 'tag_id', 'id');
    }

    public function videos()
    {
        return $this->belongsToMany(Video::class, VideoTag::class, 'video_id', 'tag_id');
    }
}
