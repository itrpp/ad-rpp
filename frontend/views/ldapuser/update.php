<?php
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\components\LdapHelper;
use common\components\PermissionManager;

/** @var bool $readOnly */
/** @var bool|null $canEditGtwOnly */
$pm = new PermissionManager();
$isSuperUserOnly = $pm->isSuperUserOnly();
$isAdmin = $pm->isLdapAdmin();
$canEditGtwOnly = !empty($canEditGtwOnly) || ($isSuperUserOnly && $pm->canUpdateGtwCode());
$readOnly = ($isSuperUserOnly && !$canEditGtwOnly) || (!empty($readOnly) && !$canEditGtwOnly && !$isAdmin);

$this->title = $canEditGtwOnly
    ? ('แก้ไข GTW: ' . $model->cn)
    : (($readOnly ? 'ดูข้อมูลผู้ใช้: ' : 'Update User: ') . $model->cn);
$this->params['breadcrumbs'][] = ['label' => 'ผู้ลงทะเบียนรออนุมัติ', 'url' => ['ou-register']];
$this->params['breadcrumbs'][] = $canEditGtwOnly ? 'แก้ไข GTW' : ($readOnly ? 'ดูข้อมูล' : 'Update');

$roInput = ['readonly' => true, 'disabled' => true, 'class' => 'form-control bg-light'];
$roSelect = ['disabled' => true, 'class' => 'form-control bg-light'];
$countryEditable = $isAdmin || $canEditGtwOnly;
$backUrl = ($readOnly || $canEditGtwOnly) ? ['ou-register'] : ['ou-user'];
$mainFormClosed = false;

if ($model->country !== null && $model->country !== '') {
    $model->country = \common\models\LdapUser::formatGtwCode($model->country);
}

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

$successFlashMessage = null;
$successModalTitle = 'อัปเดตสำเร็จ';
$successModalDetail = 'ข้อมูลผู้ใช้ได้รับการอัปเดตเรียบร้อยแล้ว';
if (Yii::$app->session->hasFlash('success')) {
    $successFlashMessage = Yii::$app->session->getFlash('success');
    if ($canEditGtwOnly) {
        $successModalTitle = 'บันทึก GTW สำเร็จ';
        $successModalDetail = 'เลขรหัสผู้ใช้งาน GTW ได้รับการบันทึกเรียบร้อยแล้ว';
    }
}
?>

