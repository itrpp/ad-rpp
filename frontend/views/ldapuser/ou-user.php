<?php
use yii\helpers\Html;
use yii\web\ForbiddenHttpException;
use yii\widgets\ActiveForm;
use yii\widgets\LinkPager;
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

// Helper: ดึงค่า attribute จาก user (รองรับ key ไม่สนใจตัวพิมพ์ และค่าที่เป็น array จาก LDAP)
$getUserAttr = function (array $user, $key) {
    $keyLower = strtolower((string)$key);
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
                    <?php
                    $searchValue = isset($search) ? (string)$search : '';
                    $currentPerPage = (int) Yii::$app->request->get('per-page', 20);
                    $currentPerPage = ($currentPerPage >= 5 && $currentPerPage <= 100) ? $currentPerPage : 20;
                    $currentOuPath = (string) Yii::$app->request->get('ou', '');

                    // Helper สำหรับทำ highlight คำค้นในผลลัพธ์
                    $highlightSearch = function ($text) use ($searchValue) {
                        $text = (string)$text;
                        if ($searchValue === '' || $text === '') {
                            return Html::encode($text);
                        }
                        $encodedText = Html::encode($text);
                        $encodedSearch = Html::encode($searchValue);
                        // ใช้ str_ireplace เพื่อไม่สนตัวพิมพ์เล็ก/ใหญ่
                        return str_ireplace(
                            $encodedSearch,
                            '<mark>' . $encodedSearch . '</mark>',
                            $encodedText
                        );
                    };
                    ?>
                    <form method="get" action="<?= Yii::$app->urlManager->createUrl(['ldapuser/ou-user']) ?>" class="input-group input-group-sm" style="width: 320px;" id="userSearchForm" role="search">
                        <input type="hidden" name="r" value="ldapuser/ou-user">
                        <input type="hidden" name="page" value="1">
                        <input type="hidden" name="per-page" value="<?= (int)$currentPerPage ?>">
                        <input type="hidden" name="ou" id="ouPath" value="<?= Html::encode($currentOuPath) ?>">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control float-right border-start-0" id="userSearch" placeholder="Search users by name, username, email, department or title..." aria-label="Search users" value="<?= Html::encode($searchValue) ?>">
                        <button type="button" class="btn btn-outline-secondary" id="clearUserSearch" aria-label="Clear search" title="Clear">
                            <i class="fas fa-times"></i>
                        </button>
                        <button type="submit" class="btn btn-primary" id="searchButton" aria-label="Search">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <?php
                $finalUsers = isset($dataProvider) ? $dataProvider->getModels() : [];
                $totalCount = isset($dataProvider) ? $dataProvider->getTotalCount() : 0;
                $pagination = isset($dataProvider) ? $dataProvider->getPagination() : null;
                ?>
                <?php if (isset($dataProvider)): ?>
                <div class="ou-users" data-server-pagination="1" data-total-count="<?= (int)$totalCount ?>">
                    <h5 class="mb-3">
                        <i class="fas fa-users me-2"></i>Users ทั้งหมด
                        <span class="badge badge-info ms-2" id="filteredCount" data-total="<?= (int)$totalCount ?>"><?= (int)$totalCount ?> คน</span>
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
                                            $dn = $stat['dn'];
                                            $ouNameRaw = $stat['ou'];

                                            // ซ่อน OU บางกลุ่มไม่ให้แสดงใน dropdown (แต่ยังคงดึงข้อมูลมาใช้ได้ปกติ)
                                            $dnUpper = strtoupper($dn);
                                            $ouUpper = strtoupper($ouNameRaw);
                                            if (
                                                stripos($dnUpper, 'OU=DOMAIN CONTROLLERS') !== false ||
                                                stripos($dnUpper, 'OU=LOGIN-CONNECTION') !== false ||
                                                stripos($dnUpper, 'OU=RPP-COMPUTER') !== false ||
                                                // stripos($dnUpper, 'OU=rpp-user') !== false ||
                                                stripos($dnUpper, 'OU=RPP-OUTSOURCE') !== false
                                            ) {
                                                continue;
                                            }

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
                                            // Label shows full hierarchical path if available
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
                                        $currentOuPathNorm = mb_strtolower(preg_replace('/\s+/u', ' ', $currentOuPath));
                                        ?>
                                        <?php foreach ($ouItems as $item): ?>
                                            <?php
                                            $itemValueNorm = mb_strtolower(preg_replace('/\s+/u', ' ', $item['value']));
                                            $selected = ($currentOuPathNorm !== '' && $itemValueNorm === $currentOuPathNorm) ? 'selected' : '';
                                            ?>
                                            <option value="<?= Html::encode($item['value']) ?>"
                                                    data-ou="<?= Html::encode($item['ou']) ?>"
                                                    data-dn="<?= Html::encode($item['dn']) ?>"
                                                    data-path="<?= Html::encode($item['value']) ?>"
                                                    <?= $selected ?>>
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
                                    ค้นหาด้านบน = ค้นทั้งระบบ · OU และช่องกรองตาราง = กรองเฉพาะหน้านี้
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <?php
                        $rowOffset = $pagination ? (int)$pagination->getOffset() : 0;
                        ?>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                    <th style="width: 60px" class="text-end sortable" data-sort-key="row" aria-sort="asc">No.</th>
                                    <th style="width: 40px"class="sortable" data-sort-key="username" aria-sort="none">Username <span class="sort-icon"><i class="fas fa-sort"></i></span></th>
                                    <th style="width: 180px"class="sortable" data-sort-key="cn" aria-sort="none">ชื่อ-นามสกุล (CN) <span class="sort-icon"><i class="fas fa-sort"></i></span></th>
                                    <th class="sortable" data-sort-key="department" aria-sort="none">หน่วยงาน <span class="sort-icon"><i class="fas fa-sort"></i></span></th>
                                    <th class="sortable" data-sort-key="title" aria-sort="none">ตำแหน่ง <span class="sort-icon"><i class="fas fa-sort"></i></span></th>
                                    <th style="width: 160px" class="sortable" data-sort-key="whenchanged" aria-sort="none">วันที่แก้ไขล่าสุด <span class="sort-icon"><i class="fas fa-sort"></i></span></th>
                                    <th style="width: 100px" class="text-center">สถานะ</th>
                                    <th style="width: 150px">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                <?php $counter = $rowOffset + 1; ?>
                                <?php foreach ($finalUsers as $entry): ?>
                                        <?php 
                                $user = $entry['user']; 
                                $ouDn = $entry['ou_dn']; 
                                $userDn = $getUserAttr($user, 'distinguishedname') ?: $ouDn;
                                if (is_array($userDn)) { $userDn = (string)($userDn[0] ?? $ouDn); }
                                $userDn = trim((string)$userDn);
                                $whenCreated = $getUserAttr($user, 'whencreated');
                                $uac = $getUserAttr($user, 'useraccountcontrol');
                                $userAccountControl = $uac !== '' ? intval($uac) : 0;
                                $ACCOUNTDISABLE = 0x0002;
                                $isDisabled = ($userAccountControl & $ACCOUNTDISABLE) ? true : false;
                                // Extract OU path from user's DN (ใช้ format เดียวกับ dropdown: root / ... / leaf)
                                $ouDisplay = '';
                                $ouPathStr = '';
                                $ouPath = [];
                                $dnParts = array_map('trim', explode(',', $userDn));
                                foreach ($dnParts as $part) {
                                    if (stripos($part, 'OU=') === 0) {
                                        $ouPath[] = trim(substr($part, 3));
                                    }
                                }
                                if (!empty($ouPath)) {
                                    $ouPathReversed = array_reverse($ouPath);
                                    $ouPathStr = implode(' / ', $ouPathReversed);
                                    $ouDisplay = count($ouPathReversed) > 1 ? ($ouPathReversed[0] . ' / ' . $ouPathReversed[1]) : $ouPathReversed[0];
                                }
                                if ($ouDisplay === '') {
                                    $ouDisplay = $getUserAttr($user, 'ou') ?: $ouDn;
                                }
                                $dataOupath = $ouPathStr !== '' ? $ouPathStr : $ouDisplay;
                                ?>
                                <tr class="user-row" 
                                    data-ou="<?= Html::encode($userDn) ?>"
                                    data-oupath="<?= Html::encode($dataOupath) ?>"
                                    data-username="<?= Html::encode($getUserAttr($user, 'samaccountname')) ?>"
                                    data-cn="<?= Html::encode($getUserAttr($user, 'cn')) ?>"
                                    data-displayname="<?= Html::encode($getUserAttr($user, 'displayname')) ?>"
                                    data-department="<?= Html::encode($getUserAttr($user, 'department')) ?>"
                                    data-title="<?= Html::encode($getUserAttr($user, 'title')) ?>"
                                    data-email="<?= Html::encode($getUserAttr($user, 'mail')) ?>"
                                    data-whencreated="<?= Html::encode($whenCreated) ?>"
                                    data-status="<?= $isDisabled ? 'disabled' : 'enabled' ?>"
                                    data-disabled="<?= $isDisabled ? '1' : '0' ?>"
                                    data-ouname="<?= Html::encode($ouDisplay) ?>"
                                    data-rowindex="<?= $counter ?>"
                                >
                                    <td class="text-end"><?= $counter ?></td>
                                            <td><?= $highlightSearch($getUserAttr($user, 'samaccountname')) ?></td>
                                            <td><?= $highlightSearch($getUserAttr($user, 'cn')) ?></td>
                                            <td><?= $highlightSearch($getUserAttr($user, 'department')) ?></td>
                                            <td><?= $highlightSearch($getUserAttr($user, 'title') ?: 'ยังไม่ระบุ') ?></td>
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
                                        <?php if (empty($finalUsers)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                ไม่พบข้อมูลสำหรับเงื่อนไขที่เลือก
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="table-pagination mt-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div class="pagination-info text-muted small" id="paginationInfo">
                                        <?php if ($pagination): ?>
                                            แสดง <?= $rowOffset + 1 ?>–<?= min($rowOffset + $pagination->getPageSize(), $totalCount) ?> จาก <?= (int)$totalCount ?> คน
                                        <?php endif; ?>
                                    </div>
                                    <div class="pagination-buttons" id="paginationButtons">
                                        <?php if ($pagination && $pagination->getPageCount() > 1): ?>
                                            <?= LinkPager::widget([
                                                'pagination' => $pagination,
                                                'options' => ['class' => 'pagination ou-user-pagination mb-0'],
                                                'linkContainerOptions' => ['class' => 'page-item'],
                                                'linkOptions' => ['class' => 'page-link'],
                                                'disabledListItemSubTagOptions' => ['tag' => 'span', 'class' => 'page-link'],
                                                'maxButtonCount' => 5,
                                            ]) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                <?php elseif (isset($search) && $search !== ''): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>ไม่พบผลลัพธ์สำหรับคำค้นหา "<strong><?= Html::encode($search) ?></strong>"
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
                                <span class="text-muted mb-1 d-block">ชื่อแสดง</span>
                                <div class="detail-value" id="modalDisplayName" aria-label="ชื่อแสดง"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-item mb-3">
                                <span class="text-muted mb-1 d-block">อีเมล</span>
                                <div class="detail-value" id="modalEmail" aria-label="อีเมล"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item mb-3">
                                <span class="text-muted mb-1 d-block">โทรศัพท์</span>
                                <div class="detail-value" id="modalTelephone" aria-label="โทรศัพท์"></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detail-item mb-3">
                                <span class="text-muted mb-1 d-block">ผู้ติดต่อ</span>
                                <div class="detail-value" id="modalCompany" aria-label="ผู้ติดต่อ"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item mb-3">
                                <span class="text-muted mb-1 d-block">เลข E-phis</span>
                                <div class="detail-value" id="modalOffice" aria-label="เลข E-phis"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-item mb-3">
                                <label for="modalPostalcode" class="text-muted mb-1">Postalcode เลขที่บัตรประชาชน</label>
                                <div class="detail-value" id="modalPostalcode"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="detail-item mb-3">
                                <span class="text-muted mb-1 d-block">OU</span>
                                <div class="detail-value" id="modalOu" aria-label="OU"></div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="detail-item mb-3">
                                <span class="text-muted mb-1 d-block">รายละเอียด</span>
                                <div class="detail-value" id="modalStreetAddress" aria-label="รายละเอียด"></div>
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
                    <label for="updateTitle" class="form-label">ตำแหน่ง</label>
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

