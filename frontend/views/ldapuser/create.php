<?php
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\components\LdapHelper;

$this->title = 'สร้างผู้ใช้งานใหม่';
$this->params['breadcrumbs'][] = ['label' => 'ผู้ใช้งาน', 'url' => ['ou-user']];
$this->params['breadcrumbs'][] = 'สร้างใหม่';

// Get available OUs
$ldap = new LdapHelper();
$ous = $ldap->getOrganizationalUnits();
?>

<div class="ldapuser-create">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if (Yii::$app->session->hasFlash('success')): ?>
                <div class="alert alert-success">
                    <?= Yii::$app->session->getFlash('success') ?>
                </div>
            <?php endif; ?>

            <?php if (Yii::$app->session->hasFlash('error')): ?>
                <div class="alert alert-danger">
                    <?= Yii::$app->session->getFlash('error') ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h1 class="text-center"><?= Html::encode($this->title) ?></h1>
                </div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'create-user-form',
                        'enableAjaxValidation' => false,
                        'enableClientValidation' => true,
                        'options' => ['data-pjax' => true],
                        'fieldConfig' => [
                            'template' => "{label}\n{input}\n{error}",
                            'labelOptions' => ['class' => 'col-form-label'],
                            'inputOptions' => ['class' => 'form-control'],
                            'errorOptions' => ['class' => 'invalid-feedback'],
                        ],
                    ]); ?>

                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <div class="profile-image">
                                <i class="fas fa-user-plus fa-6x text-primary"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <?= $form->field($model, 'sAMAccountName')->textInput(['maxlength' => true])->label('ชื่อผู้ใช้งาน (SAM Account)') ?>
                            </div>
                            <div class="mb-3">
                                <?= $form->field($model, 'displayName')->textInput(['maxlength' => true])->label('ชื่อที่แสดง') ?>
                            </div>
                            <div class="mb-3">
                                <?= $form->field($model, 'department')->textInput(['maxlength' => true])->label('แผนก') ?>
                            </div>
                            <div class="mb-3">
                                <?= $form->field($model, 'mail')->textInput(['maxlength' => true])->label('อีเมล') ?>
                            </div>
                            <div class="mb-3">
                                <?= $form->field($model, 'telephoneNumber')->textInput(['maxlength' => true])->label('เบอร์โทรศัพท์') ?>
                            </div>
                            <?= Html::activeHiddenInput($model, 'organizationalUnit', ['value' => 'Register']) ?>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <?= $form->field($model, 'newPassword')->passwordInput(['maxlength' => true])->label('รหัสผ่าน') ?>
                                <small class="form-text text-muted">
                                    รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร ประกอบด้วย:@Rr12345678
                                    <ul>
                                        <li>ตัวอักษรพิมพ์ใหญ่ 1 ตัว</li>
                                        <li>ตัวอักษรพิมพ์เล็ก 1 ตัว</li>
                                        <li>ตัวเลข 1 ตัว</li>
                                        <li>อักขระพิเศษ 1 ตัว (@$!%*?&)</li>
                                    </ul>
                                </small>
                            </div>
                            <div class="mb-3">
                                <?= $form->field($model, 'confirmPassword')->passwordInput(['maxlength' => true])->label('ยืนยันรหัสผ่าน') ?>
                            </div>
                        </div>
                    </div>

                    <?= Html::activeHiddenInput($model, 'cn') ?>
                    <?= Html::activeHiddenInput($model, 'password') ?>

                    <div class="form-group text-center">
                        <?= Html::submitButton('<i class="fas fa-save me-2"></i>บันทึก', ['class' => 'btn btn-primary']) ?>
                        <?= Html::a('<i class="fas fa-times me-2"></i>ยกเลิก', ['ou-user'], ['class' => 'btn btn-default']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.ldapuser-create {
    padding: 20px;
}
.card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
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
.card-body {
    padding: 20px;
}
.profile-image {
    margin-bottom: 20px;
}
.form-group {
    margin-bottom: 1rem;
}
.btn {
    padding: 8px 16px;
    font-weight: 500;
    border-radius: 4px;
    transition: all 0.3s ease;
    margin: 0 5px;
}
.btn-primary {
    background-color: #007bff;
    border-color: #007bff;
}
.btn-primary:hover {
    background-color: #0056b3;
    border-color: #0056b3;
}
.btn-default {
    background-color: #f8f9fa;
    border-color: #ced4da;
    color: #2c3e50;
}
.btn-default:hover {
    background-color: #e9ecef;
    border-color: #ced4da;
}
.form-control {
    border-radius: 4px;
    border: 1px solid #ced4da;
    padding: 8px 12px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
.invalid-feedback {
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 5px;
}
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}
.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}
.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add password validation
    var password = document.getElementById('ldapuser-newpassword');
    var confirmPassword = document.getElementById('ldapuser-confirmpassword');
    var passwordHidden = document.getElementById('ldapuser-password');
    
    function validatePassword() {
        // Password requirements
        var hasUpperCase = /[A-Z]/.test(password.value);
        var hasLowerCase = /[a-z]/.test(password.value);
        var hasNumbers = /\d/.test(password.value);
        var hasSpecialChar = /[@$!%*?&]/.test(password.value);
        var isLongEnough = password.value.length >= 8;
        
        // Check if passwords match
        if (password.value != confirmPassword.value) {
            confirmPassword.setCustomValidity("รหัสผ่านไม่ตรงกัน");
        } else {
            confirmPassword.setCustomValidity('');
        }
        
        // Check password requirements
        if (!isLongEnough) {
            password.setCustomValidity("รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร");
        } else if (!hasUpperCase) {
            password.setCustomValidity("รหัสผ่านต้องมีตัวอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว");
        } else if (!hasLowerCase) {
            password.setCustomValidity("รหัสผ่านต้องมีตัวอักษรพิมพ์เล็กอย่างน้อย 1 ตัว");
        } else if (!hasNumbers) {
            password.setCustomValidity("รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว");
        } else if (!hasSpecialChar) {
            password.setCustomValidity("รหัสผ่านต้องมีอักขระพิเศษอย่างน้อย 1 ตัว (@$!%*?&)");
        } else {
            password.setCustomValidity('');
            // Set the hidden password field only if all requirements are met
            passwordHidden.value = password.value;
        }
    }
    
    // Set CN from sAMAccountName
    var samAccountName = document.getElementById('ldapuser-samaccountname');
    var cnHidden = document.getElementById('ldapuser-cn');
    
    samAccountName.addEventListener('change', function() {
        cnHidden.value = this.value;
    });
    
    password.addEventListener('input', validatePassword);
    password.addEventListener('change', validatePassword);
    confirmPassword.addEventListener('input', validatePassword);
    confirmPassword.addEventListener('keyup', validatePassword);
});
</script>
