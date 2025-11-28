<?php
/**
 * RBAC Items Configuration
 * This file contains all roles and permissions for the system
 */

return [
    // Roles
    'admin' => [
        'type' => 1, // Role
        'description' => 'Administrator',
        'ruleName' => null,
        'data' => null,
        'createdAt' => time(),
        'updatedAt' => time(),
    ],
    'user' => [
        'type' => 1, // Role
        'description' => 'Regular User',
        'ruleName' => null,
        'data' => null,
        'createdAt' => time(),
        'updatedAt' => time(),
    ],
    'guest' => [
        'type' => 1, // Role
        'description' => 'Guest User',
        'ruleName' => null,
        'data' => null,
        'createdAt' => time(),
        'updatedAt' => time(),
    ],

    // Permissions
    'adUserView' => [
        'type' => 2, // Permission
        'description' => 'View AD Users',
        'ruleName' => null,
        'data' => null,
        'createdAt' => time(),
        'updatedAt' => time(),
    ],
    'adUserCreate' => [
        'type' => 2, // Permission
        'description' => 'Create AD Users',
        'ruleName' => null,
        'data' => null,
        'createdAt' => time(),
        'updatedAt' => time(),
    ],
    'adUserUpdate' => [
        'type' => 2, // Permission
        'description' => 'Update AD Users',
        'ruleName' => null,
        'data' => null,
        'createdAt' => time(),
        'updatedAt' => time(),
    ],
    'adUserDelete' => [
        'type' => 2, // Permission
        'description' => 'Delete AD Users',
        'ruleName' => null,
        'data' => null,
        'createdAt' => time(),
        'updatedAt' => time(),
    ],
    'ldapUserView' => [
        'type' => 2, // Permission
        'description' => 'View LDAP Users',
        'ruleName' => null,
        'data' => null,
        'createdAt' => time(),
        'updatedAt' => time(),
    ],
    'ldapUserCreate' => [
        'type' => 2, // Permission
        'description' => 'Create LDAP Users',
        'ruleName' => null,
        'data' => null,
        'createdAt' => time(),
        'updatedAt' => time(),
    ],
    'ldapUserUpdate' => [
        'type' => 2, // Permission
        'description' => 'Update LDAP Users',
        'ruleName' => null,
        'data' => null,
        'createdAt' => time(),
        'updatedAt' => time(),
    ],
    'ldapUserDelete' => [
        'type' => 2, // Permission
        'description' => 'Delete LDAP Users',
        'ruleName' => null,
        'data' => null,
        'createdAt' => time(),
        'updatedAt' => time(),
    ],
];