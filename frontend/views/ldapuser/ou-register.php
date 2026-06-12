<?php
/** @var yii\web\View $this */
/** @var common\models\User $user */
use yii\helpers\Html;
use yii\web\ForbiddenHttpException;
use yii\widgets\ActiveForm;
use yii\widgets\LinkPager;
use common\components\PermissionManager;
use common\models\LdapUser;

// Check if user is logged in
if (Yii::$app->user->isGuest) {
    throw new ForbiddenHttpException('You are not allowed to access this page. Please login first.');
}

$permissionManager = new PermissionManager();
if (!$permissionManager->canViewOuRegister()) {
    throw new ForbiddenHttpException('คุณไม่มีสิทธิ์ในการเข้าถึงหน้ารายการผู้ลงทะเบียนรออนุมัติ');
}
$canManageRegister = $permissionManager->canManageOuRegister();
$isSuperUserOnly = $permissionManager->isSuperUserOnly();

$this->title = 'Organizational Unit Register';

$this->params['breadcrumbs'][] = $this->title;

// Get current user's OU
$currentUserOu = Yii::$app->session->get('ldapUserData')['ou'] ?? 'rpp-user';

if (!function_exists('formatThaiAdDate')) {
    function formatThaiAdDate($adWhen) {
        if (empty($adWhen)) {
            return 'ยังไม่ระบุ';
        }
        // รับรูปแบบ AD เช่น 20250128 061530.0Z หรือ 20250128061530.0Z (ไม่มีช่องว่าง)
        $s = preg_replace('/[^0-9]/', '', (string)$adWhen); // เก็บเฉพาะตัวเลข
        if (!preg_match('/^(\d{8})(\d{6})$/', $s, $m)) {
            return 'ยังไม่ระบุ';
        }
        $dt = \DateTime::createFromFormat('YmdHis', $m[1] . $m[2], new \DateTimeZone('UTC'));
        if ($dt === false) {
            return 'ยังไม่ระบุ';
        }
        $dt->setTimezone(new \DateTimeZone('Asia/Bangkok'));
        $thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $d = (int)$dt->format('j');
        $mIndex = (int)$dt->format('n');
        $y = (int)$dt->format('Y') + 543;
        $time = $dt->format('H:i');
        return $d . ' ' . $thaiMonths[$mIndex - 1] . ' ' . $y . ' ' . $time . ' น.';
    }
}
?>

<?php
$filterHasGtw = Yii::$app->request->get('filter_gtw') === '1';
$filterHasEphis = Yii::$app->request->get('filter_ephis') === '1';
$filterMissingIntegration = Yii::$app->request->get('filter_missing') === '1';
?>
<?php $this->registerCssFile('@web/css/ou-user.css'); ?>
<?php $this->registerJsFile('@web/js/ou-register-filters.js', ['depends' => [\yii\web\JqueryAsset::class]]); ?>
<style>
/* Cursor styles for interactive elements */
.user-row {
    cursor: pointer;
}

.user-row:hover {
    background-color: #f8f9fa;
    transition: background-color 0.2s ease;
}

.btn {
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn:active {
    transform: translateY(0);
}

.badge {
    cursor: default;
}

#userSearch {
    cursor: text;
}

#searchButton {
    cursor: pointer;
}

.modal-header {
    cursor: move;
}

.form-select {
    cursor: pointer;
}

/* Disable cursor for disabled buttons */
.btn:disabled {
    cursor: not-allowed;
    opacity: 0.65;
}

/* Custom cursor for action buttons */
.btn-info, .btn-warning, .btn-primary {
    cursor: pointer;
}

