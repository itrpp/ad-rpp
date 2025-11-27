<?php
use yii\helpers\Html;
use yii\web\ForbiddenHttpException;
use yii\widgets\ActiveForm;
use common\models\User;
use yii\base\BaseObject;
use common\components\PermissionManager;

// Register CSS and JS assets
$this->registerCssFile('@web/css/ou-user.css');
$this->registerJsFile('@web/js/ou-user-utils.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/ou-user.js', ['depends' => [\yii\web\JqueryAsset::class]]);

// Check if user is logged in
if (Yii::$app->user->isGuest) {
    throw new ForbiddenHttpException('You are not allowed to access this page. Please login first.');
}

// Check if user has view LDAP permission (admin or superuser with view)
$permissionManager = new PermissionManager();
$canViewLdapUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_VIEW);
if (!$canViewLdapUsers) {
    throw new ForbiddenHttpException('คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้ (ต้องมีสิทธิ์ดูข้อมูลผู้ใช้ LDAP)');
}

// Check specific permissions for action buttons
$user = Yii::$app->user->identity;
$isAdmin = $permissionManager->isLdapAdmin();
$isSuperUser = $permissionManager->isSuperUser();
$canUpdateUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_UPDATE);
$canMoveUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_MOVE);
$canToggleStatus = $permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_TOGGLE_STATUS);

$this->title = 'รายชื่อผู้ใช้งานทั้งหมด';
$this->params['breadcrumbs'][] = $this->title;

// Get current user's OU
$currentUserOu = Yii::$app->session->get('ldapUserData')['ou'] ?? 'rpp-user';

// Include sidebar
// echo $this->render('//layouts/_sidebar');
?>

