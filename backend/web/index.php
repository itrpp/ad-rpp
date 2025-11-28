<?php

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../../common/config/bootstrap.php';
require __DIR__ . '/../config/bootstrap.php';

// โหลดไฟล์ config หลัก (required)
$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/../../common/config/main.php',
    require __DIR__ . '/../config/main.php'
);

// โหลดไฟล์ local config ถ้ามีอยู่ (optional)
$commonConfigMainLocal = __DIR__ . '/../../common/config/main-local.php';
if (file_exists($commonConfigMainLocal)) {
    $config = yii\helpers\ArrayHelper::merge($config, require $commonConfigMainLocal);
}

$backendConfigMainLocal = __DIR__ . '/../config/main-local.php';
if (file_exists($backendConfigMainLocal)) {
    $config = yii\helpers\ArrayHelper::merge($config, require $backendConfigMainLocal);
}

(new yii\web\Application($config))->run();
