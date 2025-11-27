<?php
/** @var yii\web\View $this */
/** @var common\models\User $user */
use yii\helpers\Html;
use yii\web\ForbiddenHttpException;
use yii\widgets\ActiveForm;
use common\components\PermissionManager;

// Check if user is logged in
if (Yii::$app->user->isGuest) {
    throw new ForbiddenHttpException('You are not allowed to access this page. Please login first.');
}

// Check if user has admin permissions
$permissionManager = new PermissionManager();
if (!$permissionManager->isLdapAdmin()) {
    throw new ForbiddenHttpException('คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้ เฉพาะผู้ดูแลระบบเท่านั้น');
}

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
                <?php if (isset($ouUsers[$mainOu['dn']])): ?>
                <div class="ou-users">
                    <h5 class="mb-3">Users in this OU:</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 50px">No</th>
                                    <th>Username</th>
                                    <th>ชื่อที่แสดงในระบบ</th>
                                    <th>ผู้ใช้งาน (CN)</th>
                                    <th>แผนก</th>
                                    <th>บริษัท/(บุคลากรผู้ติดต่อ)</th>
                                    <th>วันที่ลงทะเบียน</th>
                                    <th>Status</th>
                                    <th style="width: 150px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                foreach ($ouUsers[$mainOu['dn']] as $user): 
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
                                ?>
                                <tr class="user-row" data-ou="<?= Html::encode($mainOu['dn']) ?>">
                                    <td><?= $counter++ ?></td>
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
                                    <td>
                                        <div class="btn-group">
                                            <?= Html::a('<i class="fas fa-edit"></i>', ['update', 'cn' => $cn], [
                                                'class' => 'btn btn-sm btn-primary',
                                                'title' => 'แก้ไขข้อมูลผู้ใช้: ' . Html::encode($displayName ?: $username),
                                            ]) ?>
                                            <?= Html::a('<i class="fas fa-exchange-alt"></i>', ['move', 'cn' => $cn], [
                                                'class' => 'btn btn-sm btn-warning',
                                                'title' => 'Move',
                                                'method' => 'post',
                                            ]) ?>
                                            <button type="button" class="btn btn-sm btn-danger delete-user" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteUserModal"
                                                data-cn="<?= Html::encode($cn) ?>"
                                                data-username="<?= Html::encode($username) ?>"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
    // User Search Functionality
    const userSearch = document.getElementById('userSearch');
    const searchButton = document.getElementById('searchButton');
    const userRows = document.querySelectorAll('.user-row');

    function filterUsers() {
        const searchTerm = userSearch.value.toLowerCase();
        
        userRows.forEach(row => {
            const username = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const displayName = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const department = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            const email = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
            
            const matches = username.includes(searchTerm) || 
                          displayName.includes(searchTerm) || 
                          department.includes(searchTerm) || 
                          email.includes(searchTerm);
            
            row.style.display = matches ? '' : 'none';
        });
    }

    userSearch.addEventListener('input', filterUsers);
    searchButton.addEventListener('click', filterUsers);


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

