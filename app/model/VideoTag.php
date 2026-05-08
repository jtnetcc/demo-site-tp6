<?php

namespace app\model;

use think\model\Pivot;

class VideoTag extends Pivot
{
    protected $name = 'video_tags';
    protected $pk = ['video_id', 'tag_id'];
    protected $autoWriteTimestamp = false;
    protected $createTime = false;
    protected $updateTime = false;

    protected $type = [
        'video_id' => 'integer',
        'tag_id' => 'integer',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class, 'video_id', 'id');
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class, 'tag_id', 'id');
    }
}
