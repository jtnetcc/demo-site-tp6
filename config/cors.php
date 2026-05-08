<?php

return [
    'allow_origin' => ['*'],
    'allow_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allow_headers' => ['Authorization', 'Content-Type', 'X-Requested-With'],
    'expose_headers' => [],
    'max_age' => 86400,
];
