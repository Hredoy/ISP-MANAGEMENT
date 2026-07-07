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

    // Plain-VPS custom domain flow: poll DNS for the CNAME target below, then Certbot + an
    // Nginx server block once it resolves. Mutually independent of the cpanel path above -
    // enable whichever one matches how this install is actually hosted, not both.
    'nginx' => [
        'enabled' => env('NGINX_DOMAIN_AUTO_SSL', false),
        'cname_target' => env('NGINX_DOMAIN_CNAME_TARGET', 'sites.'.env('LANDLORD_DOMAIN', 'yourplatform.com')),
        'certbot_binary' => env('CERTBOT_BINARY', 'certbot'),
        'webroot' => env('CERTBOT_WEBROOT', '/var/www/certbot'),
        'sites_available_path' => env('NGINX_SITES_AVAILABLE_PATH', '/etc/nginx/sites-available'),
        'sites_enabled_path' => env('NGINX_SITES_ENABLED_PATH', '/etc/nginx/sites-enabled'),
        'app_root' => env('NGINX_APP_ROOT', '/var/www/isp-management/public'),
        'reload_command' => env('NGINX_RELOAD_COMMAND', 'systemctl reload nginx'),
    ],
];
