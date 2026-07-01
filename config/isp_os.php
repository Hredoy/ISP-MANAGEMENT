<?php

return [
    'modules' => [
        'tenants',
        'domains',
        'users',
        'roles',
        'permissions',
        'isp_profiles',
        'isp_settings',
        'packages',
        'clients',
        'devices',
        'onus',
        'payments',
        'payment_transactions',
        'tickets',
        'faults',
        'network_nodes',
        'notifications',
        'resellers',
        'ftp_accounts',
        'reports',
    ],

    'actions' => ['view', 'create', 'edit', 'delete'],

    'role_permissions' => [
        'Super Admin' => ['*'],
        'Network Engineer' => [
            'devices.*', 'onus.*', 'network_nodes.*', 'faults.*', 'packages.view', 'clients.view',
        ],
        'Billing Manager' => [
            'clients.view', 'packages.view', 'payments.*', 'payment_transactions.*', 'reports.view',
        ],
        'Technician' => [
            'clients.view', 'devices.view', 'onus.view', 'tickets.view', 'tickets.edit', 'faults.view', 'faults.edit',
        ],
        'Reseller' => [
            'clients.view', 'clients.create', 'payments.view', 'resellers.view',
        ],
        'Support Agent' => [
            'clients.view', 'tickets.*', 'faults.view', 'notifications.view',
        ],
        'Client' => [
            'packages.view', 'payments.view', 'tickets.view', 'tickets.create',
        ],
    ],

    'jwt' => [
        'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),
        'ttl_minutes' => (int) env('JWT_TTL_MINUTES', 43200),
        'secret' => env('JWT_SECRET', env('APP_KEY')),
    ],
];
