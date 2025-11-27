<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-frontend',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'frontend\controllers',
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-frontend',
        ],
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-frontend', 'httpOnly' => true],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the frontend
            'name' => 'advanced-frontend',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'ldap' => [
            'class' => 'common\components\LdapHelper',
        ],
        'authManager' => [
            'class' => 'yii\rbac\DbManager',
            'db' => 'db',
            'itemTable' => 'auth_item',
            'itemChildTable' => 'auth_item_child',
            'assignmentTable' => 'auth_assignment',
            'ruleTable' => 'auth_rule',
        ],
        
        // 'urlManager' => [
        //     'enablePrettyUrl' => true,
        //     'showScriptName' => false,
        //     'rules' => [
        //     ],
        // ],
        

// 'urlManager' => [
//     'enablePrettyUrl' => true,
//     'showScriptName' => false,
//     'rules' => [
//         'ldap-user' => 'ldap-user/index',
//         'ldap-user/create' => 'ldap-user/create',
//         'ldap-user/update/<cn>' => 'ldap-user/update',
//         'ldap-user/delete/<cn>' => 'ldap-user/delete',
//         'ldap-user/view/<cn>' => 'ldap-user/view',
//     ],
// ],

    ],
    'params' => $params,
];
