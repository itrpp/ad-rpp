<?php
use yii\helpers\Html;
use yii\widgets\LinkPager;

$this->title = 'Users Outside OUs';
$this->params['breadcrumbs'][] = ['label' => 'LDAP Management', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-users"></i> Users Outside Organizational Units
        </h3>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No users found outside of organizational units.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Display Name</th>
                            <th>Department</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $index => $user): ?>
                            <tr>
                                <td><?= $pagination->offset + $index + 1 ?></td>
                                <td><?= Html::encode($user['samaccountname'][0] ?? '') ?></td>
                                <td><?= Html::encode($user['displayname'][0] ?? '') ?></td>
                                <td><?= Html::encode($user['department'][0] ?? '') ?></td>
                                <td><?= Html::encode($user['mail'][0] ?? '') ?></td>
                                <td>
                                    <?php
                                    $userAccountControl = isset($user['useraccountcontrol'][0]) ? intval($user['useraccountcontrol'][0]) : 0;
                                    $ACCOUNTDISABLE = 0x0002;
                                    $isDisabled = ($userAccountControl & $ACCOUNTDISABLE);
                                    ?>
                                    <span class="badge badge-<?= $isDisabled ? 'danger' : 'success' ?>">
                                        <?= $isDisabled ? 'Disabled' : 'Enabled' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?= Html::a('<i class="fas fa-eye"></i>', ['view', 'cn' => $user['cn'][0]], [
                                            'class' => 'btn btn-sm btn-info',
                                            'title' => 'View Details'
                                        ]) ?>
                                        <?= Html::a('<i class="fas fa-edit"></i>', ['update', 'cn' => $user['cn'][0]], [
                                            'class' => 'btn btn-sm btn-primary',
                                            'title' => 'Update User'
                                        ]) ?>
                                        <?= Html::a('<i class="fas fa-exchange-alt"></i>', ['move', 'cn' => $user['cn'][0]], [
                                            'class' => 'btn btn-sm btn-warning',
                                            'title' => 'Move to OU'
                                        ]) ?>
                                        <?= Html::a(
                                            '<i class="fas fa-' . ($isDisabled ? 'check' : 'ban') . '"></i>',
                                            ['toggle-status', 'cn' => $user['cn'][0], 'enable' => $isDisabled ? 1 : 0],
                                            [
                                                'class' => 'btn btn-sm btn-' . ($isDisabled ? 'success' : 'danger'),
                                                'title' => $isDisabled ? 'Enable User' : 'Disable User',
                                                'data' => [
                                                    'confirm' => 'Are you sure you want to ' . ($isDisabled ? 'enable' : 'disable') . ' this user?',
                                                    'method' => 'post',
                                                ],
                                            ]
                                        ) ?>
                                        <?= Html::a('<i class="fas fa-sitemap"></i>', ['update-ou', 'dn' => $user['distinguishedname'][0]], [
                                            'class' => 'btn btn-sm btn-secondary',
                                            'title' => 'Update OU'
                                        ]) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <?= LinkPager::widget([
                    'pagination' => $pagination,
                    'options' => ['class' => 'pagination justify-content-center'],
                    'linkContainerOptions' => ['class' => 'page-item'],
                    'linkOptions' => ['class' => 'page-link'],
                    'disabledListItemSubTagOptions' => ['tag' => 'a', 'class' => 'page-link'],
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}

.badge {
    font-size: 0.85em;
    padding: 0.35em 0.65em;
}

.table td {
    vertical-align: middle;
}
</style> 