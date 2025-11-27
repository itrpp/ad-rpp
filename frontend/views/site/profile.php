<?php

/** @var yii\web\View $this */
/** @var common\models\User $user */
/** @var common\models\ProfileForm $model */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;

$this->title = 'User Profile';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="site-profile">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Success Modal -->
            <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="successModalLabel">Success</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Profile updated successfully.
                        </div>
                        <div class="modal-footer">
                            <button id="successModalOkBtn" type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
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
                        var okBtn = document.getElementById('successModalOkBtn');
                        if (okBtn) {
                            okBtn.addEventListener('click', function() {
                                location.reload();
                            });
                        }
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
                </div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'profile-form',
                        'action' => ['/site/profile'],
                        'method' => 'post',
                        'layout' => 'horizontal',
                        'options' => ['onsubmit' => 'return validateProfileForm()'],
                        'fieldConfig' => [
                            'template' => "{label}\n{input}\n{error}",
                            'labelOptions' => ['class' => 'col-form-label'],
                            'inputOptions' => ['class' => 'form-control'],
                            'errorOptions' => ['class' => 'invalid-feedback'],
                        ],
                    ]); ?>

                    <!-- หัวข้อเดียว -->
                    <div class="card-header bg-primary text-white mb-3">
                        <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>ข้อมูลส่วนตัว</h5>
                    </div>

                    <div class="row g-4">
                        <!-- Card 1: ข้อมูลพื้นฐาน -->
                        <div class="col-md-6">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <div class="mb-4">
                                        <?= $form->field($model, 'displayName')->textInput(['required' => true])->label('ชื่อแสดงในระบบ <span class="text-danger">*</span>') ?>
                                    </div>
                                    <div class="mb-4">
                                        <?= $form->field($model, 'email')->textInput(['required' => true, 'type' => 'email'])->label('Email <span class="text-danger">*</span>') ?>
                                    </div>
                                    <div class="mb-0">
                                        <?= $form->field($model, 'telephoneNumber')->textInput(['required' => true])->label('Telephone Number <span class="text-danger">*</span>') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: ข้อมูลเพิ่มเติม -->
                        <div class="col-md-6">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <div class="mb-4">
                                        <?= $form->field($model, 'title')->textInput(['required' => false])->label('ตำแหน่ง') ?>
                                    </div>
                                    <div class="mb-4">
                                        <?= $form->field($model, 'givenname')->textInput(['required' => false])->label('ชื่อภาษาอังกฤษ') ?>
                                    </div>
                                    <div class="mb-0">
                                        <?= $form->field($model, 'streetaddress')->textarea(['required' => false, 'rows' => 3])->label('รายละเอียด') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- <h4>Change Password</h4>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <?= $form->field($model, 'currentPassword')->passwordInput() ?>
                            </div>
                            <div class="mb-3">
                                <?= $form->field($model, 'newPassword')->passwordInput() ?>
                            </div>
                            <div class="mb-3">
                                <?= $form->field($model, 'confirmPassword')->passwordInput() ?>
                            </div>
                        </div>
                    </div> -->

                    <div class="form-group text-center">
                        <?= Html::submitButton('Update Profile', ['class' => 'btn btn-primary']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.site-profile {
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
.profile-image {
    margin-bottom: 20px;
}

/* Validation styles */
.form-control.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.form-control.is-valid {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.invalid-feedback {
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 5px;
}

.text-danger {
    color: #dc3545 !important;
}

.alert-warning {
    color: #856404;
    background-color: #fff3cd;
    border-color: #ffeaa7;
}

.bg-warning {
    background-color: #ffc107 !important;
}

/* Disabled field styling */
.form-control:disabled {
    background-color: #e9ecef;
    opacity: 0.6;
    cursor: not-allowed;
}

.text-muted {
    color: #6c757d !important;
}
</style>

<script>
// Validation function to check for empty fields
function validateProfileForm() {
    const requiredFields = [
        { id: 'profileform-displayname', name: 'Display Name' },
        { id: 'profileform-email', name: 'Email' },
        { id: 'profileform-telephonenumber', name: 'Telephone Number' }
    ];
    
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

// Add real-time validation feedback
document.addEventListener('DOMContentLoaded', function() {
    const requiredFields = [
        'profileform-displayname',
        'profileform-email',
        'profileform-telephonenumber'
    ];
    
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
});
</script> 