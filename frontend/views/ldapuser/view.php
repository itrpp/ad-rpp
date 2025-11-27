<?php
use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = isset($user['cn'][0]) ? $user['cn'][0] : 'User Details';
$this->params['breadcrumbs'][] = ['label' => 'OU Users', 'url' => ['ou-user']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="ldap-user-view">
    <!-- <h1><?= Html::encode($this->title) ?></h1> -->

    <p>
        <?= Html::a('กลับหน้า Update', ['update', 'cn' => $user['cn'][0]], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Move', ['move', 'cn' => $user['cn'][0]], ['class' => 'btn btn-warning']) ?>
        <?php
        // Check if account is currently disabled
        $userAccountControl = isset($user['useraccountcontrol'][0]) ? intval($user['useraccountcontrol'][0]) : 0;
        $ACCOUNTDISABLE = 0x0002;
        $isDisabled = ($userAccountControl & $ACCOUNTDISABLE);
        
        if ($isDisabled) {
            echo Html::a('Enable Account', ['toggle-status', 'cn' => $user['cn'][0], 'enable' => 1], [
                'class' => 'btn btn-success',
                'data' => [
                    'confirm' => 'Are you sure you want to enable this account?',
                    'method' => 'post',
                ],
            ]);
        } else {
            echo Html::a('Disable Account', ['toggle-status', 'cn' => $user['cn'][0], 'enable' => 0], [
                'class' => 'btn btn-danger',
                'data' => [
                    'confirm' => 'Are you sure you want to disable this account?',
                    'method' => 'post',
                ],
            ]);
        }
        ?>
        <!-- <button type="button" class="btn btn-danger" 
            data-bs-toggle="modal" 
            data-bs-target="#deleteUserModal"
            data-cn="<?= Html::encode($user['cn'][0]) ?>"
            data-username="<?= Html::encode(isset($user['samaccountname'][0]) ? $user['samaccountname'][0] : $user['cn'][0]) ?>">
            Delete
        </button> -->
    </p>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user me-2"></i>ข้อมูลพื้นฐาน</h5>
                </div>
                <div class="card-body">
                    <?= DetailView::widget([
                        'model' => $user,
                        'attributes' => [
                            ['label' => 'ชื่อผู้ใช้ (CN)', 'value' => $user['cn'][0] ?? ''],
                            ['label' => 'ชื่อแสดง (Display Name)', 'value' => $user['displayname'][0] ?? ''],
                            ['label' => 'คำนำหน้าชื่อ (personalTitle)', 'value' => $user['personaltitle'][0] ?? ''],
                            ['label' => 'ชื่อผู้ใช้ (SAM Account)', 'value' => $user['samaccountname'][0] ?? ''],
                            ['label' => 'ชื่อ ภาษาอังกฤษ (ยืมมาใช้จาก Given Name)', 'value' => $user['givenname'][0] ?? ''],
                            ['label' => 'นามสกุล (SN)', 'value' => $user['sn'][0] ?? ''],
                            ['label' => 'ชื่อย่อ (Initials)', 'value' => $user['initials'][0] ?? ''],
                            ['label' => 'ตำแหน่ง (ยืมมาใช้จาก Title)', 'value' => $user['title'][0] ?? ''],
                            ['label' => 'แผนก (Department)', 'value' => $user['department'][0] ?? ''],
                            ['label' => 'บริษัท (Company)', 'value' => $user['company'][0] ?? ''],
                            ['label' => 'อีเมล (Mail)', 'value' => $user['mail'][0] ?? ''],
                            ['label' => 'โทรศัพท์ (Telephone)', 'value' => $user['telephonenumber'][0] ?? ''],
                            ['label' => 'มือถือ (Mobile)', 'value' => $user['mobile'][0] ?? ''],
                            ['label' => 'Iphone', 'value' => $user['ipphone'][0] ?? ''],
                         
                        ]
                    ]) ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cog me-2"></i>สถานะบัญชี</h5>
                </div>
                <div class="card-body">
                    <?= DetailView::widget([
                        'model' => $user,
                        'attributes' => [
                            ['label' => 'สถานะบัญชี', 'value' => function($user) {
                                $userAccountControl = isset($user['useraccountcontrol'][0]) ? intval($user['useraccountcontrol'][0]) : 0;
                                $ACCOUNTDISABLE = 0x0002;
                                $status = ($userAccountControl & $ACCOUNTDISABLE) ? 'Disabled' : 'Enabled';
                                $badge = ($userAccountControl & $ACCOUNTDISABLE) ? 'danger' : 'success';
                                return "<span class='badge bg-$badge'>$status</span>";
                            }, 'format' => 'raw'],
                            ['label' => 'User Account Control', 'value' => $user['useraccountcontrol'][0] ?? ''],
                            ['label' => 'User Principal Name', 'value' => $user['userprincipalname'][0] ?? ''],
                            ['label' => 'Account Expires', 'value' => $user['accountexpires'][0] ?? ''],
                            ['label' => 'Password Last Set', 'value' => $user['pwdlastset'][0] ?? ''],
                            ['label' => 'Last Logon', 'value' => $user['lastlogon'][0] ?? ''],
                            ['label' => 'Last Logoff', 'value' => $user['lastlogoff'][0] ?? ''],
                            ['label' => 'Logon Count', 'value' => $user['logoncount'][0] ?? ''],
                            ['label' => 'Primary Group ID', 'value' => $user['primarygroupid'][0] ?? ''],
                            ['label' => 'SAM Account Type', 'value' => $user['samaccounttype'][0] ?? ''],
                         
                            ['label' => 'Homephone', 'value' => $user['homephone'][0] ?? ''],
                            ['label' => 'Pager', 'value' => $user['pager'][0] ?? ''],
                        ]
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-map-marker-alt me-2"></i>ที่อยู่</h5>
                </div>
                <div class="card-body">
                    <?= DetailView::widget([
                        'model' => $user,
                        'attributes' => [
                            ['label' => 'รายละเอียด(ยืม Street Address มาออก)', 'value' => $user['streetaddress'][0] ?? ''],
                            ['label' => 'เมือง (City)', 'value' => $user['l'][0] ?? ''],
                            ['label' => 'จังหวัด (State)', 'value' => $user['st'][0] ?? ''],
                            ['label' => 'รหัสไปรษณีย์ (Postal Code)', 'value' => $user['postalcode'][0] ?? ''],
                            ['label' => 'ตู้ไปรษณีย์ (Post Office Box)', 'value' => $user['postofficebox'][0] ?? ''],
                            ['label' => 'ประเทศ (Country Code)', 'value' => $user['countrycode'][0] ?? ''],
                            ['label' => 'สำนักงาน (ยืมมาใช้บันทึกเลขรหัสจากระบบ E-phis)', 'value' => $user['physicaldeliveryofficename'][0] ?? ''],
                        ]
                    ]) ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>ข้อมูลระบบ</h5>
                </div>
                <div class="card-body">
                    <?= DetailView::widget([
                        'model' => $user,
                        'attributes' => [
                            ['label' => 'Distinguished Name', 'value' => $user['distinguishedname'][0] ?? ''],
                            ['label' => 'When Created', 'value' => $user['whencreated'][0] ?? ''],
                            ['label' => 'When Changed', 'value' => $user['whenchanged'][0] ?? ''],
                            ['label' => 'USN Created', 'value' => $user['usncreated'][0] ?? ''],
                            ['label' => 'USN Changed', 'value' => $user['usnchanged'][0] ?? ''],
                            ['label' => 'Instance Type', 'value' => $user['instancetype'][0] ?? ''],
                            ['label' => 'Code Page', 'value' => $user['codepage'][0] ?? ''],
                            ['label' => 'WWW Homepage', 'value' => $user['wwwhomepage'][0] ?? ''],
                            ['label' => 'Job Title', 'value' => $user['jobtitle'][0] ?? ''],
                            ['label' => 'Description', 'value' => $user['description'][0] ?? ''],
                        ]
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>ข้อมูลเพิ่มเติม</h5>
                </div>
                <div class="card-body">
                    <?= DetailView::widget([
                        'model' => $user,
                        'attributes' => [
                            ['label' => 'Object Class', 'value' => function($user){
                                $classes = [];
                                if (isset($user['objectclass']) && is_array($user['objectclass'])) {
                                    foreach ($user['objectclass'] as $k => $v) {
                                        if (is_string($v)) { $classes[] = $v; }
                                        elseif (is_array($v) && isset($v[0]) && is_string($v[0])) { $classes[] = $v[0]; }
                                    }
                                }
                                return !empty($classes) ? implode(', ', $classes) : '';
                            }],
                            ['label' => 'Supported Encryption Types', 'value' => $user['msds-supportedencryptiontypes'][0] ?? ''],
                            ['label' => 'Name', 'value' => $user['name'][0] ?? ''],
                            ['label' => 'CO', 'value' => $user['co'][0] ?? ''],
                        ]
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Confirmation Modal -->
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
    const deleteButton = document.querySelector('button[data-bs-target="#deleteUserModal"]');
    const deleteModal = document.getElementById('deleteUserModal');
    const deleteUsername = document.getElementById('deleteUsername');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    
    if (deleteButton) {
        deleteButton.addEventListener('click', function() {
            const cn = this.getAttribute('data-cn');
            const username = this.getAttribute('data-username');
            
            // Update modal content
            deleteUsername.textContent = username;
            
            // Update delete button href with proper URL encoding
            const deleteUrl = '<?= Yii::$app->urlManager->createUrl(['ldapuser/delete']) ?>' + '&cn=' + encodeURIComponent(cn);
            confirmDeleteBtn.href = deleteUrl;
        });
    }
});
</script>

<style>
.ldap-user-view {
    padding: 20px;
}

.card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    border: none;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
}

.card-header h5 {
    margin: 0;
    color: #495057;
}

.card-body {
    padding: 20px;
}

.table th {
    background-color: #f8f9fa;
    border-top: none;
    font-weight: 600;
    color: #495057;
    width: 30%;
}

.table td {
    border-top: 1px solid #dee2e6;
    vertical-align: middle;
}

.badge {
    font-size: 0.875rem;
    padding: 0.5em 0.75em;
}

.btn {
    margin-right: 10px;
    margin-bottom: 10px;
}

.btn-primary {
    background-color: #007bff;
    border-color: #007bff;
}

.btn-warning {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}

.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.fas {
    margin-right: 5px;
}

.row {
    margin-bottom: 20px;
}

.mt-3 {
    margin-top: 1rem !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 15px;
    }
    
    .table th,
    .table td {
        padding: 8px;
        font-size: 0.9rem;
    }
}
</style>