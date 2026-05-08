<?php

return [
    'default' => 'file',
    'channels' => [
        'file' => [
            'type' => 'File',
            'path' => runtime_path('log'),
            'single' => false,
            'apart_level' => [],
            'max_files' => 0,
            'json' => false,
            'processor' => null,
            'close' => false,
        ],
    ],
];
