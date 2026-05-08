<?php

namespace app\model;

use think\Model;

class Video extends Model
{
    protected $name = 'videos';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $type = [
        'id' => 'integer',
        'category_id' => 'integer',
        'created_by_id' => 'integer',
        'allow_comments' => 'boolean',
        'play_count' => 'integer',
        'valid_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id', 'id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, VideoTag::class, 'tag_id', 'video_id');
    }

    public function videoTags()
    {
        return $this->hasMany(VideoTag::class, 'video_id', 'id');
    }

    public function assets()
    {
        return $this->hasMany(VideoAsset::class, 'video_id', 'id');
    }

    public function watchHistories()
    {
        return $this->hasMany(WatchHistory::class, 'video_id', 'id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'video_id', 'id');
    }

    public function likes()
    {
        return $this->hasMany(VideoLike::class, 'video_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'video_id', 'id');
    }

    public function importTasks()
    {
        return $this->hasMany(ImportTask::class, 'video_id', 'id');
    }
}