<div class="ldapuser-update">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <!-- Success Modal (ใช้แจ้งเตือนอย่างเดียว ไม่แสดงแบนเนอร์ซ้ำ) -->
            <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="successModalLabel">
                                <i class="fas fa-check-circle me-2"></i><span id="successModalTitle">อัปเดตสำเร็จ</span>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-success mb-3">
                                <i class="fas fa-check me-2"></i>
                                <strong id="successModalMessage">อัปเดตข้อมูลผู้ใช้สำเร็จ!</strong>
                            </div>
                            <p class="mb-0" id="successModalDetail">ข้อมูลผู้ใช้ได้รับการอัปเดตเรียบร้อยแล้ว</p>
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

            <?php if ($successFlashMessage !== null): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert" id="gtw-success-alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong><?= Html::encode($successFlashMessage) ?></strong>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (Yii::$app->session->hasFlash('error')): ?>
                <div class="alert alert-danger">
                    <?= Yii::$app->session->getFlash('error') ?>
                </div>
            <?php endif; ?>

            <?php if (Yii::$app->session->hasFlash('info')): ?>
                <div class="alert alert-info">
                    <?= Yii::$app->session->getFlash('info') ?>
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
                    <?php if ($canEditGtwOnly): ?>
                    <div class="alert alert-warning py-2 mb-0 mt-2 text-center">
                        <i class="fas fa-edit me-1"></i> แก้ไขได้เฉพาะ <strong>เลขรหัสผู้ใช้งาน GTW</strong> — ฟิลด์อื่นเป็นแบบอ่านอย่างเดียว
                    </div>
                    <?php elseif ($readOnly): ?>
                    <div class="alert alert-info py-2 mb-0 mt-2 text-center">
                        <i class="fas fa-eye me-1"></i> โหมดดูข้อมูลอย่างเดียว — ไม่สามารถแก้ไขหรือบันทึกได้
                    </div>
                    <?php else: ?>
                    <div class="text-end mt-2">
                        <?= Html::a('<i class="fas fa-exchange-alt me-1"></i> Move OU', ['move', 'cn' => $model->cn], [
                            'class' => 'btn btn-warning btn-sm me-1',
                            'title' => 'Move user to another OU',
                        ]) ?>
                        <?= Html::a('<i class="fas fa-list me-1"></i> ดูรายละเอียดทั้งหมด', ['view', 'cn' => $model->cn], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'update-user-form',
                        'enableAjaxValidation' => false,
                        'enableClientValidation' => $isAdmin,
                        'options' => array_filter([
                            'data-pjax' => $isAdmin ? true : null,
                            'onsubmit' => $canEditGtwOnly ? 'return false;' : ($readOnly ? 'return false;' : 'return validateForm()'),
                        ]),
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
                            <div class="mt-3 p-3 bg-light border rounded-3 shadow-sm fs-5 user-current-info-box">
                                <h6 class="user-current-info-title mb-2"><i class="fas fa-id-card me-2"></i>ข้อมูลปัจจุบัน</h6>
                                <div class="text-start">
                                    <?php 
                                    // Helper to display clean OU name (e.g., "IT-itdes" -> "IT") for read-only sections
                                    $displayDepartment = $model->department ?? '';
                                    if (!empty($displayDepartment) && strpos($displayDepartment, '-') !== false) {
                                        $displayDepartment = trim(explode('-', $displayDepartment)[0]);
                                    }

                                    // รวมคำนำหน้า + Display Name โดยไม่ซ้ำ (เช่น นาย + นายนรชัย -> นายนรชัย)
                                    $personalTitle = trim($model->personalTitle ?? '');
                                    $displayNameRaw = trim($model->displayName ?? '');
                                    $displayNameShow = 'ยังไม่ระบุ';
                                    if ($displayNameRaw !== '') {
                                        $displayNameShow = $displayNameRaw;
                                        if ($personalTitle !== '') {
                                            $titleNorm = rtrim($personalTitle, '.');
                                            $hasTitlePrefix = (mb_stripos($displayNameRaw, $personalTitle) === 0)
                                                || (mb_stripos($displayNameRaw, $titleNorm) === 0);
                                            if (!$hasTitlePrefix) {
                                                $firstWord = preg_split('/\s+/u', $displayNameRaw, 2)[0] ?? '';
                                                if ($firstWord !== ''
                                                    && mb_strlen($firstWord) > mb_strlen($titleNorm)
                                                    && mb_stripos($firstWord, $titleNorm) === 0) {
                                                    $hasTitlePrefix = true;
                                                }
                                            }
                                            if (!$hasTitlePrefix) {
                                                $displayNameShow = $personalTitle . ' ' . $displayNameRaw;
                                            }
                                        }
                                    }

                                    $renderUserInfoRow = static function ($icon, $label, $value, $extraClass = '') {
                                        $raw = is_scalar($value) ? trim((string) $value) : '';
                                        $display = $raw !== '' ? $raw : 'ยังไม่ระบุ';
                                        $copyBtn = $raw !== ''
                                            ? '<button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline btn-copy-info" data-copy="' . Html::encode($raw) . '" title="คัดลอก" onclick="window.copyUserInfoRow(this); return false;"><i class="fas fa-copy"></i></button>'
                                            : '';
                                        $class = trim('d-block user-info-row ' . $extraClass);
                                        return '<small class="' . $class . '">'
                                            . '<strong><i class="fas ' . $icon . ' me-1"></i>' . Html::encode($label) . ':</strong> '
                                            . '<span class="user-info-value-wrap">'
                                            . '<span class="user-info-value">' . Html::encode($display) . '</span>'
                                            . $copyBtn
                                            . '</span>'
                                            . '</small>';
                                    };
                                    ?>
                                    <?= $renderUserInfoRow('fa-user', 'Username', $model->sAMAccountName ?? '') ?>
                                    <?= $renderUserInfoRow('fa-signature', 'Display Name', $displayNameShow !== 'ยังไม่ระบุ' ? $displayNameShow : '') ?>
                                    <?= $renderUserInfoRow('fa-id-card', 'เลขบัตรประชาชน', $model->postalCode ?? '') ?>
                                    <?= $renderUserInfoRow('fa-language', 'ชื่อภาษาอังกฤษ', $model->description ?? '') ?>
                                    <?= $renderUserInfoRow('fa-sitemap', 'Department', $displayDepartment ?? '') ?>
                                    <?= $renderUserInfoRow('fa-briefcase', 'ตำแหน่ง', $model->title ?? '') ?>
                                    <?= $renderUserInfoRow('fa-envelope', 'Email', $model->mail ?? '') ?>
                                    <?= $renderUserInfoRow('fa-phone', 'Telephone Number', $model->telephoneNumber ?? '') ?>
                                    <?= $renderUserInfoRow('fa-hospital', 'เลขระบบ E-phis', $model->physicalDeliveryOfficeName ?? '') ?>
                                    
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
                            <?= $renderUserInfoRow('fa-calendar-plus', 'วันที่สร้างบัญชี', $whenCreatedThai !== 'ยังไม่ระบุ' ? $whenCreatedThai : '', 'mt-3 pt-2 border-top') ?>
                            <?= $renderUserInfoRow('fa-calendar-check', 'วันที่แก้ไข', $whenChangedThai !== 'ยังไม่ระบุ' ? $whenChangedThai : '') ?>
                            <?= $renderUserInfoRow('fa-building', 'ผู้ติดต่อ(บริษัท)', $model->company ?? '') ?>
                            <?= $renderUserInfoRow('fa-align-left', 'รายละเอียด', $model->streetaddress ?? '') ?>

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
                                        ->textInput(array_merge(['maxlength' => true, 'required' => $isAdmin, 'placeholder' => 'เช่น user123'], $isAdmin ? [] : $roInput))
                                        ->label('Username <span class="text-warning">(มีผลกับผู้ใช้ที่เคยมีประวัติ KM)</span><span class="text-danger">*</span>') ?>
                                </div>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'displayName')
                                        ->textInput(array_merge(['maxlength' => true, 'required' => $isAdmin, 'placeholder' => 'ชื่อที่แสดง'], $isAdmin ? [] : $roInput))
                                        ->label('Display Name <span class="text-danger">*</span>') ?>
                                </div>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'department')
                                        ->dropDownList($ouOptions, array_merge([
                                            'prompt' => 'เลือกกลุ่มงาน/ฝ่าย',
                                            'class' => 'form-control',
                                            'required' => $isAdmin,
                                        ], $isAdmin ? [] : $roSelect))->label('Department <span class="text-danger">*</span>') ?>
                                </div>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'title')
                                        ->textInput(array_merge(['maxlength' => true, 'required' => $isAdmin, 'placeholder' => 'ตำแหน่งงาน'], $isAdmin ? [] : $roInput))
                                        ->label('ตำแหน่ง <span class="text-danger">*</span>') ?>
                                </div>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'physicalDeliveryOfficeName')
                                        ->textInput(array_merge(['maxlength' => true, 'placeholder' => 'เลขระบบ E-phis (ถ้ามี)'], $isAdmin ? [] : $roInput))
                                        ->label('เลขระบบ E-phis (ถ้ามี) ') ?>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($canEditGtwOnly): ?>
                                    <div class="mb-3">
                                        <label class="col-form-label">เลขรหัสผู้ใช้งาน GTW <span class="text-danger">*</span></label>
                                        <div class="form-control bg-light text-muted">แก้ไขในช่องด้านล่าง</div>
                                    </div>
                                    <?php else: ?>
                                    <?= $form->field($model, 'country')
                                        ->textInput(array_merge([
                                            'maxlength' => 4,
                                            'placeholder' => '0001 (ไม่บังคับ)',
                                            'pattern' => '[0-9]{4}',
                                            'inputmode' => 'numeric',
                                            'autocomplete' => 'off',
                                            'title' => 'ตัวเลข 4 หลัก เช่น 0001 (ไม่บังคับ)',
                                        ], $countryEditable ? ['class' => 'form-control gtw-code-input'] : $roInput))
                                        ->label('เลขรหัสผู้ใช้งาน GTW') ?>
                                    <?php endif; ?>
                                </div>
                                <div class="w-100"></div>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'mail')
                                        ->textInput(array_merge(['maxlength' => true, 'placeholder' => 'someone@example.com'], $isAdmin ? [] : $roInput))
                                        ->label('Email') ?>
                                </div>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'telephoneNumber')
                                        ->textInput(array_merge(['class' => 'form-control', 'placeholder' => 'ยังไม่ระบุ'], $isAdmin ? [] : $roInput))
                                        ->label('Telephone Number') ?>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                    <?php if ($isAdmin): ?>
                    <hr>

                    <!-- Group Assignment Section (Admin เท่านั้น) -->
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
                        </div>
                    </div>

                    <div class="form-group text-end">
                        <?= Html::submitButton('<i class="fas fa-save me-2"></i>Update User', ['class' => 'btn btn-primary']) ?>
                        <?= Html::a('<i class="fas fa-times me-2"></i>Cancel', $backUrl, ['class' => 'btn btn-default']) ?>
                    </div>
                    <?php elseif ($canEditGtwOnly): ?>
                    <?php ActiveForm::end(); $mainFormClosed = true; ?>

                    <div class="card border-warning mt-3">
                        <div class="card-header bg-warning bg-opacity-25 py-2">
                            <strong><i class="fas fa-edit me-1"></i>แก้ไขเลขรหัสผู้ใช้งาน GTW</strong>
                        </div>
                        <div class="card-body">
                            <?php $gtwForm = ActiveForm::begin([
                                'id' => 'gtw-save-form',
                                'action' => ['update', 'cn' => $model->cn],
                                'method' => 'post',
                                'enableAjaxValidation' => false,
                                'enableClientValidation' => false,
                                'options' => ['novalidate' => true],
                            ]); ?>
                            <?= Html::hiddenInput('gtw_save', '1') ?>
                            <div class="row justify-content-end">
                                <div class="col-md-6">
                                    <?= $gtwForm->field($model, 'country')
                                        ->textInput([
                                            'id' => 'ldapuser-country',
                                            'maxlength' => 4,
                                            'placeholder' => '0001',
                                            'inputmode' => 'numeric',
                                            'autocomplete' => 'off',
                                            'class' => 'form-control gtw-code-input',
                                            'title' => 'ตัวเลข 4 หลัก เช่น 0001',
                                        ])
                                        ->label('เลขรหัสผู้ใช้งาน GTW <span class="text-danger">*</span>') ?>
                                </div>
                            </div>
                            <div class="form-group text-end mt-2 mb-0">
                                <?= Html::submitButton('<i class="fas fa-save me-2"></i>บันทึก GTW', [
                                    'class' => 'btn btn-primary',
                                    'id' => 'gtw-save-btn',
                                ]) ?>
                                <?= Html::a('<i class="fas fa-arrow-left me-2"></i>กลับรายการรออนุมัติ', ['ou-register'], ['class' => 'btn btn-secondary']) ?>
                            </div>
                            <?php ActiveForm::end(); ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-end mt-3">
                        <?= Html::a('<i class="fas fa-arrow-left me-2"></i>กลับรายการรออนุมัติ', ['ou-register'], ['class' => 'btn btn-secondary']) ?>
                    </div>
                    <?php endif; ?>
                    
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

                    <?php if (!$mainFormClosed): ?>
                    <?php ActiveForm::end(); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.user-current-info-box .user-current-info-title,
