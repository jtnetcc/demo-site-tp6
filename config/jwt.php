<?php

return [
    'secret' => env('JWT_SECRET', ''),
    'issuer' => env('JWT_ISSUER', 'demo-site-tp6'),
    'ttl' => (int) env('JWT_TTL', 86400),
    'algo' => 'HS256',
];
