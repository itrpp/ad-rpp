<?php
use yii\helpers\Html;
use yii\web\ForbiddenHttpException;
use yii\widgets\ActiveForm;

// Check if user is logged in
if (Yii::$app->user->isGuest) {
    throw new ForbiddenHttpException('You are not allowed to access this page. Please login first.');
}

$this->title = 'Create New Organizational Unit';
// $this->params['breadcrumbs'][] = ['label' => 'LDAP Management', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Manage OUs', 'url' => ['manage-ou']];
$this->params['breadcrumbs'][] = $this->title;

// Get LDAP helper instance
$ldapHelper = Yii::$app->ldap;
$parentOUs = $ldapHelper->getAllOUs();
?>

<div class="row">
    <div class="col-12">
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-plus-circle"></i> Create New Organizational Unit
                </h3>
            </div>
            <div class="card-body">
                <?php $form = ActiveForm::begin([
                    'id' => 'create-ou-form',
                    'options' => ['class' => 'form-horizontal'],
                ]); ?>

                <div class="row">
                    <div class="col-md-6">
                        <?= $form->field($model, 'ou_name')->textInput([
                            'placeholder' => 'Enter OU name',
                            'class' => 'form-control'
                        ])->label('OU Name <span class="text-danger">*</span>') ?>

                        <?= $form->field($model, 'type')->dropDownList([
                            'User OU' => 'User OU',
                            'Register OU' => 'Register OU',
                            'Other OU' => 'Other OU'
                        ], [
                            'prompt' => 'Select OU type',
                            'class' => 'form-control'
                        ])->label('Type <span class="text-danger">*</span>') ?>

                        <?= $form->field($model, 'parent_ou')->dropDownList(
                            array_reduce($parentOUs, function($result, $ou) {
                                $result[$ou['dn']] = $ou['ou'];
                                return $result;
                            }, []),
                            [
                                'prompt' => 'Select parent OU',
                                'class' => 'form-control'
                            ]
                        )->label('Parent OU') ?>
                    </div>
                    <div class="col-md-6">
                        <?= $form->field($model, 'description')->textarea([
                            'rows' => 4,
                            'placeholder' => 'Enter OU description',
                            'class' => 'form-control'
                        ]) ?>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <?= Html::checkbox('protected', false, [
                                    'class' => 'custom-control-input',
                                    'id' => 'protected'
                                ]) ?>
                                <label class="custom-control-label" for="protected">
                                    Protected from accidental deletion
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-4">
                    <?= Html::submitButton('<i class="fas fa-save"></i> Create OU', [
                        'class' => 'btn btn-primary',
                        'name' => 'create-ou-button'
                    ]) ?>
                    <?= Html::a('<i class="fas fa-times"></i> Cancel', ['manage-ou'], [
                        'class' => 'btn btn-secondary ml-2'
                    ]) ?>
                </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>
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
    padding: 0.75rem 1.25rem;
    position: relative;
    display: flex;
    align-items: center;
}

.card-title {
    margin-bottom: 0;
    color: #1f2d3d;
    font-size: 1.1rem;
    font-weight: 400;
}

.form-group {
    margin-bottom: 1rem;
}

.form-control {
    display: block;
    width: 100%;
    height: calc(2.25rem + 2px);
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
}

.form-control:focus {
    color: #495057;
    background-color: #fff;
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.btn {
    display: inline-block;
    font-weight: 400;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    user-select: none;
    border: 1px solid transparent;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: 0.25rem;
    transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
}

.btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}

.btn-secondary {
    color: #fff;
    background-color: #6c757d;
    border-color: #6c757d;
}

.text-danger {
    color: #dc3545;
}

.custom-control {
    position: relative;
    display: block;
    min-height: 1.5rem;
    padding-left: 1.5rem;
}

.custom-control-input {
    position: absolute;
    z-index: -1;
    opacity: 0;
}

.custom-control-label {
    position: relative;
    margin-bottom: 0;
    vertical-align: top;
}

.custom-control-label::before {
    position: absolute;
    top: 0.25rem;
    left: -1.5rem;
    display: block;
    width: 1rem;
    height: 1rem;
    pointer-events: none;
    content: "";
    background-color: #fff;
    border: #adb5bd solid 1px;
}

.custom-control-input:checked ~ .custom-control-label::before {
    color: #fff;
    border-color: #007bff;
    background-color: #007bff;
}
</style> 