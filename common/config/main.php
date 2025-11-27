<?php
return [
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
    ],

'params' => [
    'ldap' => [
        // 'server' => 'ldap://192.168.238.8:389',
        // 'admin_dn' => 'cn=ldaprpp,OU=rpp-user,DC=rpphosp,DC=local',
        // 'admin_password' => 'rpp14641',
        // 'base_dn' => 'OU=rpp-user,DC=rpphosp,DC=local',
        // 'base_dn_reg' => 'OU=rpp-register,OU=rpp-user,DC=rpphosp,DC=local',
        // 'base_dn_del' => 'OU=rpp-delete,DC=rpphosp,DC=local',
        // 'base_dn_outs' => 'OU=rpp-OutSource,DC=rpphosp,DC=local',
        // 'base_dn_user' => 'OU=rpp-user,DC=rpphosp,DC=local',
    ],
],



    
];