<?php
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\components\LdapHelper;

$this->title = 'Update User: ' . $model->cn;
$this->params['breadcrumbs'][] = ['label' => 'OU Users', 'url' => ['ou-user']];
$this->params['breadcrumbs'][] = 'Update';

// Get available OUs
$ldap = new LdapHelper();
$ous = $ldap->getOrganizationalUnits();

// Get OU options for department dropdown
$ouOptions = [];
$allOus = $ldap->getOrganizationalUnits('OU=rpp-user,DC=rpphosp,DC=local');

// Create dropdown options from AD OUs
$excludedOus = ['Register-test', 'updateOU', 'test', 'ฝ่ายการพยาบาล', 'ฝ่ายการพยาบาล(Nurse)', 'rpp-register'];

foreach ($allOus as $ou) {
    if (isset($ou['ou']) && isset($ou['dn'])) {
        // Skip excluded OU names
        if (in_array($ou['ou'], $excludedOus)) {
            continue;
        }
        
        // Use OU name as value for department attribute
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

// Normalize model's department value to match dropdown key if it contains dash
// This ensures that if model->department = "IT-itdes", it will match the dropdown key "IT-itdes"
if (isset($model->department) && !empty($model->department)) {
    $currentDepartment = $model->department;
    // If the department value doesn't match any key in $ouOptions,
    // try to find matching key by checking if any key starts with the department value
    if (!isset($ouOptions[$currentDepartment])) {
        // Find matching OU key by checking if department value is a prefix of any OU name
        foreach ($ouOptions as $ouKey => $ouDisplay) {
            $cleanOuName = $ouKey;
            if (strpos($ouKey, '-') !== false) {
                $parts = explode('-', $ouKey);
                $cleanOuName = trim($parts[0]);
            }
            // If current department matches clean OU name, update model department to match the key
            if ($currentDepartment === $cleanOuName || $currentDepartment === $ouKey) {
                $model->department = $ouKey;
                break;
            }
        }
    }
}

// If no OUs found, provide a default option
if (empty($ouOptions)) {
    $ouOptions['Default'] = 'Default Department';
}

// Debug information
Yii::debug("Available OUs: " . print_r($ous, true));
Yii::debug("Department Options: " . print_r($ouOptions, true));
?>

<div class="ldapuser-update">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <?php if (Yii::$app->session->hasFlash('success')): ?>
                <div class="alert alert-success">
                    <?= Yii::$app->session->getFlash('success') ?>
                </div>
            <?php endif; ?>

            <!-- Success Modal -->
            <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="successModalLabel">
                                <i class="fas fa-check-circle me-2"></i>อัปเดตสำเร็จ
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-success">
                                <i class="fas fa-check me-2"></i>
                                <strong>อัปเดตข้อมูลผู้ใช้สำเร็จ!</strong>
                            </div>
                            <p class="mb-0">ข้อมูลผู้ใช้ได้รับการอัปเดตเรียบร้อยแล้ว</p>
                        </div>
                        <div class="modal-footer">
                            <button id="successModalOkBtn" type="button" class="btn btn-success" data-bs-dismiss="modal">
                                <i class="fas fa-check me-1"></i>ตกลง
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Validation Warning Modal -->
            <div class="modal fade" id="validationModal" tabindex="-1" aria-labelledby="validationModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title" id="validationModalLabel">
                                <i class="fas fa-exclamation-triangle me-2"></i>กรุณาตรวจสอบข้อมูล
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <strong>พบฟิลด์ที่ยังไม่ได้กรอกข้อมูล:</strong>
                                <ul id="emptyFieldsList" class="mb-0 mt-2"></ul>
                            </div>
                            <p>กรุณากรอกข้อมูลให้ครบถ้วนก่อนทำการอัปเดต</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                            <button type="button" class="btn btn-warning" onclick="focusFirstEmptyField()">ไปยังฟิลด์ที่ว่าง</button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (Yii::$app->session->hasFlash('success')): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        successModal.show();
                    });
                </script>
            <?php endif; ?>

            <?php if (Yii::$app->session->hasFlash('error')): ?>
                <div class="alert alert-danger">
                    <?= Yii::$app->session->getFlash('error') ?>
                </div>
            <?php endif; ?>


            <div class="card">
                <div class="card-header">
                    <h1 class="text-center"><?= Html::encode($this->title) ?></h1>
                    <div class="text-center text-muted">
                        <small>
                            <i class="fas fa-user-edit me-1"></i>
                            ผู้แก้ไข: <?= Html::encode(Yii::$app->session->get('ldapUserData')['displayname'] ?? Yii::$app->session->get('ldapUserData')['samaccountname'] ?? 'Unknown') ?>
                            <br>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                               
                            </small>
                            วันที่-เวลาที่แก้ไข:
                            <i class="fas fa-clock me-1"></i>
                            <?= date('d/m/Y H:i:s') ?>
                        </small>
                    </div>
                    <div class="text-end mt-2">
                        <?= Html::a('<i class="fas fa-exchange-alt me-1"></i> Move OU', ['move', 'cn' => $model->cn], [
                            'class' => 'btn btn-warning btn-sm me-1',
                            'title' => 'Move user to another OU',
                        ]) ?>
                        <?= Html::a('<i class="fas fa-list me-1"></i> ดูรายละเอียดทั้งหมด', ['view', 'cn' => $model->cn], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'update-user-form',
                        'enableAjaxValidation' => false,
                        'enableClientValidation' => true,
                        'options' => ['data-pjax' => true, 'onsubmit' => 'return validateForm()'],
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
                                <i class="fas fa-user-circle fa-6x text-primary"></i>
                            </div>
                            <div class="mt-3 p-3 bg-light border rounded-3 shadow-sm fs-5">
                                <h6 class="text-muted mb-2"><i class="fas fa-id-card me-2"></i>ข้อมูลปัจจุบัน</h6>
                                <div class="text-start">
                                    <?php 
                                    // Helper to display clean OU name (e.g., "IT-itdes" -> "IT") for read-only sections
                                    $displayDepartment = $model->department ?? '';
                                    if (!empty($displayDepartment) && strpos($displayDepartment, '-') !== false) {
                                        $displayDepartment = trim(explode('-', $displayDepartment)[0]);
                                    }
                                    ?>
                                    <small class="d-block"><strong><i class="fas fa-user me-1"></i>Username:</strong> <?= Html::encode($model->sAMAccountName?: 'ยังไม่ระบุ') ?></small>
                                    <small class="d-block"><strong><i class="fas fa-signature me-1"></i>Display Name:</strong> <?= Html::encode($model->personalTitle?: '') ?> <?= Html::encode($model->displayName?: 'ยังไม่ระบุ') ?></small>
                                    <small class="d-block"><strong><i class="fas fa-sitemap me-1"></i>Department:</strong> <?= Html::encode($displayDepartment ?: 'ยังไม่ระบุ') ?></small>
                                    <small class="d-block"><strong><i class="fas fa-briefcase me-1"></i>ตำแหน่ง:</strong> <?= Html::encode($model->title?: 'ยังไม่ระบุ') ?></small>
                                    <small class="d-block"><strong><i class="fas fa-envelope me-1"></i>Email:</strong> <?= Html::encode($model->mail ?: 'ยังไม่ระบุ') ?></small>
                                    <small class="d-block"><strong><i class="fas fa-hospital me-1"></i>เลขระบบ E-phis:</strong> <?= Html::encode($model->physicalDeliveryOfficeName ?: 'ยังไม่ระบุ') ?></small>
                                    
                            <?php
                            $whenCreatedThai = 'ยังไม่ระบุ';
                            if (!empty($model->whenCreated) && preg_match('/^(\d{8})(\d{6})/', (string)$model->whenCreated, $m)) {
                                $dt = \DateTime::createFromFormat('YmdHis', $m[1] . $m[2], new \DateTimeZone('UTC'));
                                if ($dt !== false) {
                                    $dt->setTimezone(new \DateTimeZone('Asia/Bangkok'));
                                    $thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                                    $d = (int)$dt->format('j');
                                    $mIndex = (int)$dt->format('n');
                                    $y = (int)$dt->format('Y') + 543;
                                    $time = $dt->format('H:i');
                                    $whenCreatedThai = $d . ' ' . $thaiMonths[$mIndex - 1] . ' ' . $y . ' ' . $time . ' น.';
                                }
                            }

                            $whenChangedThai = 'ยังไม่ระบุ';
                            if (!empty($model->whenChanged) && preg_match('/^(\d{8})(\d{6})/', (string)$model->whenChanged, $m2)) {
                                $dt2 = \DateTime::createFromFormat('YmdHis', $m2[1] . $m2[2], new \DateTimeZone('UTC'));
                                if ($dt2 !== false) {
                                    $dt2->setTimezone(new \DateTimeZone('Asia/Bangkok'));
                                    $thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                                    $d2 = (int)$dt2->format('j');
                                    $mIndex2 = (int)$dt2->format('n');
                                    $y2 = (int)$dt2->format('Y') + 543;
                                    $time2 = $dt2->format('H:i');
                                    $whenChangedThai = $d2 . ' ' . $thaiMonths[$mIndex2 - 1] . ' ' . $y2 . ' ' . $time2 . ' น.';
                                }
                            }
                            ?>
                            <small class="d-block"><strong>วันที่สร้างบัญชี:</strong> <?= Html::encode($whenCreatedThai) ?></small>
                            <small class="d-block"><strong>วันที่แก้ไข:</strong> <?= Html::encode($whenChangedThai) ?></small>
                                    
                                    <small class="d-block"><strong>ผู้ติดต่อ(บริษัท):</strong> <?= Html::encode($model->company?: 'ยังไม่ระบุ') ?></small>
                                    <small class="d-block"><strong>รายละเอียด :</strong> <?= Html::encode($model->streetaddress?: 'ยังไม่ระบุ') ?></small>

                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <?= $form->field($model, 'cn')->textInput(['readonly' => true, 'class' => 'form-control bg-light'])->label('CN') ?>
                            </div>
                            <div class="row g-3 fs-5">
                                <div class="col-md-6">
                                    <?= $form->field($model, 'sAMAccountName')
                                        ->textInput(['maxlength' => true, 'required' => true, 'placeholder' => 'เช่น user123'])
                                        ->label('Username <span class="text-warning">(มีผลกับผู้ใช้ที่เคยมีประวัติ KM)</span><span class="text-danger">*</span>') ?>
                                </div>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'displayName')
                                        ->textInput(['maxlength' => true, 'required' => true, 'placeholder' => 'ชื่อที่แสดง'])
                                        ->label('Display Name <span class="text-danger">*</span>') ?>
                                </div>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'department')
                                        ->dropDownList($ouOptions, [
                                            'prompt' => 'เลือกกลุ่มงาน/ฝ่าย',
                                            'class' => 'form-control',
                                            'required' => true
                                        ])->label('Department <span class="text-danger">*</span>') ?>
                                </div>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'title')
                                        ->textInput(['maxlength' => true, 'required' => true, 'placeholder' => 'ตำแหน่งงาน'])
                                        ->label('ตำแหน่ง <span class="text-danger">*</span>') ?>
                                </div>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'mail')
                                        ->textInput(['maxlength' => true, 'placeholder' => 'someone@example.com'])
                                        ->label('Email') ?>
                                </div>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'physicalDeliveryOfficeName')
                                        ->textInput(['maxlength' => true, 'placeholder' => 'เลขระบบ E-phis (ถ้ามี)'])
                                        ->label('เลขระบบ E-phis (ถ้ามี) ') ?>
                                </div>
                                <div class="col-12">
                                    <?= $form->field($model, 'telephoneNumber')
                                        ->textInput(['class' => 'form-control', 'placeholder' => 'ยังไม่ระบุ'])
                                        ->label('Telephone Number') ?>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                    <hr>

                    <!-- Group Assignment Section -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-3">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0 d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-users-cog me-2"></i>การกำหนดกลุ่ม (Group Assignment)</span>
                                        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#groupAssignmentSection" aria-expanded="false" aria-controls="groupAssignmentSection">
                                            <i class="fas fa-chevron-down me-1"></i>แสดง/ซ่อน
                                        </button>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div id="groupAssignmentSection" class="collapse">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-plus-circle fa-sm me-2"></i>เพิ่มผู้ใช้เข้าไปในกลุ่ม</h6>
                                                <div class="mb-2">
                                                    <select class="form-select" id="availableGroupsSelect">
                                                        <option value="">-- เลือกกลุ่ม --</option>
                                                    </select>
                                                </div>
                                                <button type="button" class="btn btn-primary btn-sm" id="btnAddToGroup">
                                                    <i class="fas fa-user-plus me-1"></i>เพิ่มเข้าไปในกลุ่ม
                                                </button>
                                                <div id="groupAssignmentMessage" class="mt-2"></div>
                                            </div>


                                            <div class="col-md-6">
                                                <h6><i class="fas fa-list fa-sm me-2"></i>กลุ่มที่ผู้ใช้เป็นสมาชิกอยู่</h6>
                                                <div id="userGroupsList" class="border rounded p-3" style="min-height: 50px; max-height: 300px; overflow-y: auto; background-color: #f8f9fa;">
                                                    <div class="text-center text-muted py-3">
                                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                            <span class="visually-hidden">กำลังโหลด...</span>
                                                        </div>
                                                        <p class="mt-2 mb-0">กำลังโหลดกลุ่ม...</p>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <div class="form-check">
                                    <?= Html::checkbox('resetPassword', false, [
                                        'class' => 'form-check-input',
                                        'id' => 'resetPasswordCheckbox',
                                        'onchange' => 'togglePasswordFields()'
                                    ]) ?>
                                    <?= Html::label('Reset Password (ตั้งเป็น 1234 อัตโนมัติ)', 'resetPasswordCheckbox', ['class' => 'form-check-label']) ?>
                                </div>
                            </div>
                            <!-- Removed manual password inputs: reset sets default 123456 automatically -->
                        </div>
                    </div>

                    <div class="form-group text-end">
                        <?= Html::submitButton('<i class="fas fa-save me-2"></i>Update User', ['class' => 'btn btn-primary']) ?>
                        <?= Html::a('<i class="fas fa-times me-2"></i>Cancel', ['ou-user'], ['class' => 'btn btn-default']) ?>
                    </div>
                    
                    <!-- Change Summary -->
                    <!-- <div class="mt-4">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>ข้อมูลการเปลี่ยนแปลง</h6>
                            <ul class="mb-0">
                                <li><strong>Username:</strong> สามารถเปลี่ยนแปลงได้ (ต้องไม่ซ้ำกับผู้ใช้อื่น)</li>
                                <li><strong>Display Name:</strong> ชื่อที่แสดงในระบบ</li>
                                <li><strong>Department:</strong> แผนกที่สังกัด</li>
                                <li><strong>ตำแหน่ง:</strong> ตำแหน่งงานในองค์กร</li>
                                <li><strong>Password:</strong> สามารถรีเซ็ตเป็น 1234 ได้</li>
                            </ul>
                        </div>
                    </div> -->

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.ldapuser-update {
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
.form-check {
    padding-left: 1.25rem;
    margin-bottom: 1rem;
}
.form-check-input {
    margin-top: 0.3rem;
    margin-left: -1.25rem;
}
.form-check-label {
    margin-bottom: 0;
}
</style>

<?php
// Prepare JavaScript configuration
$ldap = new LdapHelper();
$user = $ldap->getUser($model->cn);
$userDn = '';
if ($user && isset($user['distinguishedname'])) {
    $userDn = is_array($user['distinguishedname']) ? $user['distinguishedname'][0] : $user['distinguishedname'];
} elseif ($user && isset($user['distinguishedName'])) {
    $userDn = is_array($user['distinguishedName']) ? $user['distinguishedName'][0] : $user['distinguishedName'];
}

$jsConfig = [
    'userDn' => Html::encode($userDn),
    'csrfParam' => Yii::$app->request->csrfParam,
    'csrfToken' => Yii::$app->request->getCsrfToken(),
    'urls' => [
        'getUserGroups' => Yii::$app->urlManager->createUrl(['ldapuser/get-user-groups']),
        'getAvailableGroups' => Yii::$app->urlManager->createUrl(['ldapuser/get-available-groups']),
        'addUserToGroup' => Yii::$app->urlManager->createUrl(['ldapuser/add-user-to-group']),
        'removeUserFromGroup' => Yii::$app->urlManager->createUrl(['ldapuser/remove-user-from-group']),
    ],
];

// Register config first, then the JS file
$this->registerJs('userUpdateConfig = ' . json_encode($jsConfig) . ';', \yii\web\View::POS_HEAD);
$this->registerJsFile('@web/js/user-update.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);
?>

<script>
// Validation function to check for empty fields
function validateForm() {
    const requiredFields = [
        { id: 'ldapuser-samaccountname', name: 'Username' },
        { id: 'ldapuser-displayname', name: 'Display Name' },
        { id: 'ldapuser-department', name: 'Department' },
        { id: 'ldapuser-title', name: 'ตำแหน่ง' }
    ];
    
    // Check email format if provided
    const emailField = document.getElementById('ldapuser-mail');
    if (emailField && emailField.value.trim() !== '') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailField.value.trim())) {
            alert('รูปแบบ Email ไม่ถูกต้อง');
            emailField.focus();
            return false;
        }
    }
    
    // Check username format
    const usernameField = document.getElementById('ldapuser-samaccountname');
    if (usernameField && usernameField.value.trim() !== '') {
        const usernameRegex = /^[a-zA-Z0-9_]+$/;
        if (!usernameRegex.test(usernameField.value.trim())) {
            alert('Username ต้องประกอบด้วยตัวอักษร ตัวเลข และ underscore เท่านั้น');
            usernameField.focus();
            return false;
        }
    }
    
    const emptyFields = [];
    let firstEmptyField = null;
    
    requiredFields.forEach(field => {
        const element = document.getElementById(field.id);
        if (element && element.value.trim() === '') {
            emptyFields.push(field.name);
            if (!firstEmptyField) {
                firstEmptyField = element;
            }
        }
    });
    
    if (emptyFields.length > 0) {
        // Show validation modal
        const emptyFieldsList = document.getElementById('emptyFieldsList');
        emptyFieldsList.innerHTML = '';
        
        emptyFields.forEach(fieldName => {
            const li = document.createElement('li');
            li.textContent = fieldName;
            emptyFieldsList.appendChild(li);
        });
        
        // Store first empty field for focus function
        window.firstEmptyField = firstEmptyField;
        
        // Show modal
        const validationModal = new bootstrap.Modal(document.getElementById('validationModal'));
        validationModal.show();
        
        return false; // Prevent form submission
    }
    
    return true; // Allow form submission
}

