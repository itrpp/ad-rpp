<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \common\models\ProfileForm $model */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;

$this->title = 'Change Password';
$this->params['breadcrumbs'][] = $this->title;

// ตรรกะการเปลี่ยนรหัสผ่านถูกย้ายไปยัง Controller/Model ตามหลัก MVC
?>
<div class="site-change-password">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h1 class="text-center"><?= Html::encode($this->title) ?></h1>
                </div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'change-password-form',
                        'layout' => 'horizontal',
                        'enableClientValidation' => true,
                        'fieldConfig' => [
                            'template' => "{label}\n{input}\n{hint}\n{error}",
                            'labelOptions' => ['class' => 'col-form-label'],
                            'inputOptions' => ['class' => 'form-control'],
                            'errorOptions' => ['class' => 'invalid-feedback'],
                        ],
                    ]); ?>

                    <div class="mb-3">
                        <?= $form->field($model, 'currentPassword')->passwordInput(['placeholder' => 'รหัสผ่านปัจจุบัน'])->label('รหัสผ่านปัจจุบัน') ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'newPassword')->passwordInput(['minlength' => 4, 'placeholder' => 'รหัสผ่านใหม่ (อย่างน้อย 4 ตัวอักษร)'])->label('รหัสผ่านใหม่')->hint('ต้องมีอย่างน้อย 4 ตัวอักษร') ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'confirmPassword')->passwordInput(['placeholder' => 'ยืนยันรหัสผ่านใหม่'])->label('ยืนยันรหัสผ่านใหม่')->hint('กรุณาพิมพ์ให้ตรงกับรหัสผ่านใหม่') ?>
                    </div>

                    <div class="alert alert-info mb-4">
                        <h6 class="alert-heading mb-2"><i class="fas fa-info-circle me-2"></i>การแก้ไขรหัสผ่านจะมีผลกับระบบ</h6>
                        <ul class="mb-0">
                            <li>KM,Pacs,Service ช่าง,internet/WiFi</li>
                    
                            <li>รหัส e-phis ต้องแก้ไขผ่านระบบของ e-phis</li>
                        </ul>
                    </div>

                    <div class="form-group text-center">
                        <?= Html::submitButton('Change Password', ['class' => 'btn btn-primary']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.site-change-password {
    padding: 20px;
}
.card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
.card-header h1 {
    font-size: 24px;
    margin: 0;
    padding: 10px 0;
}
</style> 