.user-current-info-box strong {
    color: #5c3d1e;
    font-weight: 600;
}
.user-current-info-box small.d-block {
    color: #1976d2;
    font-weight: 300;
}
.user-current-info-box small.d-block strong {
    color: #5c3d1e;
    font-weight: 600;
}
.user-current-info-box small.d-block strong i {
    color: #5c3d1e;
}
.user-current-info-box .btn-copy-info {
    color: #6c757d;
    font-size: 0.75rem;
    line-height: 1;
    vertical-align: middle;
    text-decoration: none;
    cursor: pointer;
    pointer-events: auto;
}
.user-current-info-box .btn-copy-info:hover,
.user-current-info-box .btn-copy-info:hover i {
    color: #1976d2;
}
.user-current-info-box .btn-copy-info i {
    color: #6c757d;
}
.user-current-info-box .btn-copy-info .fa-check {
    color: #198754 !important;
}
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
$this->registerJs(<<<'JS'
window.copyUserInfoRow = function (btn) {
    if (!btn) return false;

    var row = btn.closest('.user-info-row');
    var valueEl = row ? row.querySelector('.user-info-value') : null;
    var text = valueEl ? valueEl.textContent.trim() : (btn.getAttribute('data-copy') || '');
    if (!text || text === 'ยังไม่ระบุ') return false;

    var icon = btn.querySelector('i');
    var showOk = function () {
        if (!icon) return;
        icon.classList.remove('fa-copy');
        icon.classList.add('fa-check');
        setTimeout(function () {
            icon.classList.remove('fa-check');
            icon.classList.add('fa-copy');
        }, 1500);
    };

    var copied = false;
    try {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.top = '0';
        ta.style.left = '0';
        ta.style.width = '2em';
        ta.style.height = '2em';
        ta.style.padding = '0';
        ta.style.border = 'none';
        ta.style.outline = 'none';
        ta.style.boxShadow = 'none';
        ta.style.background = 'transparent';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        ta.setSelectionRange(0, text.length);
        copied = document.execCommand('copy');
        document.body.removeChild(ta);
    } catch (err) {
        copied = false;
    }

    if (copied) {
        showOk();
        return false;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(showOk).catch(function () {});
    }
    return false;
};
JS
, \yii\web\View::POS_HEAD);

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
    'readOnly' => $readOnly && !$canEditGtwOnly,
    'isSuperUserOnly' => $isSuperUserOnly,
    'canEditGtwOnly' => $canEditGtwOnly,
    'csrfParam' => Yii::$app->request->csrfParam,
    'csrfToken' => Yii::$app->request->getCsrfToken(),
    'urls' => [
        'getUserGroups' => Yii::$app->urlManager->createUrl(['ldapuser/get-user-groups']),
        'getAvailableGroups' => Yii::$app->urlManager->createUrl(['ldapuser/get-available-groups']),
        'addUserToGroup' => Yii::$app->urlManager->createUrl(['ldapuser/add-user-to-group']),
        'removeUserFromGroup' => Yii::$app->urlManager->createUrl(['ldapuser/remove-user-from-group']),
    ],
];

