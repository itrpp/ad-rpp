<?php
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\SiteServiceMenu */
/* @var $title string */

$this->title = $title;
$this->params['breadcrumbs'][] = ['label' => 'จัดการเมนูระบบงาน', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$colors = [
    'card-border-blue' => 'น้ำเงิน (Blue)',
    'card-border-green' => 'เขียว (Green)',
    'card-border-dark-green' => 'เขียวเข้ม (Dark Green)',
    'card-border-red' => 'แดง (Red)',
    'card-border-orange' => 'ส้ม (Orange)',
    'card-border-blue-light' => 'ฟ้าอ่อน (Blue Light)',
    'card-border-indigo' => 'คราม (Indigo)',
    'card-border-lime' => 'เหลืองมะนาว (Lime)',
    'card-border-dark-red' => 'แดงเข้ม (Dark Red)',
    'card-border-teal' => 'เขียวฟ้า (Teal)',
    'card-border-cyan' => 'ฟ้า (Cyan)',
    'card-border-purple' => 'ม่วง (Purple)',
];
$iconBg = [
    'icon-bg-blue' => 'น้ำเงิน',
    'icon-bg-green' => 'เขียว',
    'icon-bg-dark-green' => 'เขียวเข้ม',
    'icon-bg-red' => 'แดง',
    'icon-bg-orange' => 'ส้ม',
    'icon-bg-blue-light' => 'ฟ้าอ่อน',
    'icon-bg-indigo' => 'คราม',
    'icon-bg-lime' => 'เหลืองมะนาว',
    'icon-bg-dark-red' => 'แดงเข้ม',
    'icon-bg-teal' => 'เขียวฟ้า',
    'icon-bg-cyan' => 'ฟ้า',
    'icon-bg-purple' => 'ม่วง',
];
?>

<div class="card">
    <div class="card-header"><h4 class="card-title mb-0"><?= Html::encode($this->title) ?></h4></div>
    <div class="card-body">
        <?php $form = ActiveForm::begin(['id' => 'site-menu-form', 'options' => ['enctype' => 'multipart/form-data']]); ?>
        <div class="row">
            <div class="col-md-6">
                <?= $form->field($model, 'title')->textInput(['maxlength' => true, 'placeholder' => 'เช่น ระบบจัดการความรู้ (KM)']) ?>
                <?= $form->field($model, 'description')->textInput(['maxlength' => true, 'placeholder' => 'คำอธิบายสั้นใต้ชื่อ']) ?>
                <?= $form->field($model, 'url')->textInput(['maxlength' => true, 'placeholder' => 'https://...']) ?>
                <?= $form->field($model, 'imageFile')->fileInput(['accept' => 'image/png,image/jpeg,image/jpg,image/gif,image/webp'])->hint('รองรับ PNG, JPG, GIF, WebP ขนาดไม่เกิน 2 MB') ?>
                <?php if ($model->image_path): ?>
                <div class="mb-2">
                    <label class="form-label small text-muted">รูปปัจจุบัน</label>
                    <div>
                        <img src="<?= Yii::getAlias('@web/img/' . $model->image_path) ?>" alt="" class="img-thumbnail" style="max-height: 120px;">
                        <span class="small text-muted ms-2"><?= htmlspecialchars($model->image_path) ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <?= $form->field($model, 'icon')->dropDownList(\common\models\SiteServiceMenu::iconOptions(), ['prompt' => '-- เลือกไอคอน --']) ?>
                <?= $form->field($model, 'card_color')->dropDownList($colors) ?>
                <?= $form->field($model, 'icon_bg_class')->dropDownList($iconBg) ?>
                <?= $form->field($model, 'sort_order')->textInput(['type' => 'number']) ?>
                <?= $form->field($model, 'is_visible')->checkbox() ?>
                <?= $form->field($model, 'admin_only')->checkbox() ?>
                <?= $form->field($model, 'open_new_tab')->checkbox() ?>
            </div>
        </div>
        <div class="form-group mt-3">
            <?= Html::submitButton('บันทึก', ['class' => 'btn btn-success']) ?>
            <?= Html::a('ยกเลิก', ['index'], ['class' => 'btn btn-secondary']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>
