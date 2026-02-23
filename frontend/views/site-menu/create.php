<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SiteServiceMenu */

$this->title = 'เพิ่มรายการเมนู';
$this->params['breadcrumbs'][] = ['label' => 'จัดการเมนูระบบงาน', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="site-menu-create">
    <?= $this->render('form', ['model' => $model, 'title' => $this->title]) ?>
</div>
