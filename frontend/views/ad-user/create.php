<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\web\View;

/* @var $this yii\web\View */
/* @var $model common\models\AdUser */

$this->title = 'ลงทะเบียน';
// $this->params['breadcrumbs'][] = ['label' => 'AD User Management', 'url' => ['index']];


// Register required assets
$this->registerJsFile('@web/js/bootstrap.bundle.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile('@web/css/bootstrap.min.css');
if (class_exists('yii\debug\Module')) {
    $this->off(\yii\web\View::EVENT_END_BODY, [\yii\debug\Module::getInstance(), 'renderToolbar']);
}
?>
<div class="ad-user-create" style="max-width: 1140px; margin: 0 auto;">
    <div class="card shadow-sm rounded-3">
        <div class="card-header">
            <div class="d-flex align-items-center gap-2">
                <img src="<?= Yii::getAlias('@web/favicon.ico') ?>" alt="logo" width="28" height="28" class="me-2">
                <div class="div">
                    <h6>  รหัสจะเปิดใช้งานหลังจาก เอกสารสมัครงานยื่นฝ่ายบริหารงานทั่วไปเรียบร้อยแล้ว
                </h6>
                <div class="text-warning"> รหัส E-phis จะเปิดใช้งานทุกวันเสาร์ ของสัปดาห์ </div>
            </div>
        </div>
        <div class="card-body">
          
     
            <?php $form = ActiveForm::begin([
                'id' => 'create-form',
                'enableAjaxValidation' => true,
                'fieldConfig' => [
                    'template' => "{label}\n{input}\n{error}",
                    'labelOptions' => ['class' => 'control-label'],
                    'errorOptions' => ['class' => 'invalid-feedback'],
                ],
                'options' => ['class' => 'form-horizontal'],
            ]); ?>

            <div class="row">
                <div class="col-md-3">
                    <?= $form->field($model, 'samaccountname', [
                        'options' => ['class' => 'form-group required'],
                    ])->textInput([
                        'maxlength' => true,
                        'class' => 'form-control',
                        'placeholder' => 'Username ที่ใช้ในการเข้าระบบ',
                        'aria-describedby' => 'usernameHelp',
                        'pattern' => '^[a-z0-9]+$',
                        'title' => 'ตัวอักษรภาษาอังกฤษพิมพ์เล็ก (a-z) หรือตัวเลข (0-9)',
                        'oninput' => "this.value = this.value.toLowerCase().replace(/[^a-z0-9]/g, '')"
                    ]) ?>
                    <small id="usernameHelp" class="form-text text-muted">ตัวอักษรภาษาอังกฤษพิมพ์เล็ก หรือตัวเลข</small>
                    <div class="mt-1" id="username-availability" aria-live="polite"></div>
                </div>
                <div class="col-md-1">
                    <!-- Empty column for layout balance -->
                </div>
                <div class="col-md-4">
                    <!-- Empty column for layout balance -->
                </div>
                      <div class="col-md-4">
                    <!-- Empty column for layout balance -->
                </div>
            </div>

            <div class="row">
                <div class="col-md-2">
                    <?= $form->field($model, 'personalTitle', [
                        'options' => ['class' => 'form-group required'],
                    ])->dropDownList([
                   
                        'นาย' => 'นาย',
                        'นาง' => 'นาง',
                        'นางสาว' => 'นางสาว',
                        'นพ.' => 'นพ',
                        'พญ.' => 'พญ.',
                        'พว.' => 'พว.',
                        'ทพ.' => 'ทพ.',
                        'ทพญ.' => 'ทพญ.',
                        'ทนพ.' => 'ทนพ.',
                        'ทพพญ.' => 'ทพพญ.',
                        'ภก.' => 'ภก.',
                        'ภญ.' => 'ภญ.',
                  
                    
                    ], [
                        'class' => 'form-control',
                        'prompt' => '-- คำนำหน้าชื่อ -',
                        'required' => true
                    ]) ?>
                </div>
      

                <div class="col-md-3">
                    <?= $form->field($model, 'username', [
                        'options' => ['class' => 'form-group required'],
                    ])->textInput([
                        'maxlength' => true,
                        'class' => 'form-control',
                        'placeholder' => 'ชื่อ'
                    ]) ?>
                </div>

                     <div class="col-md-3">
                    <?= $form->field($model, 'sername', [
                        'options' => ['class' => 'form-group required'],
                    ])->textInput([
                        'maxlength' => true,
                        'class' => 'form-control',
                        'placeholder' => 'นามสกุล'
                    ]) ?>
                    <div class="mt-1" id="name-availability" aria-live="polite"></div>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'name_en', [
                        'options' => ['class' => 'form-group'],
                    ])->textInput([
                        'maxlength' => true,
                        'class' => 'form-control',
                        'placeholder' => 'firstname - lastname(ไม่บังคับ)'
                    ]) ?>
                </div>


            </div>

       

            <div class="row">
                <div class="col-md-4">
                    <?php
                    // Query Active Directory for all Organizational Units within OU=rpp-user,DC=rpphosp,DC=local
                    $ldap = new \common\components\LdapHelper();
                    $allOus = $ldap->getOrganizationalUnits('OU=rpp-user,DC=rpphosp,DC=local');
                    
                    // Create dropdown options from AD OUs
                    $ouOptions = [];
                    $excludedOus = ['Register-test', 'updateOU', 'test', 'ฝ่ายการพยาบาล', 'ฝ่ายการพยาบาล(Nurse)' ,'rpp-register'];
                    
                    foreach ($allOus as $ou) {
                        if (isset($ou['ou']) && isset($ou['dn'])) {
                            // Skip excluded OU names
                            if (in_array($ou['ou'], $excludedOus)) {
                                continue;
                            }
                            
                            // Use OU name as value (not full DN) for department attribute
                            // Extract clean OU name (remove suffix after dash if exists, e.g., "IT-itdes" -> "IT")
                            $ouName = $ou['ou'];
                            
                            // Always extract clean name first (remove suffix after dash)
                            if (strpos($ouName, '-') !== false) {
                                $parts = explode('-', $ouName);
                                $displayName = trim($parts[0]); // This will be "IT" for "IT-itdes"
                            } else {
                                $displayName = $ouName;
                            }
                            
                            // Add description if available (but don't add if it's just a duplicate of the clean name)
                            if (isset($ou['description']) && !empty($ou['description'])) {
                                $cleanDescription = trim($ou['description']);
                                // Only add description if it's different from the clean display name
                                if ($cleanDescription !== $displayName && !empty($cleanDescription)) {
                                    $displayName .= ' - ' . $cleanDescription;
                                }
                            }
                            
                            // Key is the full OU name from AD (e.g., "IT-itdes"), value is display name (e.g., "IT")
                            $ouOptions[$ou['ou']] = $displayName;
                        }
                    }
                    
                    // If no OUs found, provide a default option
                    if (empty($ouOptions)) {
                        $ouOptions['Default'] = 'Default Department';
                    }
                    ?>
                    <?= $form->field($model, 'department')->dropDownList($ouOptions, [
                        'prompt' => 'เลือกกลุ่มงาน/ฝ่าย',
                        'class' => 'form-control required'
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'title', [
                        'options' => ['class' => 'form-group required'],
                    ])->textInput([
                        'maxlength' => true,
                        'class' => 'form-control',
                        'placeholder' => 'ตำแหน่ง'
                    ]) ?>
                </div>

                <div class="col-md-4">
                    <?= $form->field($model, 'telephone', [
                        'options' => ['class' => 'form-group required'],
                    ])->textInput([
                        'maxlength' => true,
                        'class' => 'form-control',
                        'placeholder' => 'หน่วยงาน หรือ ipphone'
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <!-- Empty column for layout balance -->
                </div>
            </div>

            <!-- New Active Directory fields -->
            <div class="row">
          

                <div class="col-md-4">
                    <!-- Empty column for layout balance -->
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'id_card', [
                        'options' => ['class' => 'form-group required'],
                    ])->textInput([
                        'maxlength' => 13,
                        'class' => 'form-control',
                        'placeholder' => 'เลขบัตรประชาชนจริง 13 หลัก',
                        'id' => 'thai-id-card',
                        'required' => true
                    ])->label('เลขบัตรประชาชน <span class="text-danger">*</span>') ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'company', [
                        'options' => ['class' => 'form-group'],
                    ])->textInput([
                        'maxlength' => true,
                        'class' => 'form-control',
                        'placeholder' => 'ชื่อบริษัท(ชื่อผู้ประสานงาน)'
                    ]) ?>
                </div>
            </div>
            <div class="row">
            <div class="col-md-12">
                    <?= $form->field($model, 'streetaddress', [
                        'options' => ['class' => 'form-group'],
                    ])->textarea([
                        'rows' => 3,
                        'maxlength' => true,
                        'class' => 'form-control',
                        'placeholder' => 'รายละเอียดเพิ่มเติม ในการขอใช้งานระบบ',
                        'aria-describedby' => 'streetaddressHelp'
                    ]) ?>
                    <small class="text-info" id="streetaddressHelp" class="form-text text-muted">User และ รหัสผ่านที่ลงทะเบียนในระบบนี้จะใช้งานได้กับ KM,PACSRPPH5,Internet ในโรงพยาบาล,VPN</small>
                    <div class="mt-1" style="color:#d26a00; font-weight:600;">ส่วนระบบ E-phis จะใช้ user นี้ รหัสผ่านจะเป็นตามที่ admin แจ้ง</div>

            </div>
            

            <div class="row">
                <!-- <div class="col-md-4">
                    <?= $form->field($model, 'ephis_code', [
                        'options' => ['class' => 'form-group'],
                    ])->textInput([
                        'maxlength' => true,
                        'class' => 'form-control',
                        'placeholder' => 'Enter Ephis code(ไม่บังคับ)'
                    ]) ?>
                </div> -->
                <div class="col-md-4">
                    <!-- Empty column for layout balance -->
                </div>
                <div class="col-md-4">
                    <!-- Empty column for layout balance -->
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <?= $form->field($model, 'password', [
                        'options' => ['class' => 'form-group required'],
                    ])->passwordInput([
                        'maxlength' => 50,
                        'minlength' => 4,
                        'class' => 'form-control',
                        'placeholder' => 'password',
                        'aria-describedby' => 'passwordHelp',
                        'data-bs-toggle' => 'tooltip',
                        'title' => 'ความยาว 4-50 ตัวอักษร (อักขระใดก็ได้)'
                    ]) ?>
                    <small id="passwordHelp" class="form-text text-muted">ความยาว 4-50 ตัวอักษร (อักขระใดก็ได้)</small>
                    <div class="mt-2">
                        <div class="progress" style="height: 6px;">
                            <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small id="passwordStrengthText" class="text-muted">Password strength: -</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'confirm_password', [
                        'options' => ['class' => 'form-group required'],
                    ])->passwordInput([
                        'maxlength' => 50,
                        'minlength' => 4,
                        'class' => 'form-control',
                        'placeholder' => 'Confirm password',
                        'aria-describedby' => 'confirmHelp'
                    ]) ?>
                    <small id="confirmHelp" class="form-text text-muted">กรอกรหัสผ่านอีกครั้งเพื่อยืนยัน</small>
                </div>
                
             
            <div class="col-md-4 d-flex justify-content-end align-items-end mt-4">
                <div class="d-flex gap-2">
                    <?= Html::submitButton('<i class="fas fa-user-plus"></i> Register', ['class' => 'btn btn-success']) ?>
                    <?= Html::a('<i class="fas fa-times"></i> Cancel', ['site/index'], ['class' => 'btn btn-default']) ?>
                </div>
            </div>

            <?= Html::hiddenInput('AdUser[target_ou]', $model->target_ou ?: 'OU=Register,OU=rpp-user,DC=rpphosp,DC=local') ?>

            


            <div class="mt-3">
                <small class="text-muted">มีบัญชีอยู่แล้ว? <?= Html::a('เข้าสู่ระบบที่นี่', ['site/login']) ?></small>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>

    <style>
        .field-aduser-samaccountname.required label:after,
        .field-aduser-username.required label:after,
        .field-aduser-sername.required label:after,
        .field-aduser-personaltitle.required label:after,
        .field-aduser-department.required label:after,
        .field-aduser-telephone.required label:after,
        .field-aduser-title.required label:after,
        .field-aduser-id_card.required label:after,
        .field-aduser-password.required label:after,
        .field-aduser-confirm_password.required label:after {
            content: ' *';
            color: #dc3545;
            font-weight: bold;
        }
        .card {
            box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
            margin-bottom: 1rem;
        }
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: .75rem 1.25rem;
            position: relative;
            border-top-left-radius: .25rem;
            border-top-right-radius: .25rem;
        }
        .card-title {
            float: left;
            font-size: 1.1rem;
            font-weight: 400;
            margin: 0;
        }
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: .25rem;
            font-size: 80%;
            color: #dc3545;
        }
        .btn {
            margin-right: 5px;
        }
        .form-control.is-valid {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
    </style>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successModalLabel">แจ้งเตือน</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                        <h4 class="mt-3">เพิ่มผู้ใช้สำเร็จ</h4>
                        <p>บัญชีผู้ใช้ถูกสร้างใน Active Directory พร้อมรหัสผ่านที่กำหนดไว้</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal" onclick="window.location.href='<?= Yii::$app->urlManager->createUrl(['site/index']) ?>'">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <?php
    $this->registerJs("
        $(document).ready(function() {
            // Enable Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle=\"tooltip\"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {return new bootstrap.Tooltip(tooltipTriggerEl)});

            // Username availability check (debounced)
            var usernameTimer = null;
            var checkUrl = '" . Yii::$app->urlManager->createUrl(['ad-user/check-username']) . "';
            $('#aduser-samaccountname').on('input', function(){
                clearTimeout(usernameTimer);
                var val = $(this).val().trim();
                if(!val){
                    $('#username-availability').html('');
                    return;
                }
                usernameTimer = setTimeout(function(){
                    $.get(checkUrl, {username: val}).done(function(res){
                        if(res && res.success){
                            if(res.available){
                                $('#username-availability').html('<span class=\"text-success\">✅ Username is available</span>');
                            }else{
                                $('#username-availability').html('<span class=\"text-danger\">❌ Username is already used</span>');
                            }
                        }
                    });
                }, 400);
            });

            // Password strength meter
            function assessStrength(pw){
                var length = pw.length;
                if(length === 0) return 0;
                if(length < 4) return 1; // Too short (min 4)
                if(length <= 8) return 2; // Fair
                if(length <= 15) return 3; // Good
                return 4; // Strong (16+ characters)
            }
            function updateStrengthUI(score){
                var percent = (score/4)*100;
                var cls = 'bg-danger';
                var label = 'Too Short';
                if(score === 1){cls='bg-danger'; label='Too Short';}
                if(score === 2){cls='bg-warning'; label='Fair';}
                if(score === 3){cls='bg-info'; label='Good';}
                if(score === 4){cls='bg-success'; label='Strong';}
                $('#passwordStrengthBar').css('width', percent + '%').removeClass('bg-danger bg-warning bg-info bg-success').addClass(cls);
                $('#passwordStrengthText').text('Password strength: ' + label);
            }
            $('#aduser-password').on('input', function(){
                updateStrengthUI(assessStrength($(this).val()));
            });

            // Confirm password match
            function validateConfirm(){
                var p1 = $('#aduser-password').val();
                var p2 = $('#aduser-confirm_password').val();
                var errorDiv = $('#aduser-confirm_password').next('.invalid-feedback');
                if(!p2){
                    $('#aduser-confirm_password').removeClass('is-invalid');
                    if(errorDiv.length){ errorDiv.text(''); }
                    return true;
                }
                if(p1 !== p2){
                    $('#aduser-confirm_password').addClass('is-invalid');
                    if(errorDiv.length){ errorDiv.text('รหัสผ่านไม่ตรงกัน'); }
                    return false;
                }
                $('#aduser-confirm_password').removeClass('is-invalid');
                if(errorDiv.length){ errorDiv.text(''); }
                return true;
            }
            $('#aduser-confirm_password, #aduser-password').on('input', validateConfirm);

            // Thai full name duplicate check (debounced)
            var nameTimer = null;
            var nameExists = false;
            var nameUrl = '" . Yii::$app->urlManager->createUrl(['ad-user/check-name']) . "';
            $('#aduser-username, #aduser-sername').on('input', function(){
                clearTimeout(nameTimer);
                var first = $('#aduser-username').val().trim();
                var last = $('#aduser-sername').val().trim();
                if(!first || !last){
                    nameExists = false;
                    $('#name-availability').html('');
                    return;
                }
                nameTimer = setTimeout(function(){
                    $.get(nameUrl, {firstName: first, lastName: last}).done(function(res){
                        if(res && res.success){
                            if(res.exists){
                                nameExists = true;
                                $('#name-availability').html('<span class=\"text-danger\">❌ พบชื่อ-นามสกุลนี้ใน ระบบ แล้ว</span>');
                            } else {
                                nameExists = false;
                                $('#name-availability').html('<span class=\"text-success\">✅ ยังไม่พบชื่อ-นามสกุลนี้ใน AD</span>');
                            }
                        }
                    });
                }, 400);
            });

            // Before submit: validate confirm password and name duplication
            $('#create-form').on('beforeSubmit', function(e) {
                e.preventDefault();
                if(!validateConfirm()){
                    return false;
                }
                if(nameExists){
                    // mark fields invalid and block submit
                    var msg = 'พบชื่อ-นามสกุลนี้ใน ระบบ แล้ว';
                    var u = $('#aduser-username');
                    var s = $('#aduser-sername');
                    u.addClass('is-invalid');
                    s.addClass('is-invalid');
                    if(u.next('.invalid-feedback').length === 0){ $('<div class=\"invalid-feedback\"></div>').insertAfter(u); }
                    if(s.next('.invalid-feedback').length === 0){ $('<div class=\"invalid-feedback\"></div>').insertAfter(s); }
                    u.next('.invalid-feedback').text(msg);
                    s.next('.invalid-feedback').text(msg);
                    return false;
                }

                var form = $(this);
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        if(response.success) {
                            form[0].reset();
                            $('#passwordStrengthBar').css('width','0%').removeClass('bg-danger bg-warning bg-info bg-success');
                            $('#passwordStrengthText').text('Password strength: -');
                            $('#username-availability').html('');
                            $('#name-availability').html('');
                            $('#successModal').modal('show');
                        } else if(response.errors) {
                            $.each(response.errors, function(field, errors) {
                                var input = form.find('[name=\"' + field + '\"]');
                                input.addClass('is-invalid');
                                var errorDiv = input.next('.invalid-feedback');
                                if(errorDiv.length === 0) {
                                    errorDiv = $('<div class=\"invalid-feedback\"></div>').insertAfter(input);
                                }
                                errorDiv.text(errors[0]);
                            });
                        }
                    },
                    error: function() {
                        alert('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
                    }
                });
                return false;
            });

            // Reset button
            $('#resetForm').on('click', function(){
                var form = $('#create-form');
                
                // Reset the form
                form[0].reset();
                
                // Clear all input fields explicitly
                form.find('input[type=text]').val('');
                form.find('input[type=password]').val('');
                form.find('input[type=email]').val('');
                form.find('input[type=tel]').val('');
                form.find('input[type=number]').val('');
                
                // Reset dropdowns to default
                form.find('select').prop('selectedIndex', 0);
                
                // Clear all validation states
                form.find('.is-invalid').removeClass('is-invalid');
                form.find('.is-valid').removeClass('is-valid');
                form.find('.invalid-feedback').text('');
                
                // Reset password strength indicator
                $('#passwordStrengthBar').css('width','0%').removeClass('bg-danger bg-warning bg-info bg-success');
                $('#passwordStrengthText').text('Password strength: -');
                
                // Clear username availability
                $('#username-availability').html('');
                $('#name-availability').html(''); // Clear name availability
                
                // Clear any custom validation states
                form.find('input').removeClass('is-invalid is-valid');
                
                // Focus on first input field
                form.find('input[type=text]').first().focus();
            });

            // Thai ID Card validation
            function validateThaiIdCard(idCard) {
                if (!idCard || idCard.length !== 13) {
                    return false;
                }
                
                // Check if all characters are digits
                var digitRegex = /^[0-9]{13}$/;
                if (!digitRegex.test(idCard)) {
                    return false;
                }
                
                // Thai ID card validation algorithm
                var sum = 0;
                var weights = [13, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2];
                
                for (var i = 0; i < 12; i++) {
                    sum += parseInt(idCard[i]) * weights[i];
                }
                
                var remainder = sum % 11;
                var checkDigit = (11 - remainder) % 10;
                
                return parseInt(idCard[12]) === checkDigit;
            }
            
            $('#thai-id-card').on('input', function() {
                var idCard = $(this).val();
                var errorDiv = $(this).next('.invalid-feedback');
                
                if (!idCard) {
                    $(this).removeClass('is-invalid is-valid');
                    if (errorDiv.length) { errorDiv.text(''); }
                    return;
                }
                
                if (idCard.length < 13) {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                    if (errorDiv.length) { errorDiv.text('กรุณากรอกเลขบัตรประชาชน 13 หลัก'); }
                    return;
                }
                
                var digitRegex = /^[0-9]{13}$/;
                if (!digitRegex.test(idCard)) {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                    if (errorDiv.length) { errorDiv.text('เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก'); }
                    return;
                }
                
                if (validateThaiIdCard(idCard)) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                    if (errorDiv.length) { errorDiv.text(''); }
                } else {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                    if (errorDiv.length) { errorDiv.text('เลขบัตรประชาชนไม่ถูกต้องตามรูปแบบไทย'); }
                }
            });

            // Clear errors on input
            $('input, select').on('input change', function() {
                $(this).removeClass('is-invalid');
                $(this).next('.invalid-feedback').text('');
            });
        });
    ");
    ?>
</div> 