<!-- Main Content -->
<div class="row"> 
    <div class="col-12">
        <!-- Domain Statistics -->
        <?php if (!empty($ouStats)): ?>
        <!-- <div class="row mb-4">
            <div class="col-12">
                <div class="card card-outline">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-chart-bar me-2"></i>Domain Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($ouStats as $stat): ?>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="info-box">
                                    <span class="info-box-icon <?= $stat['badge'] ?>">
                                        <i class="<?= $stat['icon'] ?>"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text"><?= Html::encode($stat['ou']) ?></span>
                                        <span class="info-box-number"><?= $stat['user_count'] ?></span>
                                        <small class="text-muted"><?= Html::encode($stat['type']) ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div> -->
        <?php endif; ?>
        
        <!-- OU Filter moved to User Search section; OU search removed -->

        <!-- OU Structure Card -->
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-globe"></i> ค้นหาผู้ใช้งานทั้งหมด
                </h3>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 320px;">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="userSearch" class="form-control float-right border-start-0" id="userSearch" placeholder="Search users by name, username, email, department or title..." aria-label="Search users">
                        <button type="button" class="btn btn-outline-secondary" id="clearUserSearch" aria-label="Clear search" title="Clear">
                            <i class="fas fa-times"></i>
                        </button>
                        <button type="button" class="btn btn-primary" id="searchButton" aria-label="Search">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                                <?php 
                $allUsers = [];
                if (!empty($allDomainUsers) && is_array($allDomainUsers)) {
                    // Use all domain users directly
                    foreach ($allDomainUsers as $user) {
                        // Extract OU information from user's DN
                        $userDn = $user['distinguishedname'] ?? '';
                        $ouDn = '';
                        if (preg_match('/OU=([^,]+)/', $userDn, $matches)) {
                            $ouDn = $matches[1];
                        }
                        $allUsers[] = ['ou_dn' => $ouDn, 'user' => $user];
                    }
                } elseif (!empty($ouUsers) && is_array($ouUsers)) {
                    // Fallback to original method
                    foreach ($ouUsers as $ouDn => $users) {
                        if (empty($users)) { continue; }
                        foreach ($users as $user) {
                            $allUsers[] = ['ou_dn' => $ouDn, 'user' => $user];
                        }
                    }
                }

                // Dedupe by sAMAccountName; pick entry with deepest OU in DN, tie-break by DN length
                // Also filter out users from Server OU
                $deduped = [];
                foreach ($allUsers as $entry) {
                    $user = $entry['user'];
                    $key = strtolower($user['samaccountname'] ?? '');
                    if ($key === '') { continue; }
                    
                    // Filter out users from Server OU and IT OU
                    $userDn = $user['distinguishedname'] ?? '';
                    if (stripos($userDn, 'OU=Server') !== false) { continue; }
                    if (stripos($userDn, 'OU=rpp-computer') !== false) { continue; }
                    // if (stripos($userDn, 'OU=IT') !== false) { continue; }
                    if (stripos($userDn, 'OU=Vichakarn') !== false) { continue; }
                    if (stripos($userDn, 'OU=Domain Controllers') !== false) { continue; }
                    if (stripos($userDn, 'OU=Login-Connection') !== false) { continue; }
                    // Filter out users from rpp-register OU
                    if (stripos($userDn, 'OU=rpp-register') !== false) { continue; }

                    
                    $dn = strtoupper($userDn);
                    $depth = substr_count($dn, 'OU=');
                    $dnlen = strlen($dn);
                    if (!isset($deduped[$key])) {
                        $deduped[$key] = ['entry' => $entry, 'depth' => $depth, 'dnlen' => $dnlen];
                                        } else {
                        $cur = $deduped[$key];
                        if ($depth > $cur['depth'] || ($depth === $cur['depth'] && $dnlen > $cur['dnlen'])) {
                            $deduped[$key] = ['entry' => $entry, 'depth' => $depth, 'dnlen' => $dnlen];
                        }
                    }
                }
                $finalUsers = [];
                foreach ($deduped as $k => $v) { $finalUsers[] = $v['entry']; }
                
                // Sort by most recently updated (whenchanged) first
                usort($finalUsers, function($a, $b) {
                    $ua = $a['user'] ?? [];
                    $ub = $b['user'] ?? [];
                    $va = $ua['whenchanged'] ?? '';
                    $vb = $ub['whenchanged'] ?? '';
                    // Handle array values from LDAP
                    if (is_array($va)) { $va = $va[0] ?? ''; }
                    if (is_array($vb)) { $vb = $vb[0] ?? ''; }
                    // Keep digits only (YYYYMMDDHHMMSS)
                    $wa = preg_replace('/[^0-9]/', '', (string)$va);
                    $wb = preg_replace('/[^0-9]/', '', (string)$vb);
                    // Ensure 14-digit comparable strings; pad/truncate if necessary
                    $wa = substr(str_pad($wa, 14, '0'), 0, 14);
                    $wb = substr(str_pad($wb, 14, '0'), 0, 14);
                    if ($wa === $wb) { return 0; }
                    return ($wa < $wb) ? 1 : -1; // DESC (newest first)
                });
                ?>
                <?php if (!empty($finalUsers)): ?>
                <div class="ou-users">
                    <h5 class="mb-3">
                        <i class="fas fa-users me-2"></i>Users ทั้งหมด
                        <span class="badge badge-info ms-2" id="filteredCount" data-total="<?= count($finalUsers) ?>"><?= count($finalUsers) ?> คน</span>
                    </h5>
                    
                    <!-- OU Filter Section -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-sitemap"></i></span>
                                <select class="form-select" id="ouFilter" aria-label="Filter by OU">
                                    <option value="">- เลือก OU -</option>
                                    <?php if (!empty($ouStats)): ?>
                                        <?php
                                        // Build flattened hierarchical list with indentation
                                        $ouItems = [];
                                        $seenByDn = [];
                                        foreach ($ouStats as $stat) {
                                            if (strcasecmp($stat['ou'], 'rpp-user') === 0) { continue; } // exclude rpp-user
                                            if (strcasecmp($stat['ou'], 'Server') === 0) { continue; } // exclude Server OU
                                            // if (strcasecmp($stat['ou'], 'IT') === 0) { continue; } // exclude IT OU
                                            if (strcasecmp($stat['ou'], 'Vichakarn') === 0) { continue; } // exclude Vichakarn OU
                                            if (strcasecmp($stat['ou'], 'Domain Controllers') === 0) { continue; } // exclude Domain Controllers OU
                                            if (strcasecmp($stat['ou'], 'Login-Connection') === 0) { continue; } // exclude Login-Connection OU
                                            if (strcasecmp($stat['ou'], 'test') === 0) { continue; } // exclude Register OU

                                            if (strcasecmp($stat['ou'], 'rpp-computer') === 0) { continue; } // exclude rpp-computer OU
                                            // Exclude specific entry path: rpphosp.local//rpp-computer
                                            if (isset($stat['dn']) && stripos($stat['dn'], 'rpphosp.local//rpp-computer') !== false) { continue; }
                                       
                                            if (strcasecmp($stat['ou'], 'rpp-register') === 0 || stripos($stat['dn'], 'OU=rpp-register') !== false) { continue; } // exclude rpp-register OU
                                            if (strcasecmp($stat['ou'], 'rpp-computer') === 0 || stripos($stat['dn'], 'OU=rpp-computer') !== false) { continue; } // exclude rpp-register OU
                                            $dn = $stat['dn'];
                                            if (isset($seenByDn[$dn])) { continue; } // avoid duplicates by DN
                                            $seenByDn[$dn] = true;

                                            $dnParts = explode(',', $dn);
                                            $ouPath = [];
                                            foreach ($dnParts as $part) {
                                                if (strpos($part, 'OU=') === 0) {
                                                    $ouName = substr($part, 3);
                                                    $ouPath[] = $ouName;
                                                }
                                            }

        
                                            $reversed = array_reverse($ouPath); // parent -> child order
                                            $hierarchicalPath = implode(' / ', $reversed);
                                            // Clean label without any decorative prefix
                                            $label = ($hierarchicalPath !== '') ? $hierarchicalPath : $stat['ou'];

                                            $ouItems[] = [
                                                'value' => $hierarchicalPath !== '' ? $hierarchicalPath : $stat['ou'],
                                                'label' => $label,
                                                'dn' => $dn,
                                                'ou' => $stat['ou'],
                                                'count' => $stat['user_count'],
                                            ];
                                        }

                                        // Sort by hierarchical path label
                                        usort($ouItems, function($a, $b) {
                                            return strcasecmp($a['label'], $b['label']);
                                        });
                                        ?>
                                        <?php foreach ($ouItems as $item): ?>
                                            <option value="<?= Html::encode($item['value']) ?>" data-ou="<?= Html::encode($item['ou']) ?>" data-dn="<?= Html::encode($item['dn']) ?>" data-path="<?= Html::encode($item['value']) ?>">
                                                <?= Html::encode($item['label']) ?> (<?= (int)$item['count'] ?> users)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="clearOuFilter" aria-label="Clear OU filter" title="Clear OU Filter">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Filter users by Organizational Unit
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                                <?php 
                        // Build OU dropdown options from final users with hierarchical paths
                        $uniqueOuPaths = [];
                        foreach ($finalUsers as $entry) {
                            $u = $entry['user'];
                            $ouDnTmp = $entry['ou_dn'];
                            $userDnTmp = $u['distinguishedname'] ?? $ouDnTmp;
                            $dnPartsTmp = array_map('trim', explode(',', (string)$userDnTmp));
                            
                            // Extract OU path
                            $ouPathTmp = [];
                            foreach ($dnPartsTmp as $part) {
                                if (stripos($part, 'OU=') === 0) {
                                    $ouName = substr($part, 3);
                                    $ouPathTmp[] = $ouName;
                                }
                            }
                            
                            if (!empty($ouPathTmp)) {
                                $ouPathTmp = array_reverse($ouPathTmp); // Reverse to get parent -> child order
                                if (count($ouPathTmp) > 1) {
                                    $ouDisplayTmp = $ouPathTmp[0] . ' / ' . $ouPathTmp[1];
                                } else {
                                    $ouDisplayTmp = $ouPathTmp[0];
                                }
                                $uniqueOuPaths[$ouDisplayTmp] = true;
                            } elseif (!empty($u['ou'])) {
                                $uniqueOuPaths[$u['ou']] = true;
                            }
                        }
                        $ouNameList = array_keys($uniqueOuPaths);
                        sort($ouNameList, SORT_NATURAL | SORT_FLAG_CASE);
                        ?>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                    <th style="width: 60px" class="text-end sortable" data-sort-key="row" aria-sort="asc">No.</th>
                                    <th style="width: 40px"class="sortable" data-sort-key="username" aria-sort="none">Username <span class="sort-icon"><i class="fas fa-sort"></i></span></th>
                                    <th style="width: 180px"class="sortable" data-sort-key="cn" aria-sort="none">ชื่อ-นามสกุล (CN) <span class="sort-icon"><i class="fas fa-sort"></i></span></th>
                                    <th class="sortable" data-sort-key="department" aria-sort="none">หน่วยงาน <span class="sort-icon"><i class="fas fa-sort"></i></span></th>
                                    <th class="sortable" data-sort-key="department" aria-sort="none">ตำแหน่ง <span class="sort-icon"><i class="fas fa-sort"></i></span></th>
                                    <th style="width: 160px" class="sortable" data-sort-key="whenchanged" aria-sort="none">วันที่แก้ไขล่าสุด <span class="sort-icon"><i class="fas fa-sort"></i></span></th>
                                    <th style="width: 100px" class="text-center">สถานะ</th>
                                    <th style="width: 150px">Actions</th>
                                        </tr>
                                <tr class="filter-row">
                                    <th></th>
                                    <th>
                                        <input id="filterUsername" type="text" class="form-control form-control-sm" placeholder="Search username">
                                    </th>
                                    <th>
                                        <input id="filterCn" type="text" class="form-control form-control-sm" placeholder="Search CN">
                                    </th>
                                    <th>
                                        <input id="filterDepartment" type="text" class="form-control form-control-sm" placeholder="Search department">
                                    </th>
                                    <th>
                                        <input id="filterTitle" type="text" class="form-control form-control-sm" placeholder="Search title">
                                    </th>
                                    <th></th>
                                    <th></th>
                                </tr>
                                    </thead>
                                    <tbody>
                                <?php $counter = 1; ?>
                                <?php foreach ($finalUsers as $entry): ?>
                                        <?php 
                                $user = $entry['user']; 
                                $ouDn = $entry['ou_dn']; 
                                $userDn = $user['distinguishedname'] ?? $ouDn; 
                                $whenCreated = $user['whencreated'] ?? '';
                                $userAccountControl = isset($user['useraccountcontrol']) ? intval($user['useraccountcontrol']) : 0;
                                $ACCOUNTDISABLE = 0x0002;
                                $isDisabled = ($userAccountControl & $ACCOUNTDISABLE) ? true : false;
                                // Extract OU path from user's DN
                                $ouDisplay = '';
                                $ouPath = [];
                                $dnParts = array_map('trim', explode(',', (string)$userDn));
                                
                                // Collect all OUs in the DN
                                foreach ($dnParts as $part) {
                                    if (stripos($part, 'OU=') === 0) {
                                        $ouName = substr($part, 3);
                                        $ouPath[] = $ouName;
                                    }
                                }
                                
                                // Create hierarchical path
                                if (!empty($ouPath)) {
                                    $ouPath = array_reverse($ouPath); // Reverse to get parent -> child order
                                    if (count($ouPath) > 1) {
                                        // Show parent / child format
                                        $ouDisplay = $ouPath[0] . ' / ' . $ouPath[1];
                                    } else {
                                        $ouDisplay = $ouPath[0];
                                    }
                                }
                                
                                // Fallback to user's ou attribute
                                if ($ouDisplay === '' && !empty($user['ou'])) {
                                    $ouDisplay = $user['ou'];
                                }
                                ?>
                                <tr class="user-row" 
                                    data-ou="<?= Html::encode($userDn) ?>"
                                    data-oupath="<?= Html::encode(implode(' / ', array_reverse(array_values(array_filter(array_map(function($p){ return stripos($p, 'OU=') === 0 ? substr($p, 3) : null; }, $dnParts)))))) ?>"
                                    data-username="<?= Html::encode($user['samaccountname']) ?>"
                                    data-cn="<?= Html::encode($user['cn']) ?>"
                                    data-displayname="<?= Html::encode($user['displayname']) ?>"
                                    data-department="<?= Html::encode($user['department']) ?>"
                                    data-title="<?= Html::encode($user['title'] ?? '') ?>"
                                    data-email="<?= Html::encode($user['mail']) ?>"
                                    data-whencreated="<?= Html::encode($whenCreated) ?>"
                                    data-status="<?= $isDisabled ? 'disabled' : 'enabled' ?>"
                                    data-disabled="<?= $isDisabled ? '1' : '0' ?>"
                                    data-ouname="<?= Html::encode($ouDisplay) ?>"
                                    data-rowindex="<?= $counter ?>"
                                >
                                    <td class="text-end"><?= $counter ?></td>
                                            <td><?= Html::encode($user['samaccountname']) ?></td>
                                            <td><?= Html::encode($user['cn']) ?></td>
                                            <td><?= Html::encode($user['department']) ?></td>
                                            <td><?= Html::encode($user['title'] ?? 'ยังไม่ระบ') ?></td>
                                            <td>
                                                <?php
                                                // ใช้รูปแบบเดียวกับ update.php แต่เพิ่มความทนทานของรูปแบบข้อมูลจาก LDAP
                                                $whenChangedThai = 'ยังไม่ระบุ';
                                                $wc = $user['whenchanged'] ?? '';
                                                if (is_array($wc)) { $wc = $wc[0] ?? ''; }
                                                $wc = trim((string)$wc);
                                                if ($wc !== '') {
                                                    // พยายามจับกลุ่ม 8+6 หลักแบบไม่ anchor เผื่อมีอักขระแทรก
                                                    if (!preg_match('/(\d{8})(\d{6})/', $wc, $m2)) {
                                                        // fallback: เก็บเฉพาะตัวเลขแล้วจับใหม่
                                                        $digits = preg_replace('/[^0-9]/', '', $wc);
                                                        preg_match('/(\d{8})(\d{6})/', $digits, $m2);
                                                    }
                                                    if (!empty($m2)) {
                                                        $dt2 = \DateTime::createFromFormat('YmdHis', $m2[1] . $m2[2], new \DateTimeZone('UTC'));
                                                        if ($dt2 !== false) {
                                                            $dt2->setTimezone(new \DateTimeZone('Asia/Bangkok'));
                                                            $thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                                                            $d2 = (int)$dt2->format('j');
                                                            $mIndex2 = (int)$dt2->format('n');
                                                            $y2 = (int)$dt2->format('Y') + 543;
                                                            $time2 = $dt2->format('H:i');
                                                            $whenChangedThai = $d2 . ' ' . $thaiMonths[$mIndex2 - 1] . ' ' . $y2 . ' ' . $time2 . ' น.';
                                                        }
                                                    }
                                                }
                                                echo Html::encode($whenChangedThai);
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($canToggleStatus): ?>
                                                    <div class="form-check form-switch toggle-status-wrapper d-inline-block">
                                                        <input class="form-check-input toggle-status-switch" 
                                                            type="checkbox" 
                                                            role="switch"
                                                            id="statusSwitch-<?= $counter ?>"
                                                            data-cn="<?= Html::encode($user['cn']) ?>" 
                                                            data-samaccountname="<?= Html::encode($user['samaccountname']) ?>"
                                                            data-enable="<?= $isDisabled ? '1' : '0' ?>" 
                                                            data-current-status="<?= $isDisabled ? 'disabled' : 'enabled' ?>"
                                                            <?= !$isDisabled ? 'checked' : '' ?>
                                                            title="<?= Html::encode($user['displayname'] ?: $user['samaccountname']) ?> - <?= $isDisabled ? 'ถูกปิดอยู่ (Disabled)' : 'เปิดอยู่ (Enabled)' ?>">
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge <?= $isDisabled ? 'bg-danger' : 'bg-success' ?>">
                                                        <?= $isDisabled ? 'Disabled' : 'Enabled' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info view-user" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewUserModal"
                                                        data-user='<?= json_encode([
                                                            'displayname' => $user['displayname'],
                                                            'email' => $user['mail'] ?? '',
                                                            'telephone' => $user['telephonenumber'] ?? '',
                                                            'streetaddress' => $user['streetaddress'] ?? '',
                                                            'office' => $user['physicaldeliveryofficename'] ?? '',
                                                            'postalcode' => $user['postalcode'] ?? '',
                                                            'company' => $user['company'] ?? '',
                                                            'status' => ($isDisabled ? 'Disabled' : 'Enabled'),
                                                            'ou' => $userDn
                                                        ]) ?>'
                                                        title="ดูข้อมูลผู้ใช้: <?= Html::encode($user['displayname'] ?: $user['samaccountname']) ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($canUpdateUsers): ?>
                                                    <?= Html::a('<i class="fas fa-edit"></i>', ['update', 'cn' => $user['cn']], [
                                                        'class' => 'btn btn-sm btn-primary',
                                                        'title' => 'แก้ไขข้อมูลผู้ใช้: ' . Html::encode($user['displayname'] ?: $user['samaccountname']),
                                                        'data' => [
                                                            'toggle' => 'modal',
                                                            'target' => '#updateUserModal',
                                                            'user' => json_encode([
                                                                'cn' => $user['cn'],
                                                                'username' => $user['samaccountname'],
                                                                'displayname' => $user['displayname'],
                                                                'department' => $user['department'],
                                                                'title' => $user['title'] ?? '',
                                                                'email' => $user['mail'],
                                                        'ou' => $userDn
                                                            ])
                                                        ]
                                                    ]) ?>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($canMoveUsers): ?>
                                                    <?= Html::a('<i class="fas fa-exchange-alt"></i>', ['move', 'cn' => $user['cn']], ['class' => 'btn btn-sm btn-warning', 'title' => 'ย้ายผู้ใช้: ' . Html::encode($user['displayname'] ?: $user['samaccountname'])]) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php $counter++; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="table-pagination mt-3">
                                <div class="d-flex justify-content-between align-items-center">
                            <div class="pagination-info" id="paginationInfo"></div>
                            <div class="pagination-buttons" id="paginationButtons"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Notification Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;">
    <div id="statusToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="fas fa-check-circle text-success me-2"></i>
            <strong class="me-auto">Status Update</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage">
            <!-- Message will be inserted here -->
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewUserModalLabel">
                    <i class="fas fa-user-circle me-2"></i>รายละเอียดผู้ใช้เพิ่มเติม
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="user-details">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item mb-3">
                                <label class="text-muted mb-1">ชื่อแสดง </label>
                                <div class="detail-value" id="modalDisplayName"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-item mb-3">
                                <label class="text-muted mb-1">อีเมล </label>
                                <div class="detail-value" id="modalEmail"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item mb-3">
                                <label class="text-muted mb-1">โทรศัพท์ </label>
                                <div class="detail-value" id="modalTelephone"></div>
                            </div>
                        </div>
                           
              
                        <div class="col-6">
                            <div class="detail-item mb-3">
                                <label class="text-muted mb-1">ผู้ติดต่อ</label>
                                <div class="detail-value" id="modalCompany"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item mb-3">
                                <label class="text-muted mb-1">เลข E-phis</label>
                                <div class="detail-value" id="modalOffice"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-item mb-3">
                                <label class="text-muted mb-1">  Postalcode เลขที่บัตรประชาชน</label>
                                <div class="detail-value" id="modalPostalcode"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="detail-item mb-3">
                                <label class="text-muted mb-1">OU</label>
                                <div class="detail-value" id="modalOu"></div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="detail-item mb-3">
                                <label class="text-muted mb-1">รายละเอียด </label>
                                <div class="detail-value" id="modalStreetAddress"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Update User Modal -->
