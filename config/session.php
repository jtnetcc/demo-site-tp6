<?php

return [
    'type' => 'file',
    'name' => 'TPSESSID',
    'path' => runtime_path('session'),
    'expire' => 86400,
    'prefix' => '',
    'auto_start' => true,
    'var_session_id' => '',
];
