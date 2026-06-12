<?php
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\components\LdapHelper;
use common\components\LdapUserEditTracker;
use common\components\PermissionManager;

/** @var common\models\LdapUser $model */
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

if ($canEditGtwOnly || $readOnly) {
    $this->params['breadcrumbs'][] = ['label' => 'ผู้ลงทะเบียนรออนุมัติ', 'url' => ['ou-register']];
} else {
    $this->params['breadcrumbs'][] = ['label' => 'All User', 'url' => ['ou-user']];
}
$this->params['breadcrumbs'][] = $model->cn;

$lastEditMeta = LdapUserEditTracker::get($model->cn) ?? [];
$lastEditedAt = LdapUserEditTracker::formatWhenChanged($model->whenChanged);
if ($lastEditedAt === '' && !empty($lastEditMeta['editedAt'])) {
    $lastEditedAt = (string)$lastEditMeta['editedAt'];
}

$this->params['pageSubtitle'] = [
    'editorLabel' => 'ผู้แก้ไขก่อนหน้า',
    'editedAtLabel' => 'วันที่-เวลาที่แก้ไขก่อนหน้า',
    'editor' => trim((string)($lastEditMeta['editor'] ?? '')),
    'editedAt' => $lastEditedAt,
];

$roInput = ['readonly' => true, 'disabled' => true, 'class' => 'form-control bg-light'];
$roSelect = ['disabled' => true, 'class' => 'form-control bg-light'];
$countryEditable = $isAdmin || $canEditGtwOnly;
$backUrl = ($readOnly || $canEditGtwOnly) ? ['ou-register'] : ['ou-user'];
$canViewOuRegister = $pm->canViewOuRegister();
$canViewAllUsers = $isAdmin || $pm->isSuperUser();
$gtwOriginalValue = \common\models\LdapUser::normalizeGtwCode($model->country ?? '');
$gtwInputAttrs = [
    'maxlength' => 6,
    'placeholder' => 'กรอกรหัส',
    'inputmode' => 'numeric',
    'autocomplete' => 'off',
    'title' => 'ตัวเลขไม่เกิน 6 หลัก',
    'data-original-gtw' => $gtwOriginalValue,
];
$mainFormClosed = false;

// Get available OUs
$ldap = new LdapHelper();
$ous = $ldap->getOrganizationalUnits();

// Get OU options for department dropdown
$ouOptions = [];
$allOus = $ldap->getOrganizationalUnits('OU=rpp-user,DC=rpphosp,DC=local');

// Create dropdown options from AD OUs
$excludedOus = ['Register-test', 'updateOU', 'test', 'ฝ่ายการพยาบาล', 'ฝ่ายการพยาบาล(Nurse)', 'rpp-register'];
$stripItDesSuffix = static function (string $value): string {
    $value = trim($value);
    if ($value !== '' && strlen($value) > 3
        && substr(strtolower($value), -3) === 'des') {
        return substr($value, 0, -3);
    }
    return $value;
};

