<?php
return [
    'adminEmail' => 'admin@example.com',
    'supportEmail' => 'support@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'user.passwordResetTokenExpire' => 3600,
    'user.passwordMinLength' => 8,
    'ldap' => [
        'server' => 'ldaps://192.168.238.8',
        'admin_dn' => 'ldaprpp@rpphosp.local',
        'admin_password' => 'rpp14641',
        'base_dn' => 'DC=rpphosp,DC=local',
        'base_dn_user' => 'OU=rpp-user,DC=rpphosp,DC=local',
        'base_dn_reg' => 'OU=rpp-register,DC=rpphosp,DC=local',
        'domain' => 'rpphosp.local',
        'port' => 636,
        'version' => 3,
        'referrals' => 0,
        'timeout' => 10,
        'use_ssl' => false,
        'use_tls' => false,
        'debug' => true,
        // Enable authentication across all OUs
        'search_all_ous' => true,
        'allowed_ous' => [], // Empty array means all OUs are allowed
    ],
];