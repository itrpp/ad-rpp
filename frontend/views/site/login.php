<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \common\models\LoginForm $model */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;

// $this->title = 'เข้าสู่ระบบ';
// $this->params['breadcrumbs'][] = $this->title;

// ปิด debug toolbar บนหน้านี้ให้เหมือนหน้า Register
if (class_exists('yii\\debug\\Module')) {
    $this->off(\yii\web\View::EVENT_END_BODY, [\yii\debug\Module::getInstance(), 'renderToolbar']);
}
?>
<div class="site-login" style="max-width: 520px; margin: 0 auto;">
    <div class="card shadow-sm rounded-3">
        <div class="card-header">
            <div class="d-flex align-items-center gap-2">
                <img src="<?= Yii::getAlias('@web/favicon.ico') ?>" alt="logo" width="28" height="28" class="me-2">
                <h3 class="card-title mb-0"><?= Html::encode($this->title) ?></h3>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">เข้าสู่ระบบจัดการข้อมูลผู้ใช้งานของโรงพยาบาลราชพิพัฒน์</p>

            <?php $form = ActiveForm::begin([
                'id' => 'login-form',
                'fieldConfig' => [
                    'template' => "{label}\n{input}\n{error}",
                    'labelOptions' => ['class' => 'control-label'],
                    'errorOptions' => ['class' => 'invalid-feedback'],
                ],
                'options' => ['class' => 'form-horizontal'],
            ]); ?>

            <div class="row">
                <div class="col-md-12">
                    <?= $form->field($model, 'username', [
                        'options' => ['class' => 'form-group required'],
                    ])->textInput([
                        'autofocus' => true,
                        'maxlength' => true,
                        'class' => 'form-control',
                        'placeholder' => 'กรอกชื่อผู้ใช้ (Username)'
                    ])->label('ชื่อผู้ใช้') ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <?= $form->field($model, 'password', [
                        'options' => ['class' => 'form-group required'],
                    ])->passwordInput([
                        'class' => 'form-control',
                        'placeholder' => 'กรอกรหัสผ่าน'
                    ])->label('รหัสผ่าน') ?>
                </div>
            </div>



            <div class="d-flex align-items-center gap-2 mt-3">
                <?= Html::submitButton('<i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
                <?= Html::a('<i class="fas fa-user-plus"></i> ลงทะเบียนใช้งาน', ['ad-user/create'], ['class' => 'btn btn-default ms-auto']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>

    <style>
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
        .btn { margin-right: 5px; }
    </style>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var isLoggedIn = <?= Yii::$app->user->isGuest ? 'false' : 'true' ?>;
    var logoutSent = false;
    
    function sendAutoLogout(){
        if (!isLoggedIn || logoutSent) {
            return;
        }
        
        try {
            logoutSent = true;
            var url = '<?= Yii::$app->urlManager->createUrl(['site/auto-logout']) ?>';
            var csrf = '<?= Yii::$app->request->getCsrfToken() ?>';
            var csrfParam = '<?= Yii::$app->request->csrfParam ?>';
            
            // Use sendBeacon for reliability when page is closing
            if (navigator.sendBeacon) {
                var formData = new URLSearchParams();
                formData.append(csrfParam, csrf);
                var blob = new Blob([formData.toString()], { type: 'application/x-www-form-urlencoded' });
                navigator.sendBeacon(url, blob);
            } else {
                // Fallback to fetch with keepalive
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: csrfParam + '=' + encodeURIComponent(csrf),
                    keepalive: true
                }).catch(function() { /* ignore errors */ });
            }
        } catch (e) {
            console.error('Auto-logout error:', e);
        }
    }
    
    // Listen for page unload events
    window.addEventListener('pagehide', sendAutoLogout);
    window.addEventListener('beforeunload', function(e) {
        // Only send if not navigating to another page in same app
        if (!e.defaultPrevented) {
            sendAutoLogout();
        }
    });
    
    // Also handle visibility change (tab switch/close)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden && isLoggedIn) {
            // Tab is hidden, but don't logout yet (user might switch back)
            // Only logout on actual page close
        }
    });
});
</script>