// Group assignment JS — เฉพาะ Admin (ไม่โหลดสำหรับ ManageUser)
if (!$isSuperUserOnly) {
    $this->registerJs('userUpdateConfig = ' . json_encode($jsConfig) . ';', \yii\web\View::POS_HEAD);
    $this->registerJsFile('@web/js/user-update.js', [
        'depends' => [\yii\web\JqueryAsset::class],
        'position' => \yii\web\View::POS_END
    ]);
}
?>

<script>
// แสดง modal อัปเดตสำเร็จแค่ครั้งเดียว (กันเด้ง 2 รอบ)
function showSuccessModalOnce(options) {
    options = options || {};
    var el = document.getElementById('successModal');
    if (!el) return;
    if (window._successModalShownAt && (Date.now() - window._successModalShownAt < 3000)) return;
    window._successModalShownAt = Date.now();

    if (options.title) {
        var titleEl = document.getElementById('successModalTitle');
        if (titleEl) titleEl.textContent = options.title;
    }
    if (options.message) {
        var msgEl = document.getElementById('successModalMessage');
        if (msgEl) msgEl.textContent = options.message;
    }
    if (options.detail) {
        var detailEl = document.getElementById('successModalDetail');
        if (detailEl) detailEl.textContent = options.detail;
    }

    var modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.show();
}