foreach ($allOus as $ou) {
    if (isset($ou['ou']) && isset($ou['dn'])) {
        // Skip excluded OU names
        if (in_array($ou['ou'], $excludedOus)) {
            continue;
        }

        // แสดง "IT - it" แทน "IT - itdes" (itdes มาจากชื่อ OU หรือ description ใน AD)
        $ouName = $ou['ou'];

        if (strpos($ouName, '-') !== false) {
            $parts = explode('-', $ouName, 2);
            $firstPart = trim($parts[0]);
            $secondPart = $stripItDesSuffix(trim($parts[1] ?? ''));
            $displayName = $secondPart !== ''
                ? ($firstPart . ' - ' . $secondPart)
                : $firstPart;
        } else {
            $displayName = $ouName;

            if (isset($ou['description']) && !empty($ou['description'])) {
                $cleanDescription = $stripItDesSuffix(trim($ou['description']));
                if ($cleanDescription !== $displayName && $cleanDescription !== '') {
                    $displayName .= ' - ' . $cleanDescription;
                }
            }
        }

        if (strcasecmp($displayName, 'IT - itdes') === 0) {
            $displayName = 'IT - it';
        }

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
    <div class="row">
        <div class="col-12">
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
                <div class="card-header py-3">
                    <?php if ($canViewOuRegister || $canViewAllUsers || (!$canEditGtwOnly && !$readOnly)): ?>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($canViewOuRegister): ?>
                                <?= Html::a('<i class="fas fa-user-clock me-1"></i>ผู้ลงทะเบียนรออนุมัติ', ['ou-register'], [
                                    'class' => 'btn btn-outline-info btn-sm',
                                    'title' => 'กลับไปหน้ารายการผู้ลงทะเบียนรออนุมัติ',
                                ]) ?>
                            <?php endif; ?>
                            <?php if ($canViewAllUsers): ?>
                                <?= Html::a('<i class="fas fa-sitemap me-1"></i>All User', ['ou-user'], [
                                    'class' => 'btn btn-outline-primary btn-sm',
                                    'title' => 'กลับไปหน้ารายการผู้ใช้ทั้งหมด',
                                ]) ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!$canEditGtwOnly && !$readOnly): ?>
                        <div class="d-flex flex-wrap gap-2 ms-auto">
                            <?= Html::a('<i class="fas fa-exchange-alt me-1"></i> Move OU', ['move', 'cn' => $model->cn], [
                                'class' => 'btn btn-warning btn-sm',
                                'title' => 'Move user to another OU',
                            ]) ?>
                            <?= Html::a('<i class="fas fa-list me-1"></i> ดูรายละเอียดทั้งหมด', ['view', 'cn' => $model->cn], [
                                'class' => 'btn btn-outline-secondary btn-sm',
                            ]) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($canEditGtwOnly): ?>
                    <div class="update-mode-banner update-mode-banner--gtw mb-0 mt-2" role="status">
                        <i class="fas fa-shield-alt"></i>
                        <span>แก้ไขได้เฉพาะ <strong>เลขรหัสผู้ใช้งาน GTW</strong> — ฟิลด์อื่นเป็นแบบอ่านอย่างเดียว</span>
                    </div>
                    <?php elseif ($readOnly): ?>
                    <div class="alert alert-info py-2 mb-0 mt-2 text-center">
                        <i class="fas fa-eye me-1"></i> โหมดดูข้อมูลอย่างเดียว — ไม่สามารถแก้ไขหรือบันทึกได้
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body py-4">
                    <div class="row g-4 align-items-stretch update-user-panels-row">
                        <div class="col-md-5 col-lg-5">
                            <div class="h-100 p-3 p-lg-4 bg-light border rounded-3 shadow-sm user-current-info-box">
                                <h6 class="user-panel-title user-current-info-title mb-3"><i class="fas fa-id-card me-2"></i>ข้อมูลปัจจุบัน</h6>
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
                                        $class = trim('user-info-row ' . $extraClass);
                                        return '<div class="' . $class . '">'
                                            . '<span class="user-info-label"><i class="fas ' . $icon . ' me-1"></i>' . Html::encode($label) . '</span>'
                                            . '<span class="user-info-value-wrap">'
                                            . '<span class="user-info-value">' . Html::encode($display) . '</span>'
                                            . $copyBtn
                                            . '</span>'
                                            . '</div>';
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

                                    <?php if ($canEditGtwOnly): ?>
                                    <div class="user-info-gtw-edit mt-2 mb-2 text-start">
                                        <?php $gtwForm = ActiveForm::begin([
                                            'id' => 'gtw-save-form',
                                            'action' => ['update', 'cn' => $model->cn],
                                            'method' => 'post',
                                            'enableAjaxValidation' => false,
                                            'enableClientValidation' => false,
                                            'options' => ['novalidate' => true, 'class' => 'user-info-gtw-form'],
                                            'fieldConfig' => [
                                                'template' => "{label}\n{input}\n{error}",
                                                'labelOptions' => ['class' => 'form-label mb-1 small fw-semibold user-current-info-title'],
                                                'inputOptions' => ['class' => 'form-control form-control-sm gtw-code-input'],
                                                'errorOptions' => ['class' => 'invalid-feedback d-block small'],
                                            ],
                                        ]); ?>
                                        <?= Html::hiddenInput('gtw_save', '1') ?>
                                        <?= $gtwForm->field($model, 'country')
                                            ->textInput(array_merge([
                                                'id' => 'ldapuser-country',
                                            ], $gtwInputAttrs))
                                            ->label('<i class="fas fa-key me-1"></i>เลขรหัสผู้ใช้งาน GTW') ?>
                                        <div class="d-flex gap-2 flex-wrap justify-content-start mt-2">
                                            <?= Html::submitButton('<i class="fas fa-save me-1"></i>บันทึก GTW', [
                                                'class' => 'btn btn-primary btn-sm',
                                                'id' => 'gtw-save-btn',
                                            ]) ?>
                                            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>กลับรายการ', ['ou-register'], ['class' => 'btn btn-secondary btn-sm']) ?>
                                        </div>
                                        <?php ActiveForm::end(); ?>
                                    </div>
                                    <?php endif; ?>

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
                        <div class="col-md-7 col-lg-7">
                            <div class="h-100 p-3 p-lg-4 bg-white border rounded-3 shadow-sm update-user-edit-panel">
                            <?php
                            $editPanelTitle = $isAdmin
                                ? 'แก้ไขข้อมูลผู้ใช้'
                                : ($canEditGtwOnly ? 'ข้อมูลในระบบ' : 'ข้อมูลในระบบ (อ่านอย่างเดียว)');
                            $editPanelIcon = $isAdmin ? 'fa-edit' : 'fa-file-alt';
                            ?>
                            <?php $form = ActiveForm::begin([
                                'id' => 'update-user-form',
                                'enableAjaxValidation' => false,
                                'enableClientValidation' => $isAdmin,
                                'options' => array_filter([
                                    'class' => 'update-user-form',
                                    'data-pjax' => $isAdmin ? true : null,
                                    'onsubmit' => $readOnly ? 'return false;' : ($isAdmin ? 'return validateForm()' : 'return false;'),
                                ]),
                                'fieldConfig' => [
                                    'template' => "{label}\n{input}\n{error}",
                                    'labelOptions' => ['class' => 'form-label update-form-label'],
                                    'inputOptions' => ['class' => 'form-control'],
                                    'errorOptions' => ['class' => 'invalid-feedback'],
                                ],
                            ]); ?>
                            <h6 class="user-panel-title user-current-info-title mb-3">
                                <i class="fas <?= $editPanelIcon ?> me-2"></i><?= Html::encode($editPanelTitle) ?>
                            </h6>
                            <div class="row g-3 update-user-form-grid">
                                <div class="col-12">
                                    <?= $form->field($model, 'cn', ['options' => ['class' => 'mb-3 field-ldapuser-cn']])
                                        ->textInput(['readonly' => true, 'class' => 'form-control bg-light'])
                                        ->label('CN') ?>
                                </div>
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
                                <?php if (!$canEditGtwOnly): ?>
                                <div class="col-md-6">
                                    <?= $form->field($model, 'country')
                                        ->textInput(array_merge([
                                            'id' => 'ldapuser-country',
                                        ], $gtwInputAttrs, $countryEditable ? ['class' => 'form-control gtw-code-input'] : $roInput))
                                        ->label('เลขรหัสผู้ใช้งาน GTW') ?>
                                </div>
                                <?php endif; ?>
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
                    <?php else: ?>
                    <div class="text-end mt-3">
                        <?= Html::a('<i class="fas fa-arrow-left me-2"></i>กลับรายการรออนุมัติ', ['ou-register'], ['class' => 'btn btn-secondary']) ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!$mainFormClosed): ?>
                    <?php ActiveForm::end(); ?>
                    <?php endif; ?>
                            </div>
                        </div>
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

                </div>
            </div>
        </div>
    </div>
