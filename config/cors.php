<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter(explode(',', env('FRONTEND_URL', 'http://localhost:4200'))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 0,
    // Dùng Bearer token nên không cần cookie
    'supports_credentials' => false,
];
