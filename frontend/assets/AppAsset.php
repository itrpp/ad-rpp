<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Main frontend application asset bundle.
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/site.css',
        'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
        'https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css',
        'https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback',
    ];
    public $js = [
        'https://code.jquery.com/jquery-3.6.0.min.js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js',
        'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset',
        'yii\widgets\ActiveFormAsset',
    ];
}
