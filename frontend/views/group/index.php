<?php
use yii\helpers\Html;
use common\components\PermissionManager;

/* @var $this yii\web\View */
/* @var $groups array */

$this->title = 'Group Management';
$this->params['breadcrumbs'][] = $this->title;
$pm = new PermissionManager();
$canCreate = $pm->hasPermission(PermissionManager::PERMISSION_GROUP_CREATE);
$canUpdate = $pm->hasPermission(PermissionManager::PERMISSION_GROUP_UPDATE);
$canDelete = $pm->hasPermission(PermissionManager::PERMISSION_GROUP_DELETE);
$canDeleteGroup = $pm->canDeleteGroup(); // Check specific group for delete permission
$canManageMembers = $pm->hasPermission(PermissionManager::PERMISSION_GROUP_MANAGE_MEMBERS);
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-users-cog me-2"></i>Group Management (CN=Users) - Security Group - Global Only</h3>
        <?php if ($canCreate): ?>
        <div class="ms-auto">
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                <i class="fas fa-plus me-1"></i>Create New Group
            </button>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width:60px" class="text-end">#</th>
                        <th>Group name (CN)</th>
                        <th>Description</th>
                        <th style="width:140px" class="text-end">Members</th>
                        <th style="width:220px">Manage Group</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; foreach ($groups as $g): ?>
                    <tr>
                        <td class="text-end"><?= $i++ ?></td>
                        <td><?= Html::encode($g['cn']) ?></td>
                        <td>
                            <?= Html::encode($g['description']) ?>
                        </td>
                        <td class="text-end"><span class="badge bg-info"><?= (int)$g['member_count'] ?></span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($canUpdate): ?>
                                <button class="btn btn-warning btn-edit-group" 
                                        data-dn="<?= Html::encode($g['dn']) ?>" 
                                        data-cn="<?= Html::encode($g['cn']) ?>" 
                                        data-description="<?= Html::encode($g['description']) ?>"
                                        data-bs-toggle="tooltip" 
                                        data-bs-placement="top" 
                                        title="Edit Group">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($canManageMembers): ?>
                                <button class="btn btn-info btn-manage-members" 
                                        data-dn="<?= Html::encode($g['dn']) ?>" 
                                        data-cn="<?= Html::encode($g['cn']) ?>"
                                        data-bs-toggle="tooltip" 
                                        data-bs-placement="top" 
                                        title="Manage Members">
                                    <i class="fas fa-user-friends"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for member management -->
<div class="modal fade" id="memberModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title"><i class="fas fa-user-friends me-2"></i>จัดการสมาชิกกลุ่ม: <span id="mmGroupCn"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-3" id="memberTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="view-members-tab" data-bs-toggle="tab" data-bs-target="#view-members" type="button" role="tab">
              <i class="fas fa-list me-1"></i>สมาชิกปัจจุบัน (<span id="memberCount">0</span>)
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="add-member-tab" data-bs-toggle="tab" data-bs-target="#add-member" type="button" role="tab">
              <i class="fas fa-user-plus me-1"></i>เพิ่มสมาชิก ราย User.
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="add-from-ou-tab" data-bs-toggle="tab" data-bs-target="#add-from-ou" type="button" role="tab">
              <i class="fas fa-sitemap me-1"></i>เพิ่มสมาชิก จาก OU
            </button>
          </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="memberTabsContent">
          <!-- View Members Tab -->
          <div class="tab-pane fade show active" id="view-members" role="tabpanel">
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>รายชื่อสมาชิกในกลุ่ม</strong>
                <div>
                  <button class="btn btn-sm btn-danger me-2" id="btnDeleteSelectedMembers" style="display:none;">
                    <i class="fas fa-trash me-1"></i>ลบที่เลือก
                  </button>
                  <button class="btn btn-sm btn-outline-primary" id="btnRefreshMembers">
                    <i class="fas fa-sync-alt me-1"></i>รีเฟรช
                  </button>
                </div>
              </div>
              <div id="membersListContainer">
                <div class="text-center py-3">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">กำลังโหลด...</span>
                  </div>
                  <p class="mt-2 text-muted">กำลังโหลดรายชื่อสมาชิก...</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Add Member Tab -->
          <div class="tab-pane fade" id="add-member" role="tabpanel">
            <div class="mb-3">
              <label class="form-label"><strong>ค้นหาผู้ใช้</strong></label>
              <div class="input-group">
                <input type="text" class="form-control" id="userSearchInput" placeholder="ค้นหาด้วยชื่อ, CN, username, แผนก...">
                <button class="btn btn-primary" id="btnSearchUsers">
                  <i class="fas fa-search me-1"></i>ค้นหา
                </button>
              </div>
              <small class="text-muted">พิมพ์ชื่อ, CN, username, หรือแผนกเพื่อค้นหา</small>
            </div>
            
            <div id="searchResultsContainer" style="max-height: 400px; overflow-y: auto;">
              <p class="text-muted text-center py-3">กรุณาค้นหาผู้ใช้เพื่อเพิ่มเป็นสมาชิก</p>
            </div>
          </div>

          <!-- Add from OU Tab -->
          <div class="tab-pane fade" id="add-from-ou" role="tabpanel">
            <div class="mb-3">
              <label class="form-label"><strong>เลือก Organizational Unit (OU)</strong></label>
              <div class="input-group mb-2">
                <select class="form-select" id="ouSelect">
                  <option value="">-- เลือก OU --</option>
                </select>
                <button class="btn btn-outline-secondary" id="btnRefreshOus" title="รีเฟรชรายชื่อ OU">
                  <i class="fas fa-sync-alt"></i>
                </button>
                <button class="btn btn-primary" id="btnAddSelectedUsers" style="display:none;">
                  <i class="fas fa-user-plus me-1"></i>เพิ่มผู้ใช้ที่เลือก
                </button>
              </div>
              <small class="text-muted">เลือก OU เพื่อดูรายชื่อผู้ใช้ใน OU นั้น</small>
              <div class="mt-2">
                <span class="text-muted" id="selectedCount" style="display:none;">เลือกแล้ว: 0 คน</span>
              </div>
            </div>
            
            <div id="ouUsersContainer" style="max-height: 400px; overflow-y: auto;">
              <p class="text-muted text-center py-3">กรุณาเลือก OU เพื่อดูรายชื่อผู้ใช้</p>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal for creating new group -->