</div>

<style>
.update-mode-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 0.7rem 1.25rem;
    border-radius: 0.5rem;
    font-size: 0.9rem;
    line-height: 1.45;
    letter-spacing: 0.01em;
    color: #5c6670;
    background: linear-gradient(135deg, #f8f9fa 0%, #eef1f4 100%);
    border: 1px solid #dde2e6;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
}
.update-mode-banner i {
    color: #868e96;
    font-size: 0.95rem;
}
.update-mode-banner strong {
    color: #343a40;
    font-weight: 600;
}
.update-mode-banner--gtw {
    border-left: 4px solid #868e96;
}
.update-user-panels-row {
    margin-left: 0;
    margin-right: 0;
}
.user-panel-title {
    padding-bottom: 0.65rem;
    border-bottom: 2px solid #e9ecef;
}
.user-current-info-box,
.update-user-edit-panel {
    font-weight: 500;
    width: 100%;
    max-width: 100%;
}
.user-current-info-box .text-start,
.update-user-form {
    width: 100%;
}
.user-info-row {
    display: grid;
    grid-template-columns: minmax(9.5rem, 36%) 1fr;
    gap: 0.35rem 0.85rem;
    align-items: start;
    padding: 0.55rem 0;
    margin: 0;
    border-bottom: 1px dashed #dee2e6;
    font-size: 0.95rem;
    line-height: 1.45;
}
.user-info-row:last-child {
    border-bottom: none;
}
.user-info-row.border-top {
    border-top: 2px solid #e9ecef;
    margin-top: 0.35rem;
    padding-top: 0.85rem;
}
.user-info-label {
    color: #5c3d1e;
    font-weight: 700;
}
.user-info-label i {
    width: 1.1rem;
    text-align: center;
}
.user-info-value-wrap {
    min-width: 0;
    word-break: break-word;
}
.user-current-info-box .user-info-value,
.update-user-edit-panel .form-control,
.update-user-edit-panel .form-select {
    font-weight: 600;
    color: #0d47a1;
}
.user-current-info-box .user-current-info-title,
.user-current-info-box .user-info-label {
    color: #5c3d1e;
    font-weight: 700;
}
.update-user-edit-panel .update-form-label {
    font-size: 0.875rem;
    font-weight: 700;
    color: #5c3d1e;
    margin-bottom: 0.35rem;
}
.update-user-edit-panel .form-control,
.update-user-edit-panel .form-select {
    min-height: 38px;
    font-size: 0.95rem;
    border-radius: 0.375rem;
}
.update-user-edit-panel .mb-3 {
    margin-bottom: 0.9rem !important;
}
.update-user-form-grid > [class*="col-"] > .mb-3:last-child {
    margin-bottom: 0 !important;
}
.update-user-edit-panel hr {
    margin: 1.25rem 0;
    opacity: 0.15;
}
@media (max-width: 575.98px) {
    .user-info-row {
        grid-template-columns: 1fr;
        gap: 0.2rem;
    }
}
.user-info-gtw-edit .user-current-info-title {
    color: #5c3d1e;
    font-weight: 600;
}
.user-info-gtw-edit .form-control {
    color: #0d47a1;
    font-weight: 600;
}
.user-info-gtw-edit .invalid-feedback {
    text-align: left;
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
    padding: 0;
    width: 100%;
    max-width: 100%;
}
.card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
}
.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
.card-body {
    padding: 20px;
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

// GTW: ตัวเลขเท่านั้น สูงสุด 6 หลัก (ไม่บังคับจำนวนหลัก)
function normalizeGtwInput(value) {
    return String(value || '').replace(/\D/g, '').slice(0, 6);
}

function isValidGtwInput(val) {
    return val === '' || /^\d{1,6}$/.test(val);
}

function shouldRejectEmptyGtwSave(gtwField) {
    const val = normalizeGtwInput(gtwField.value).trim();
    if (val !== '') {
        return false;
    }
    if (gtwField.closest('#gtw-save-form')) {
        return true;
    }
    const original = (gtwField.dataset.originalGtw || '').trim();
    return original !== '';
}

function showEmptyGtwError(gtwField) {
    alert('ไม่สามารถบันทึกค่าว่างลงระบบได้ กรุณากรอกเลขรหัสผู้ใช้งาน GTW');
    gtwField.focus();
    gtwField.select();
}

function validateGtwForm() {
    const gtwField = document.getElementById('ldapuser-country');
    if (!gtwField) {
        return true;
    }
    gtwField.value = normalizeGtwInput(gtwField.value);
    const val = gtwField.value.trim();
    if (shouldRejectEmptyGtwSave(gtwField)) {
        showEmptyGtwError(gtwField);
        return false;
    }
    if (!isValidGtwInput(val)) {
        alert('เลขรหัสผู้ใช้งาน GTW ต้องเป็นตัวเลขไม่เกิน 6 หลัก');
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
    
    // GTW code (Admin): ห้ามบันทึกค่าว่างถ้าเคยมีค่าในระบบ
    const gtwField = document.getElementById('ldapuser-country');
    if (gtwField && !gtwField.disabled && !gtwField.readOnly) {
        gtwField.value = normalizeGtwInput(gtwField.value);
        const gtwVal = gtwField.value.trim();
        if (shouldRejectEmptyGtwSave(gtwField)) {
            showEmptyGtwError(gtwField);
            return false;
        }
        if (gtwVal !== '' && !isValidGtwInput(gtwVal)) {
            alert('เลขรหัสผู้ใช้งาน GTW ต้องเป็นตัวเลขไม่เกิน 6 หลัก');
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
// ManageUser: ฟอร์ม GTW แยก (ห้ามบันทึกค่าว่างลง AD)
document.addEventListener('DOMContentLoaded', function() {
    var gtwForm = document.getElementById('gtw-save-form');
    var gtwField = document.getElementById('ldapuser-country');
    var saveBtn = document.getElementById('gtw-save-btn');
    if (!gtwForm || !gtwField) {
        return;
    }
    gtwField.addEventListener('input', function() {
        this.value = normalizeGtwInput(this.value);
    });
    gtwForm.addEventListener('submit', function(e) {
        gtwField.value = normalizeGtwInput(gtwField.value);
        const val = gtwField.value.trim();
        if (shouldRejectEmptyGtwSave(gtwField)) {
            e.preventDefault();
            showEmptyGtwError(gtwField);
            return;
        }
        if (!isValidGtwInput(val)) {
            e.preventDefault();
            alert('เลขรหัสผู้ใช้งาน GTW ต้องเป็นตัวเลขไม่เกิน 6 หลัก');
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
// GTW: ตัวเลขเท่านั้น สูงสุด 6 หลัก (Admin)
document.addEventListener('DOMContentLoaded', function() {
    const gtwField = document.getElementById('ldapuser-country');
    if (!gtwField || gtwField.disabled || gtwField.readOnly) {
        return;
    }
    if (gtwField.value.trim() !== '') {
        gtwField.value = normalizeGtwInput(gtwField.value);
    }
    gtwField.addEventListener('input', function() {
        this.value = normalizeGtwInput(this.value);
        if (this.value === '') {
            this.classList.remove('is-invalid', 'is-valid');
        } else if (isValidGtwInput(this.value)) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        }
    });
    gtwField.addEventListener('blur', function() {
        if (this.value.trim() === '') {
            this.classList.remove('is-invalid', 'is-valid');
            return;
        }
        if (isValidGtwInput(this.value)) {
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