<?php

namespace app\model;

use think\Model;

class SiteSetting extends Model
{
    protected $name = 'site_settings';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $type = [
        'id' => 'integer',
        'base_info' => 'json',
        'header' => 'json',
        'footer' => 'json',
        'seo' => 'json',
        'other' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
