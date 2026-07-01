<?php

return [
    'enabled' => env('ENV_VALIDATION_ENABLED', ! in_array(env('APP_ENV'), ['local', 'testing'], true)),

    'keys' => [
        'APP_KEY',
        'APP_URL',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_DATABASE',
        'DB_USERNAME',
        'CACHE_STORE',
        'QUEUE_CONNECTION',
        'SESSION_DRIVER',
        'JWT_SECRET',
    ],

    'production_keys' => [
        'AWS_ACCESS_KEY_ID',
        'AWS_SECRET_ACCESS_KEY',
        'AWS_BUCKET',
        'AWS_ENDPOINT',
    ],
];