<?php if ($successFlashMessage !== null): ?>
// แจ้งเตือนบันทึกสำเร็จ (รอโหลดครบทุก asset รวม Bootstrap)
(function() {
    var successOptions = {
        title: <?= json_encode($successModalTitle, JSON_UNESCAPED_UNICODE) ?>,
        message: <?= json_encode($successFlashMessage, JSON_UNESCAPED_UNICODE) ?>,
        detail: <?= json_encode($successModalDetail, JSON_UNESCAPED_UNICODE) ?>
    };
    function notifySuccess() {
        var alertEl = document.getElementById('gtw-success-alert');
        if (alertEl) {
            alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        if (typeof showSuccessModalOnce === 'function' && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            showSuccessModalOnce(successOptions);
        }
    }
    if (document.readyState === 'complete') {
        notifySuccess();
    } else {
        window.addEventListener('load', notifySuccess);
    }
})();
<?php endif; ?>

// Validation: GTW code must be exactly 4 digits (รองรับ 0001)
function padGtwCode(value) {
    var digits = String(value || '').replace(/\D/g, '');
    if (digits === '') {
        return '';
    }
    if (digits.length > 4) {
        digits = digits.slice(-4);
    }
    while (digits.length < 4) {
        digits = '0' + digits;
    }
    return digits;
}

function validateGtwForm() {
    const gtwField = document.getElementById('ldapuser-country');
    if (!gtwField) {
        return true;
    }
    gtwField.value = padGtwCode(gtwField.value);
    const val = gtwField.value.trim();
    if (val === '') {
        alert('กรุณากรอกเลขรหัสผู้ใช้งาน GTW');
        gtwField.focus();
        return false;
    }
    if (!/^\d{4}$/.test(val)) {
        alert('เลขรหัสผู้ใช้งาน GTW ต้องเป็นตัวเลข 4 หลัก');
        gtwField.focus();
        gtwField.select();
        return false;
    }
    return true;
}

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
    
    // GTW code (Admin): ไม่บังคับ แต่ถ้ากรอกต้องเป็นตัวเลข 4 หลัก (รองรับ 0001)
    const gtwField = document.getElementById('ldapuser-country');
    if (gtwField && !gtwField.disabled && !gtwField.readOnly) {
        if (gtwField.value.trim() !== '') {
            gtwField.value = padGtwCode(gtwField.value);
        }
        const gtwVal = gtwField.value.trim();
        if (gtwVal !== '' && !/^\d{4}$/.test(gtwVal)) {
            alert('เลขรหัสผู้ใช้งาน GTW ต้องเป็นตัวเลข 4 หลัก');
            gtwField.focus();
            gtwField.select();
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

// Handle form submission with AJAX (Admin เท่านั้น)
<?php if ($isAdmin): ?>
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
                    showSuccessModalOnce();
                    
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
<?php endif; ?>

<?php if ($canEditGtwOnly): ?>
// ManageUser: ฟอร์ม GTW แยก — pad + submit ครั้งเดียว
document.addEventListener('DOMContentLoaded', function() {
    var gtwForm = document.getElementById('gtw-save-form');
    var gtwField = document.getElementById('ldapuser-country');
    var saveBtn = document.getElementById('gtw-save-btn');
    if (!gtwForm || !gtwField) {
        return;
    }
    if (gtwField.value.trim() !== '') {
        gtwField.value = padGtwCode(gtwField.value);
    }
    gtwField.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
    });
    gtwField.addEventListener('blur', function() {
        if (this.value.trim() !== '') {
            this.value = padGtwCode(this.value);
        }
    });
    gtwForm.addEventListener('submit', function(e) {
        gtwField.value = padGtwCode(gtwField.value);
        if (gtwField.value.trim() === '') {
            e.preventDefault();
            alert('กรุณากรอกเลขรหัสผู้ใช้งาน GTW');
            gtwField.focus();
            return;
        }
        if (!/^\d{4}$/.test(gtwField.value.trim())) {
            e.preventDefault();
            alert('เลขรหัสผู้ใช้งาน GTW ต้องเป็นตัวเลข 4 หลัก');
            gtwField.focus();
            gtwField.select();
            return;
        }
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังบันทึก...';
        }
    });
});
<?php elseif ($isAdmin && $countryEditable): ?>
// GTW: รับเฉพาะตัวเลข สูงสุด 4 หลัก (Admin)
document.addEventListener('DOMContentLoaded', function() {
    const gtwField = document.getElementById('ldapuser-country');
    if (!gtwField || gtwField.disabled || gtwField.readOnly) {
        return;
    }
    if (gtwField.value.trim() !== '') {
        gtwField.value = padGtwCode(gtwField.value);
    }
    gtwField.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
        if (/^\d{4}$/.test(this.value)) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            if (this.value.length > 0) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        }
    });
    gtwField.addEventListener('blur', function() {
        if (this.value.trim() === '') {
            this.classList.remove('is-invalid', 'is-valid');
            return;
        }
        this.value = padGtwCode(this.value);
        if (/^\d{4}$/.test(this.value)) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        }
    });
});
<?php endif; ?>

// Add real-time validation feedback (Admin เท่านั้น)
<?php if ($isAdmin): ?>
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
<?php endif; ?>


<?php
// Debug information
if ($model->hasErrors()) {
    Yii::error("Model validation errors: " . print_r($model->errors, true));
}
?> 