// Function to focus on first empty field
function focusFirstEmptyField() {
    if (window.firstEmptyField) {
        window.firstEmptyField.focus();
        window.firstEmptyField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Add highlight effect
        window.firstEmptyField.style.borderColor = '#dc3545';
        window.firstEmptyField.style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
        
        // Remove highlight after 3 seconds
        setTimeout(() => {
            window.firstEmptyField.style.borderColor = '';
            window.firstEmptyField.style.boxShadow = '';
        }, 3000);
    }
    
    // Close modal
    const validationModal = bootstrap.Modal.getInstance(document.getElementById('validationModal'));
    if (validationModal) {
        validationModal.hide();
    }
}

// Handle form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('update-user-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return false;
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังอัปเดต...';
            submitBtn.disabled = true;
            
            // Submit form via AJAX
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Show success modal
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                    
                    // Update form fields with new data if provided
                    if (data.user) {
                        if (data.user.username) {
                            const usernameField = document.getElementById('ldapuser-samaccountname');
                            if (usernameField) usernameField.value = data.user.username;
                        }
                        if (data.user.displayname) {
                            const displayNameField = document.getElementById('ldapuser-displayname');
                            if (displayNameField) displayNameField.value = data.user.displayname;
                        }
                        if (data.user.department) {
                            const departmentField = document.getElementById('ldapuser-department');
                            if (departmentField) departmentField.value = data.user.department;
                        }
                        if (data.user.title) {
                            const titleField = document.getElementById('ldapuser-title');
                            if (titleField) titleField.value = data.user.title;
                        }
                        if (data.user.email) {
                            const emailField = document.getElementById('ldapuser-mail');
                            if (emailField) emailField.value = data.user.email;
                        }
                        if (data.user.physicalDeliveryOfficeName) {
                            const officeField = document.getElementById('ldapuser-physicaldeliveryofficename');
                            if (officeField) officeField.value = data.user.physicalDeliveryOfficeName;
                        }
                    }
                } else {
                    // Show error message
                    alert('เกิดข้อผิดพลาด: ' + (data.message || 'ไม่สามารถอัปเดตข้อมูลได้'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message);
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

// Add real-time validation feedback
document.addEventListener('DOMContentLoaded', function() {
    const requiredFields = [
        'ldapuser-samaccountname',
        'ldapuser-displayname', 
        'ldapuser-department',
        'ldapuser-title'
    ];
    
    // Add email validation
    const emailField = document.getElementById('ldapuser-mail');
    if (emailField) {
        emailField.addEventListener('blur', function() {
            if (this.value.trim() !== '') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(this.value.trim())) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
        
        emailField.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (emailRegex.test(this.value.trim())) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
    }
    
    // Add username format validation
    const usernameField = document.getElementById('ldapuser-samaccountname');
    if (usernameField) {
        usernameField.addEventListener('blur', function() {
            if (this.value.trim() !== '') {
                const usernameRegex = /^[a-zA-Z0-9_]+$/;
                if (!usernameRegex.test(this.value.trim())) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
        
        usernameField.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                const usernameRegex = /^[a-zA-Z0-9_]+$/;
                if (usernameRegex.test(this.value.trim())) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
    }
    
    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
            
            field.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        }
    });
    
    // Handle physicalDeliveryOfficeName and mail fields
    const officeField = document.getElementById('ldapuser-physicaldeliveryofficename');
    const mailField = document.getElementById('ldapuser-mail');
    
    if (officeField) {
        officeField.addEventListener('blur', function() {
            // If field is empty, show placeholder text
            if (this.value.trim() === '') {
                this.placeholder = 'ยังไม่ระบุ';
            }
        });
        
        officeField.addEventListener('input', function() {
            // Remove placeholder when user starts typing
            if (this.value.trim() !== '') {
                this.placeholder = '';
            }
        });
    }
    
    if (mailField) {
        mailField.addEventListener('blur', function() {
            // If field is empty, show placeholder text
            if (this.value.trim() === '') {
                this.placeholder = 'ยังไม่ระบุ';
            }
        });
        
        mailField.addEventListener('input', function() {
            // Remove placeholder when user starts typing
            if (this.value.trim() !== '') {
                this.placeholder = '';
            }
        });
    }
});


<?php
// Debug information
if ($model->hasErrors()) {
    Yii::error("Model validation errors: " . print_r($model->errors, true));
}
?> 