<?php
/**
 * RBAC Assignments Configuration
 * This file contains role-permission assignments
 */

return [
    // Admin role gets all permissions
    'admin' => [
        'adUserView' => [
            'userId' => 'admin',
            'itemName' => 'adUserView',
            'createdAt' => time(),
        ],
        'adUserCreate' => [
            'userId' => 'admin',
            'itemName' => 'adUserCreate',
            'createdAt' => time(),
        ],
        'adUserUpdate' => [
            'userId' => 'admin',
            'itemName' => 'adUserUpdate',
            'createdAt' => time(),
        ],
        'adUserDelete' => [
            'userId' => 'admin',
            'itemName' => 'adUserDelete',
            'createdAt' => time(),
        ],
        'ldapUserView' => [
            'userId' => 'admin',
            'itemName' => 'ldapUserView',
            'createdAt' => time(),
        ],
        'ldapUserCreate' => [
            'userId' => 'admin',
            'itemName' => 'ldapUserCreate',
            'createdAt' => time(),
        ],
        'ldapUserUpdate' => [
            'userId' => 'admin',
            'itemName' => 'ldapUserUpdate',
            'createdAt' => time(),
        ],
        'ldapUserDelete' => [
            'userId' => 'admin',
            'itemName' => 'ldapUserDelete',
            'createdAt' => time(),
        ],
    ],

    // User role gets view permissions only
    'user' => [
        'adUserView' => [
            'userId' => 'user',
            'itemName' => 'adUserView',
            'createdAt' => time(),
        ],
        'ldapUserView' => [
            'userId' => 'user',
            'itemName' => 'ldapUserView',
            'createdAt' => time(),
        ],
    ],

    // Guest role has no permissions
    'guest' => [],
];