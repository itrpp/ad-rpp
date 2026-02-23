<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SiteServiceMenu */

$this->title = 'แก้ไขรายการเมนู';
$this->params['breadcrumbs'][] = ['label' => 'จัดการเมนูระบบงาน', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'แก้ไข';
?>
<div class="site-menu-update">
    <?= $this->render('form', ['model' => $model, 'title' => $this->title]) ?>
</div>
