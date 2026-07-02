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
        'hrm',
        'employees',
        'departments',
        'designations',
        'teams',
        'office_locations',
        'shifts',
        'attendance',
        'leave_management',
        'payroll',
        'employee_documents',
        'role_permissions',
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
        'Tenant Admin' => ['*'],
        'HR Manager' => [
            'hrm.view', 'employees.*', 'departments.*', 'designations.*', 'teams.*',
            'office_locations.*', 'shifts.*', 'attendance.*', 'leave_management.*',
            'employee_documents.*', 'role_permissions.view', 'role_permissions.edit',
        ],
        'Accounts Manager' => [
            'hrm.view', 'employees.view', 'attendance.view', 'leave_management.view',
            'payroll.*', 'payments.*', 'reports.view',
        ],
        'Employee' => [
            'hrm.view', 'employees.view', 'attendance.view', 'leave_management.view',
            'employee_documents.view',
        ],
    ],

    'tenant_default_roles' => ['Tenant Admin', 'HR Manager', 'Accounts Manager', 'Support Agent', 'Employee'],

    'module_permission_map' => [
        'hrm' => [
            'hrm',
            'employees',
            'departments',
            'designations',
            'teams',
            'office_locations',
            'shifts',
            'attendance',
            'leave_management',
            'payroll',
            'employee_documents',
            'role_permissions',
        ],
        'customers' => ['clients'],
        'packages' => ['packages'],
        'billing' => ['payments', 'payment_transactions'],
        'payments' => ['payments', 'payment_transactions'],
        'mikrotik' => ['devices'],
        'olt' => ['onus', 'devices'],
        'support-tickets' => ['tickets'],
        'sms' => ['notifications'],
        'reports' => ['reports'],
        'settings' => ['isp_settings', 'role_permissions'],
    ],

    'jwt' => [
        'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),
        'ttl_minutes' => (int) env('JWT_TTL_MINUTES', 43200),
        'secret' => env('JWT_SECRET', env('APP_KEY')),
    ],
];