<div class="modal fade" id="createGroupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Create New Group</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="createGroupForm">
          <div class="mb-3">
            <label for="groupName" class="form-label">
              Group Name <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" id="groupName" name="cn" placeholder="Enter group name" required>
            <small class="text-muted">Group Name must be unique. This will be used as the Common Name (CN) in Active Directory.</small>
            <div class="invalid-feedback" id="groupNameError"></div>
          </div>
          
          <div class="mb-3">
            <label for="groupDescription" class="form-label">
              Description <span class="text-danger">*</span>
            </label>
            <textarea class="form-control" id="groupDescription" name="description" rows="3" placeholder="Enter group description" required></textarea>
            <small class="text-muted">Provide a description for this group.</small>
            <div class="invalid-feedback" id="groupDescriptionError"></div>
          </div>
          
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Group Type:</strong> Security Group - Global (automatically assigned)
            <br>
            <strong>Object Class:</strong> Group (Active Directory)
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnCreateGroup">
          <i class="fas fa-save me-1"></i>Create Group
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal for editing group -->
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Group</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editGroupForm">
          <input type="hidden" id="editGroupDn" name="dn">
          
          <div class="mb-3">
            <label for="editGroupName" class="form-label">
              Group Name (CN)
            </label>
            <input type="text" class="form-control" id="editGroupName" name="cn" readonly disabled>
            <small class="text-muted">Group Name cannot be changed after creation.</small>
          </div>
          
          <div class="mb-3">
            <label for="editGroupDescription" class="form-label">
              Description <span class="text-danger">*</span>
            </label>
            <textarea class="form-control" id="editGroupDescription" name="description" rows="3" placeholder="Enter group description" required></textarea>
            <div class="invalid-feedback" id="editGroupDescriptionError"></div>
          </div>
          
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Note:</strong> Only the description can be modified. Group Name and Group Type cannot be changed.
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <?php if ($canDeleteGroup): // แสดงปุ่ม Delete Group เฉพาะ user ใน group CN=manage Ad_admin,CN=Users-RPP,DC=rpphosp,DC=local ?>
        <button type="button" class="btn btn-danger me-auto" id="btnDeleteGroupInModal">
          <i class="fas fa-trash me-1"></i>Delete Group
        </button>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="btnUpdateGroup">
          <i class="fas fa-save me-1"></i>Update Group
        </button>
      </div>
    </div>
  </div>
</div>

<?php
// ส่งข้อมูลที่จำเป็นไปยัง JavaScript ผ่าน data attributes
$jsConfig = [
    'csrfParam' => Yii::$app->request->csrfParam,
    'csrfToken' => Yii::$app->request->getCsrfToken(),
    'urls' => [
        'create' => Yii::$app->urlManager->createUrl(['group/create']),
        'update' => Yii::$app->urlManager->createUrl(['group/update']),
        'delete' => Yii::$app->urlManager->createUrl(['group/delete']),
        'getMembers' => Yii::$app->urlManager->createUrl(['group/get-members']),
        'searchUsers' => Yii::$app->urlManager->createUrl(['group/search-users']),
        'addMember' => Yii::$app->urlManager->createUrl(['group/add-member']),
        'removeMember' => Yii::$app->urlManager->createUrl(['group/remove-member']),
        'getOus' => Yii::$app->urlManager->createUrl(['group/get-ous']),
        'getUsersByOu' => Yii::$app->urlManager->createUrl(['group/get-users-by-ou']),
    ],
];
$this->registerJs('var groupManagementConfig = ' . json_encode($jsConfig) . ';', \yii\web\View::POS_HEAD);
$this->registerJsFile('@web/js/group-management.js', ['depends' => [\yii\web\JqueryAsset::class]]);
?>
