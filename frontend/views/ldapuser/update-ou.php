<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\web\ForbiddenHttpException;

// Check if user is logged in
if (Yii::$app->user->isGuest) {
    throw new ForbiddenHttpException('You are not allowed to access this page. Please login first.');
}

$this->title = 'Update Organizational Unit';
$this->params['breadcrumbs'][] = ['label' => 'LDAP Management', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Manage OUs', 'url' => ['manage-ou']];
$this->params['breadcrumbs'][] = $this->title;

// Get LDAP helper instance
$ldapHelper = Yii::$app->ldap;
?>

<div class="row">
    <div class="col-md-8">
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit"></i> Update Organizational Unit
                </h3>
            </div>
            <div class="card-body">
                <?php $form = ActiveForm::begin(); ?>

                <div class="form-group">
                    <?= Html::label('OU Name', 'ou-name', ['class' => 'control-label']) ?>
                    <?= Html::textInput('ou-name', $ou['ou'], [
                        'class' => 'form-control',
                        'readonly' => true,
                        'id' => 'ou-name'
                    ]) ?>
                </div>

                <div class="form-group">
                    <?= Html::label('Distinguished Name', 'ou-dn', ['class' => 'control-label']) ?>
                    <?= Html::textInput('ou-dn', $ou['dn'], [
                        'class' => 'form-control',
                        'readonly' => true,
                        'id' => 'ou-dn'
                    ]) ?>
                </div>

                <div class="form-group">
                    <?= Html::label('Type', 'ou-type', ['class' => 'control-label']) ?>
                    <?= Html::textInput('ou-type', $ou['type'], [
                        'class' => 'form-control',
                        'readonly' => true,
                        'id' => 'ou-type'
                    ]) ?>
                </div>

                <div class="form-group">
                    <?= Html::label('Parent OU', 'ou-parent', ['class' => 'control-label']) ?>
                    <?= Html::textInput('ou-parent', $ou['parent'], [
                        'class' => 'form-control',
                        'readonly' => true,
                        'id' => 'ou-parent'
                    ]) ?>
                </div>

                <div class="form-group">
                    <?= Html::label('Description', 'ou-description', ['class' => 'control-label']) ?>
                    <?= Html::textarea('ou-description', $ou['description'], [
                        'class' => 'form-control',
                        'id' => 'ou-description',
                        'rows' => 3
                    ]) ?>
                </div>

                <div class="form-group">
                    <?= Html::label('Created', 'ou-created', ['class' => 'control-label']) ?>
                    <?= Html::textInput('ou-created', date('Y-m-d H:i', strtotime(preg_replace('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})\.0Z/', '$1-$2-$3 $4:$5:$6', $ou['created']))), [
                        'class' => 'form-control',
                        'readonly' => true,
                        'id' => 'ou-created'
                    ]) ?>
                </div>

                <div class="form-group">
                    <?= Html::label('Last Modified', 'ou-modified', ['class' => 'control-label']) ?>
                    <?= Html::textInput('ou-modified', date('Y-m-d H:i', strtotime(preg_replace('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})\.0Z/', '$1-$2-$3 $4:$5:$6', $ou['modified']))), [
                        'class' => 'form-control',
                        'readonly' => true,
                        'id' => 'ou-modified'
                    ]) ?>
                </div>

                <div class="form-group">
                    <?= Html::submitButton('<i class="fas fa-save"></i> Save Changes', [
                        'class' => 'btn btn-primary',
                        'data' => [
                            'confirm' => 'Are you sure you want to update this OU?',
                            'method' => 'post',
                        ],
                    ]) ?>
                    <?= Html::a('<i class="fas fa-times"></i> Cancel', ['manage-ou'], [
                        'class' => 'btn btn-default'
                    ]) ?>
                </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i> OU Information
                </h3>
            </div>
            <div class="card-body">
                <div class="info-box">
                    <span class="info-box-icon bg-info">
                        <i class="<?= $ou['icon'] ?>"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Users in this OU</span>
                        <span class="info-box-number"><?= count($ou['users']) ?></span>
                    </div>
                </div>

                <div class="mt-3">
                    <h5>Quick Actions</h5>
                    <div class="list-group">
                        <?= Html::a('<i class="fas fa-users"></i> View Users', ['ou-user', 'ou' => $ou['ou']], [
                            'class' => 'list-group-item list-group-item-action'
                        ]) ?>
                        <?= Html::a('<i class="fas fa-eye"></i> View Details', '#', [
                            'class' => 'list-group-item list-group-item-action',
                            'data' => [
                                'toggle' => 'modal',
                                'target' => '#viewOUModal'
                            ]
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Group Assignment Section -->
        <div class="card card-outline mt-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users-cog"></i> การกำหนดกลุ่ม (Group Assignment)
                </h3>
            </div>
            <div class="card-body">
                <!-- Add User to Group -->
                <div class="mb-3">
                    <label class="form-label"><strong>เพิ่มผู้ใช้เข้าไปในกลุ่ม</strong></label>
                    <input type="hidden" id="user-dn-input" value="<?= isset($userDn) ? Html::encode($userDn) : '' ?>">
                    <div class="input-group">
                        <input type="text" class="form-control" id="group-search-input" placeholder="ค้นหากลุ่ม...">
                        <button class="btn btn-primary" type="button" id="search-groups-btn">
                            <i class="fas fa-search"></i> ค้นหา
                        </button>
                    </div>
                    <div id="groups-list" class="mt-2" style="max-height: 200px; overflow-y: auto; display: none;"></div>
                    <div id="groups-error" class="alert alert-danger mt-2" style="display: none;"></div>
                </div>
                
                <hr>
                
                <!-- User's Current Groups -->
                <div>
                    <label class="form-label"><strong>กลุ่มที่ผู้ใช้เป็นสมาชิกอยู่</strong></label>
                    <div id="user-groups-list" class="mt-2">
                        <div class="text-muted text-center py-3">
                            <i class="fas fa-spinner fa-spin"></i> กำลังโหลดข้อมูล...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View OU Modal -->
<div class="modal fade" id="viewOUModal" tabindex="-1" aria-labelledby="viewOUModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewOUModalLabel">
                    <i class="fas fa-sitemap me-2"></i>OU Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="ou-details">
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">OU Name</label>
                        <div class="detail-value"><?= Html::encode($ou['ou']) ?></div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Distinguished Name</label>
                        <div class="detail-value"><?= Html::encode($ou['dn']) ?></div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Type</label>
                        <div class="detail-value">
                            <span class="badge badge-<?= $ou['badge'] ?>"><?= Html::encode($ou['type']) ?></span>
                        </div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Parent OU</label>
                        <div class="detail-value"><?= Html::encode($ou['parent'] ?? 'Root') ?></div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Description</label>
                        <div class="detail-value"><?= Html::encode($ou['description']) ?></div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Users</label>
                        <div class="detail-value"><?= count($ou['users']) ?> users</div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Created</label>
                        <div class="detail-value"><?= date('Y-m-d H:i', strtotime(preg_replace('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})\.0Z/', '$1-$2-$3 $4:$5:$6', $ou['created']))) ?></div>
                    </div>
                    <div class="detail-item">
                        <label class="text-muted mb-1">Last Modified</label>
                        <div class="detail-value"><?= date('Y-m-d H:i', strtotime(preg_replace('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})\.0Z/', '$1-$2-$3 $4:$5:$6', $ou['modified']))) ?></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.info-box {
    box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    border-radius: 0.25rem;
    background-color: #fff;
    display: flex;
    margin-bottom: 1rem;
    min-height: 80px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

.info-box-icon {
    border-radius: 0.25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    text-align: center;
    width: 70px;
    color: #fff;
}

.info-box-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.8;
    flex: 1;
    padding: 0 10px;
}

.info-box-text {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.info-box-number {
    display: block;
    font-weight: 700;
}

.detail-item {
    padding: 0.75rem;
    background-color: #fff;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.detail-item:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    transition: all 0.3s ease;
}

.detail-item label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #6c757d;
}

.detail-value {
    font-size: 1rem;
    color: #2c3e50;
    font-weight: 500;
}

.badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
    font-weight: 600;
}

.list-group-item {
    border: 1px solid rgba(0,0,0,.125);
    padding: 0.75rem 1.25rem;
    margin-bottom: -1px;
    background-color: #fff;
    color: #212529;
    text-decoration: none;
}

.list-group-item:hover {
    background-color: #f8f9fa;
    color: #212529;
    text-decoration: none;
}

.list-group-item i {
    margin-right: 0.5rem;
}
</style>

<script>
$(document).ready(function() {
    // ตรวจสอบว่ามี userDn หรือไม่ (อาจจะมาจาก URL parameter หรือ hidden input)
    var userDn = '';
    
    // ลองดึงจาก URL parameter
    var urlParams = new URLSearchParams(window.location.search);
    userDn = urlParams.get('userDn') || '';
    
    // ถ้ายังไม่มี ลองดึงจาก hidden input
    if (!userDn) {
        userDn = $('#user-dn-input').val() || '';
    }
    
    // ถ้ามี userDn ให้โหลดกลุ่มที่ผู้ใช้เป็นสมาชิกอยู่
    if (userDn) {
        loadUserGroups(userDn);
    } else {
        // ถ้าไม่มี userDn แสดงข้อความว่าไม่สามารถใช้งานได้
        $('#user-groups-list').html('<div class="text-muted text-center py-3"><i class="fas fa-info-circle"></i> กรุณาเลือกผู้ใช้เพื่อดูกลุ่ม</div>');
        // ซ่อนส่วนเพิ่มผู้ใช้เข้าไปในกลุ่ม
        $('#group-search-input, #search-groups-btn').prop('disabled', true);
    }
    
    // ค้นหากลุ่ม
    $('#search-groups-btn').on('click', function() {
        var searchTerm = $('#group-search-input').val().trim();
        if (searchTerm === '') {
            alert('กรุณากรอกชื่อกลุ่มที่ต้องการค้นหา');
            return;
        }
        searchGroups(searchTerm);
    });
    
    // ค้นหาเมื่อกด Enter
    $('#group-search-input').on('keypress', function(e) {
        if (e.which === 13) {
            $('#search-groups-btn').click();
        }
    });
    
    function searchGroups(searchTerm) {
        $('#groups-error').hide();
        $('#groups-list').html('<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> กำลังค้นหา...</div>').show();
        
        $.ajax({
            url: '<?= \yii\helpers\Url::to(['ldapuser/get-available-groups']) ?>',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.groups) {
                    var groups = response.groups;
                    var filteredGroups = groups.filter(function(group) {
                        return group.cn.toLowerCase().indexOf(searchTerm.toLowerCase()) !== -1;
                    });
                    
                    if (filteredGroups.length === 0) {
                        $('#groups-list').html('<div class="text-muted text-center py-2">ไม่พบกลุ่มที่ค้นหา</div>');
                    } else {
                        var html = '<div class="list-group">';
                        filteredGroups.forEach(function(group) {
                            html += '<div class="list-group-item d-flex justify-content-between align-items-center">';
                            html += '<div>';
                            html += '<strong>' + escapeHtml(group.cn) + '</strong>';
                            if (group.description) {
                                html += '<br><small class="text-muted">' + escapeHtml(group.description) + '</small>';
                            }
                            html += '</div>';
                            if (userDn) {
                                html += '<button class="btn btn-sm btn-primary add-to-group-btn" data-group-dn="' + escapeHtml(group.dn) + '" data-group-cn="' + escapeHtml(group.cn) + '">';
                                html += '<i class="fas fa-plus"></i> เพิ่ม';
                                html += '</button>';
                            }
                            html += '</div>';
                        });
                        html += '</div>';
                        $('#groups-list').html(html);
                        
                        // Bind click event สำหรับปุ่มเพิ่ม
                        $('.add-to-group-btn').on('click', function() {
                            var groupDn = $(this).data('group-dn');
                            var groupCn = $(this).data('group-cn');
                            addUserToGroup(userDn, groupDn, groupCn);
                        });
                    }
                } else {
                    $('#groups-error').text(response.message || 'เกิดข้อผิดพลาด: Failed to search groups').show();
                    $('#groups-list').hide();
                }
            },
            error: function(xhr, status, error) {
                $('#groups-error').text('เกิดข้อผิดพลาด: ' + error).show();
                $('#groups-list').hide();
            }
        });
    }
    
    function loadUserGroups(userDn) {
        $('#user-groups-list').html('<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> กำลังโหลดข้อมูล...</div>');
        
        $.ajax({
            url: '<?= \yii\helpers\Url::to(['ldapuser/get-user-groups']) ?>',
            method: 'GET',
            data: { userDn: userDn },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.groups) {
                    if (response.groups.length === 0) {
                        $('#user-groups-list').html('<div class="text-muted text-center py-3">ผู้ใช้ยังไม่ได้เป็นสมาชิกของกลุ่มใดๆ</div>');
                    } else {
                        var html = '<div class="list-group">';
                        response.groups.forEach(function(group) {
                            html += '<div class="list-group-item d-flex justify-content-between align-items-center">';
                            html += '<div>';
                            html += '<strong>' + escapeHtml(group.cn) + '</strong>';
                            if (group.description) {
                                html += '<br><small class="text-muted">' + escapeHtml(group.description) + '</small>';
                            }
                            html += '</div>';
                            html += '<button class="btn btn-sm btn-danger remove-from-group-btn" data-group-dn="' + escapeHtml(group.dn) + '" data-group-cn="' + escapeHtml(group.cn) + '">';
                            html += '<i class="fas fa-times"></i> ลบ';
                            html += '</button>';
                            html += '</div>';
                        });
                        html += '</div>';
                        $('#user-groups-list').html(html);
                        
                        // Bind click event สำหรับปุ่มลบ
                        $('.remove-from-group-btn').on('click', function() {
                            var groupDn = $(this).data('group-dn');
                            var groupCn = $(this).data('group-cn');
                            if (confirm('คุณต้องการลบผู้ใช้ออกจากกลุ่ม "' + groupCn + '" หรือไม่?')) {
                                removeUserFromGroup(userDn, groupDn, groupCn);
                            }
                        });
                    }
                } else {
                    $('#user-groups-list').html('<div class="text-danger text-center py-3">' + (response.message || 'ไม่สามารถโหลดข้อมูลกลุ่มได้') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#user-groups-list').html('<div class="text-danger text-center py-3">เกิดข้อผิดพลาด: ' + error + '</div>');
            }
        });
    }
    
    function addUserToGroup(userDn, groupDn, groupCn) {
        $.ajax({
            url: '<?= \yii\helpers\Url::to(['group/add-member']) ?>',
            method: 'POST',
            data: {
                userDn: userDn,
                groupDn: groupDn
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('เพิ่มผู้ใช้เข้าไปในกลุ่ม "' + groupCn + '" สำเร็จ');
                    loadUserGroups(userDn);
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (response.message || 'ไม่สามารถเพิ่มผู้ใช้เข้าไปในกลุ่มได้'));
                }
            },
            error: function(xhr, status, error) {
                alert('เกิดข้อผิดพลาด: ' + error);
            }
        });
    }
    
    function removeUserFromGroup(userDn, groupDn, groupCn) {
        $.ajax({
            url: '<?= \yii\helpers\Url::to(['group/remove-member']) ?>',
            method: 'POST',
            data: {
                userDn: userDn,
                groupDn: groupDn
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('ลบผู้ใช้ออกจากกลุ่ม "' + groupCn + '" สำเร็จ');
                    loadUserGroups(userDn);
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (response.message || 'ไม่สามารถลบผู้ใช้ออกจากกลุ่มได้'));
                }
            },
            error: function(xhr, status, error) {
                alert('เกิดข้อผิดพลาด: ' + error);
            }
        });
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script> 