<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Create AD User';
?>

<div class="site-signup">
    <h1><?= Html::encode($this->title) ?></h1>
    <?php if ($model->hasErrors()): ?>
        <div class="alert alert-danger">
            <?= Html::errorSummary($model) ?>
        </div>
    <?php endif; ?>
    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'cn')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'sn')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'sAMAccountName')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'displayName')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'department')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'telephoneNumber')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'organizationalUnit')->textInput(['maxlength' => true, 'value' => 'OU=Register,OU=rpp-user,DC=rpphosp,DC=local']) ?>

    <div class="form-group">
        <?= Html::submitButton('Create User', ['class' => 'btn btn-success']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var isLoggedIn = <?= \Yii::$app->user->isGuest ? 'false' : 'true' ?>;
    var logoutSent = false;
    
    function sendAutoLogout(){
        if (!isLoggedIn || logoutSent) {
            return;
        }
        
        try {
            logoutSent = true;
            var url = '<?= \Yii::$app->urlManager->createUrl(['site/auto-logout']) ?>';
            var csrf = '<?= \Yii::$app->request->getCsrfToken() ?>';
            var csrfParam = '<?= \Yii::$app->request->csrfParam ?>';
            
            if (navigator.sendBeacon) {
                var formData = new URLSearchParams();
                formData.append(csrfParam, csrf);
                var blob = new Blob([formData.toString()], { type: 'application/x-www-form-urlencoded' });
                navigator.sendBeacon(url, blob);
            } else {
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
    
    window.addEventListener('pagehide', sendAutoLogout);
    window.addEventListener('beforeunload', function(e) {
        if (!e.defaultPrevented) {
            sendAutoLogout();
        }
    });
});
</script>
