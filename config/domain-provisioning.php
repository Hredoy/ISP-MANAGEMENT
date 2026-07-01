<?php

return [
    'local' => [
        'enabled' => env('LOCAL_DOMAIN_AUTO_ADD', env('APP_ENV') === 'local'),
        'ip' => env('LOCAL_DOMAIN_IP', '127.0.0.1'),
        'hosts_path' => env('LOCAL_HOSTS_PATH') ?: (PHP_OS_FAMILY === 'Windows'
            ? 'C:\Windows\System32\drivers\etc\hosts'
            : '/etc/hosts'),
    ],

    'cpanel' => [
        'enabled' => env('CPANEL_DOMAIN_AUTO_ADD', false),
        'host' => env('CPANEL_HOST'),
        'port' => env('CPANEL_PORT', 2083),
        'username' => env('CPANEL_USERNAME'),
        'token' => env('CPANEL_TOKEN'),
        'document_root' => env('CPANEL_DOCUMENT_ROOT', 'public_html'),
        'ssl_verify' => env('CPANEL_SSL_VERIFY', true),
    ],
];
