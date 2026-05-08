<?php

namespace app\model;

use think\Model;

class Category extends Model
{
    protected $name = 'categories';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $type = [
        'id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function videos()
    {
        return $this->hasMany(Video::class, 'category_id', 'id');
    }
}
