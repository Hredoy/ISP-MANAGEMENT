<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | Defaults to "log" (safe no-op, just writes to the log) until a real
    | broadcaster is configured. To go live with self-hosted Soketi
    | (Pusher-protocol compatible), set BROADCAST_CONNECTION=pusher and fill
    | in the PUSHER or SOKETI env vars below - no code changes needed.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'log'),

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'host' => env('PUSHER_HOST', env('SOKETI_HOST', '127.0.0.1')),
                'port' => env('PUSHER_PORT', env('SOKETI_PORT', 6001)),
                'scheme' => env('PUSHER_SCHEME', 'http'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'http') === 'https',
            ],
            'client_options' => [
                // See: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