<div class="modal fade" id="updateUserModal" tabindex="-1" aria-labelledby="updateUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="updateUserModalLabel">
                    <i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลผู้ใช้เพิ่มเติม
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <?php $form = ActiveForm::begin([
                'id' => 'update-user-form',
                'action' => ['update'],
                'method' => 'post',
                'enableClientValidation' => true,
            ]); ?>
            <div class="modal-body">
                <input type="hidden" name="cn" id="updateCn">
                <div class="mb-3">
                    <label for="updateUsername" class="form-label">User</label>
                    <input type="text" class="form-control" id="updateUsername" name="sAMAccountName" required>
                </div>
                <div class="mb-3">
                    <label for="updateDisplayName" class="form-label">Display Name</label>
                    <input type="text" class="form-control" id="updateDisplayName" name="displayName" required>
                </div>
                <div class="mb-3">
                    <label for="updateDepartment" class="form-label">Department</label>
                    <input type="text" class="form-control" id="updateDepartment" name="department" required>
                </div>
                <div class="mb-3">
                    <label for="updatePosition" class="form-label">ตำแหน่ง</label>
                    <input type="text" class="form-control" id="updateTitle" name="title" required>
                </div>
                
                <div class="mb-3">
                    <label for="updateEmail" class="form-label">Email</label>
                    <input type="email" class="form-control" id="updateEmail" name="mail" required>
                </div>
                <div class="mb-3">
                    <label for="updatePassword" class="form-label">New Password (Optional)</label>
                    <input type="password" class="form-control" id="updatePassword" name="password">
                    <small class="text-muted">Leave blank to keep current password</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