/* Hover effect for table rows */
.table-hover tbody tr:hover {
    background-color: #f5f5f5;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

/* Custom cursor for modal close button */
.btn-close {
    cursor: pointer;
}

/* Custom cursor for form elements */
input[type="text"],
input[type="email"],
input[type="password"],
select,
textarea {
    cursor: text;
}

/* Custom cursor for links */
a {
    cursor: pointer;
}

/* Custom cursor for draggable elements */
[draggable="true"] {
    cursor: move;
}

/* Custom cursor for resizable elements */
[resizable="true"] {
    cursor: se-resize;
}

/* Custom cursor for help elements */
[data-tooltip] {
    cursor: help;
}

/* Custom cursor for loading states */
.loading {
    cursor: wait;
}

/* Custom cursor for zoom elements */
.zoom {
    cursor: zoom-in;
}

/* Custom cursor for text selection */
::selection {
    cursor: text;
}

.ou-register-pagination .pagination {
    margin-bottom: 0;
}
</style>

<!-- Main Content -->
<div class="row">
    <div class="col-12">
        <!-- OU Structure Card -->
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-plus"></i> Organizational Unit Register
                </h3>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" name="userSearch" class="form-control float-right" id="userSearch" placeholder="Search users...">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-default" id="searchButton">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($pagination) && $pagination->totalCount > 0): ?>
                <div class="ou-users" data-register-list="1" data-total-count="<?= (int) $pagination->totalCount ?>">
                    <h5 class="mb-3">
                        Users in this OU:
                        <span class="badge bg-info ms-1" id="filteredCount" data-total="<?= (int) $pagination->totalCount ?>"><?= (int) $pagination->totalCount ?> คน</span>
                    </h5>
                    <div class="row mb-3">
                        <div class="col-md-6"></div>
                        <div class="col-md-6">
                            <div class="integration-filter-group d-flex flex-wrap align-items-center gap-2 justify-content-md-end">
                                <span class="small text-muted me-1"><i class="fas fa-filter me-1"></i>กรองตามระบบ</span>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="filterHasGtw" value="1"<?= $filterHasGtw ? ' checked' : '' ?>>
                                    <label class="form-check-label integration-filter-label" for="filterHasGtw">
                                        <span class="badge user-badge-gtw">GTW</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="filterHasEphis" value="1"<?= $filterHasEphis ? ' checked' : '' ?>>
                                    <label class="form-check-label integration-filter-label" for="filterHasEphis">
                                        <span class="badge user-badge-ephis">e-phis</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="filterHasNone" value="1"<?= $filterMissingIntegration ? ' checked' : '' ?>>
                                    <label class="form-check-label integration-filter-label" for="filterHasNone">
                                        <span class="badge user-badge-none">ยังไม่มี</span>
                                    </label>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="clearIntegrationFilter" title="ล้างตัวกรองรหัส" aria-label="ล้างตัวกรองรหัส">
                                    <i class="fas fa-times"></i>
                                </button>
                                <small class="text-muted ms-1 d-none d-xl-inline">กรองเฉพาะหน้านี้</small>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th class="text-end col-row-no">No.</th>
                                    <th class="col-ephis text-center">e-phis</th>
                                    <th class="col-gtw text-center">GTW</th>
                                    <th>Username</th>
                                    <th>ชื่อที่แสดงในระบบ</th>
                                    <th>ผู้ใช้งาน (CN)</th>
                                    <th>แผนก</th>
                                    <th>บริษัท/(บุคลากรผู้ติดต่อ)</th>
                                    <th>วันที่ลงทะเบียน</th>
                                    <th>Status</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rowOffset = isset($pagination) ? (int) $pagination->offset : 0;
                                $counter = $rowOffset + 1;
                                foreach ($registerUsers as $user):
                                    // Ensure all required fields are present
                                    $username = $user['samaccountname'] ?? '';
                                    $displayName = $user['displayname'] ?? '';
                                    $cn = $user['cn'] ?? $user['displayname'] ?? ''; // Use displayname as fallback for CN
                                    $department = $user['department'] ?? '';
                                    $company = $user['company'] ?? 'บุคลากร';
                                    // Determine OU name from attribute or distinguished name
                                    $ouName = $user['ou'] ?? '';
                                    if (empty($ouName) && !empty($user['distinguishedname'])) {
                                        if (preg_match('/OU=([^,]+)/i', $user['distinguishedname'], $matches)) {
                                            $ouName = $matches[1];
                                        }
                                    }
                                    $userAccountControl = isset($user['useraccountcontrol']) ? intval($user['useraccountcontrol']) : 0;
                                    $ACCOUNTDISABLE = 0x0002;
                                    $isDisabled = ($userAccountControl & $ACCOUNTDISABLE);
                                    $getUserAttr = static function (array $user, string $key): string {
                                        $keyLower = strtolower($key);
                                        foreach ($user as $k => $v) {
                                            if (strtolower((string)$k) === $keyLower) {
                                                if (is_array($v)) {
                                                    return trim((string)($v[0] ?? ''));
                                                }
                                                return trim((string)$v);
                                            }
                                        }
                                        return '';
                                    };
                                    $gtwCode = LdapUser::normalizeGtwCode($getUserAttr($user, 'countrycode'));
                                    $ephisCode = trim($getUserAttr($user, 'physicaldeliveryofficename'));
                                    $hasGtw = $gtwCode !== '' && preg_match('/^\d+$/', $gtwCode) === 1;
                                    $hasEphis = $ephisCode !== '' && preg_match('/^\d+$/', $ephisCode) === 1;
                                ?>
                                <tr class="user-row"
                                    data-ou="<?= Html::encode($mainOu['dn']) ?>"
                                    data-username="<?= Html::encode($username) ?>"
                                    data-cn="<?= Html::encode($cn) ?>"
                                    data-displayname="<?= Html::encode($displayName) ?>"
                                    data-department="<?= Html::encode($department) ?>"
                                    data-company="<?= Html::encode($company) ?>"
                                    data-has-gtw="<?= $hasGtw ? '1' : '0' ?>"
                                    data-has-ephis="<?= $hasEphis ? '1' : '0' ?>"
                                    data-rowindex="<?= $counter ?>"
                                >
                                    <td class="text-end col-row-no"><?= $counter ?></td>
                                    <td class="align-middle text-center col-ephis">
                                        <?= $this->render('_user_integration_badges', ['user' => $user, 'type' => 'ephis']) ?>
                                    </td>
                                    <td class="align-middle text-center col-gtw">
                                        <?= $this->render('_user_integration_badges', ['user' => $user, 'type' => 'gtw']) ?>
                                    </td>
                                    <td><?= Html::encode($username) ?></td>
                                    <td><?= Html::encode($displayName) ?></td>
                                    <td><?= Html::encode($cn) ?></td>
                                    <td><?= Html::encode($department) ?></td>
                                    <td><?= Html::encode($company) ?></td>
                                    <td><?php
                                        $whenCreatedThai = 'ยังไม่ระบุ';
                                        $wc = isset($user['whencreated']) ? (string)$user['whencreated'] : '';
                                        if ($wc !== '' && preg_match('/^(\d{8})(\d{6})/', preg_replace('/[^0-9]/', '', $wc), $mm)) {
                                            $dt = \DateTime::createFromFormat('YmdHis', $mm[1] . $mm[2], new \DateTimeZone('UTC'));
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
                                        echo Html::encode($whenCreatedThai);
                                    ?></td>
                                 
                                    <td>
                                        <?php if ($isDisabled): ?>
                                            <span class="badge badge-danger">Disabled</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Enabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle col-actions">
                                        <div class="btn-group ou-user-action-group">
                                        <?php if ($isSuperUserOnly): ?>
                                            <?= Html::a('<i class="fas fa-edit"></i>', ['update', 'cn' => $cn], [
                                                'class' => 'btn btn-sm btn-primary ou-user-action-btn',
                                                'title' => 'แก้ไข GTW: ' . Html::encode($displayName ?: $username),
                                            ]) ?>
                                        <?php elseif ($canManageRegister): ?>
                                            <?= Html::a('<i class="fas fa-edit"></i>', ['update', 'cn' => $cn], [
                                                'class' => 'btn btn-sm btn-primary ou-user-action-btn',
                                                'title' => 'แก้ไขข้อมูล/สิทธิ์/กลุ่ม: ' . Html::encode($displayName ?: $username),
                                            ]) ?>
                                            <?= Html::a('<i class="fas fa-exchange-alt"></i>', ['move', 'cn' => $cn], [
                                                'class' => 'btn btn-sm btn-warning ou-user-action-btn',
                                                'title' => 'ย้าย OU (อนุมัติ)',
                                                'method' => 'post',
                                            ]) ?>
                                            <button type="button" class="btn btn-sm btn-danger delete-user ou-user-action-btn"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteUserModal"
                                                data-cn="<?= Html::encode($cn) ?>"
                                                data-username="<?= Html::encode($username) ?>"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php $counter++; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (isset($pagination) && $pagination->totalCount > 0): ?>
                    <?php
                    $pageFrom = $pagination->offset + 1;
                    $pageTo = min($pagination->offset + $pagination->limit, $pagination->totalCount);
                    ?>
                    <div class="ou-register-pagination d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3 pt-3 border-top">
                        <small class="text-muted mb-0">
                            แสดง <?= $pageFrom ?>–<?= $pageTo ?> จาก <?= (int) $pagination->totalCount ?> รายการ
                        </small>
                        <?php if ($pagination->pageCount > 1): ?>
                        <?= LinkPager::widget([
                            'pagination' => $pagination,
                            'options' => ['class' => 'pagination pagination-sm mb-0'],
                            'linkContainerOptions' => ['class' => 'page-item'],
                            'linkOptions' => ['class' => 'page-link'],
                            'disabledListItemSubTagOptions' => ['tag' => 'span', 'class' => 'page-link'],
                            'maxButtonCount' => 5,
                        ]) ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No users found in this Organizational Unit.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteUserModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการลบผู้ใช้
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                <p>คุณแน่ใจหรือไม่ที่จะลบผู้ใช้ <strong id="deleteUsername"></strong>?</p>
                <p class="text-danger"><i class="fas fa-exclamation-circle"></i> การลบผู้ใช้เป็นแบบถาวรและไม่สามารถกู้คืนได้</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <?= Html::a('ลบผู้ใช้', '#', [
                    'class' => 'btn btn-danger',
                    'id' => 'confirmDelete',
                    'data' => [
                        'method' => 'post',
                    ],
                ]) ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete User Modal Functionality
    const deleteButtons = document.querySelectorAll('.delete-user');
    const deleteModal = document.getElementById('deleteUserModal');
    const deleteUsername = document.getElementById('deleteUsername');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const cn = this.getAttribute('data-cn');
            const username = this.getAttribute('data-username');
            
            // Update modal content
            deleteUsername.textContent = username;
            
            // Update delete button href with proper URL encoding
            const deleteUrl = '<?= Yii::$app->urlManager->createUrl(['ldapuser/delete']) ?>' + '&cn=' + encodeURIComponent(cn);
            confirmDeleteBtn.href = deleteUrl;
        });
    });
});
</script>

