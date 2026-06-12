<?php

/** @var \yii\web\View $this */
/** @var string $content */

use common\widgets\Alert;
use frontend\assets\AppAsset;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use common\models\User;
use common\components\LdapHelper;
use common\components\PermissionManager;

AppAsset::register($this);

// Permissions and roles
$user = Yii::$app->user->identity;
$permissionManager = new PermissionManager();
$isAdmin = $permissionManager->isLdapAdmin();
// SuperUser: from LDAP/session check, or from RBAC role (assigned at login for CN=ManageUser etc.)
$isSuperUser = $permissionManager->isSuperUser();
if (!$isSuperUser && !Yii::$app->user->isGuest) {
    try {
        $isSuperUser = Yii::$app->user->can('superuser');
    } catch (\Throwable $e) {
        // RBAC/DB may be unavailable (e.g. SQLite file not writable); rely on LDAP check only
    }
}
$canCreateAdUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_CREATE);
$canViewLdapUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_VIEW);
$canViewOuRegister = $permissionManager->canViewOuRegister();

// Get Register OU user count
$registerUserCount = 0;
$currentUserOu = '';
if (!Yii::$app->user->isGuest) {
    $ldap = new LdapHelper();
    $registerUsers = $ldap->getUsersByOu('OU=rpp-register,DC=rpphosp,DC=local');
    $registerUserCount = count($registerUsers);
    
    // Get current user's OU information
    $currentUser = Yii::$app->user->identity;
    if (isset($currentUser->distinguishedName)) {
        if (stripos($currentUser->distinguishedName, 'OU=rpp-register') !== false) {
            $currentUserOu = 'rpp-register';
        } elseif (stripos($currentUser->distinguishedName, 'OU=rpp-user') !== false) {
            $currentUserOu = 'rpp-user';
        }
    }
}
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <link rel="icon" type="image/png" href="<?= Yii::getAlias('@web/img/logo.png') ?>">
    <link rel="shortcut icon" type="image/png" href="<?= Yii::getAlias('@web/img/logo.png') ?>">
    <style>
        /* Footer improvements */
        .main-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 15px 0;
            margin-top: auto;
        }
        
        .main-footer .container-fluid {
            padding: 0 15px;
        }
        
        .main-footer strong {
            color: #495057;
            font-size: 14px;
        }
        
        .main-footer small {
            font-size: 12px;
        }
        
        /* Responsive footer */
        @media (max-width: 768px) {
            .main-footer .col-md-6 {
                text-align: center !important;
                margin-bottom: 5px;
            }
        }

        /* Content header */
        .content-header {
            padding: 0.85rem 0.5rem;
        }
        .content-header .page-content-title {
            font-size: 1.35rem;
            font-weight: 600;
            line-height: 1.3;
        }
        .content-header .page-subtitle-meta {
            line-height: 1.5;
        }
        .content-header .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
        }
        
        /* User dropdown - match sidebar theme (dark green) */
        .navbar-nav .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            left: auto;
            z-index: 1000;
            min-width: 260px;
            max-width: 320px;
            margin-top: 0.25rem;
            padding: 0;
            border: 1px solid rgba(15, 81, 50, 0.2);
            border-radius: 0.375rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .user-dropdown-header {
            padding: 0.875rem 1rem;
            background: #0f5132;
            color: #fff;
            border-radius: 0.375rem 0.375rem 0 0;
        }
        .user-dropdown-header .user-name {
            font-weight: 600;
            font-size: 0.9375rem;
            margin-bottom: 0.15rem;
        }
        .user-dropdown-header .user-dept {
            font-size: 0.8125rem;
            opacity: 0.92;
        }
        .user-dropdown-header .user-role {
            margin-top: 0.35rem;
            font-size: 0.7rem;
        }
        .user-dropdown-header .user-role .badge {
            font-weight: 500;
            padding: 0.25rem 0.5rem;
        }
        .user-dropdown-header .badge.bg-success { background-color: rgba(255,255,255,0.95) !important; color: #0f5132 !important; }
        .user-dropdown-header .badge.bg-secondary { background-color: rgba(255,255,255,0.9) !important; color: #495057 !important; }
        .user-dropdown-header .badge.bg-light { background-color: rgba(255,255,255,0.85) !important; color: #212529 !important; }
        .user-dropdown-body .dropdown-item {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        .user-dropdown-body .dropdown-item i {
            width: 1.25rem;
            margin-right: 0.5rem;
            opacity: 0.85;
        }
        .navbar-nav .dropdown-menu .dropdown-item:hover,
        .navbar-nav .dropdown-menu .dropdown-item:focus {
            background-color: rgba(15, 81, 50, 0.12);
            color: #0f5132;
        }
        .navbar-nav .dropdown-menu .logout-item:hover,
        .navbar-nav .dropdown-menu .logout-item:focus {
            background-color: rgba(200, 35, 47, 0.1);
            color: #c82333;
        }
        .navbar-nav .dropdown-menu .dropdown-divider {
            margin: 0;
            border-color: #eee;
        }
        @media (max-width: 768px) {
            .navbar-nav .dropdown-menu {
                right: 0;
                left: auto;
                max-width: calc(100vw - 2rem);
            }
        }
        @media (max-width: 576px) {
            .navbar-nav .dropdown-menu {
                max-width: calc(100vw - 1rem);
                margin-right: 0.5rem;
            }
        }

        /* Sidebar theming - dark green */
        .main-sidebar {
            background-color: #0f5132 !important; /* dark green */
            color: #e6fff1;
        }
        .main-sidebar .brand-link {
            background-color: #0b3d27 !important; /* deeper green */
            color: #ffffff !important;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .main-sidebar .brand-link .brand-image {
            max-height: 34px;
            width: auto;
            margin-right: .5rem;
        }
        .main-sidebar .nav-sidebar .nav-link {
            color: #e6fff1;
        }
        .main-sidebar .nav-sidebar .nav-link .nav-icon {
            color: #c7f9e6;
        }
        .main-sidebar .nav-sidebar .nav-link.active,
        .main-sidebar .nav-sidebar .nav-link:hover {
            background: rgba(255,255,255,0.12);
            color: #ffffff;
        }
    </style>
    <?php $this->head() ?>
</head>
<body class="hold-transition sidebar-mini">
<?php $this->beginBody() ?>

<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?= Yii::$app->homeUrl ?>" class="nav-link">Home</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <?php if (Yii::$app->user->isGuest): ?>
                <!-- <li class="nav-item">
                    <?= Html::a('Login', ['/site/login'], ['class' => 'nav-link']) ?>
                </li>
                <li class="nav-item">
                    <?= Html::a('Register', ['/ad-user/create'], ['class' => 'nav-link']) ?>
                </li> -->
            <?php else: ?>
                <li class="nav-item dropdown">
                    <?php
                    echo Html::a(
                        '<i class="fas fa-user-circle me-2"></i>' . Html::encode($user->displayName),
                        '#',
                        [
                            'class' => 'nav-link dropdown-toggle',
                            'data-bs-toggle' => 'dropdown',
                            'aria-expanded' => 'false'
                        ]
                    );
                    ?>
                    <div class="dropdown-menu dropdown-menu-end user-dropdown-menu">
                        <div class="user-dropdown-header">
                            <div class="user-name text-truncate" title="<?= Html::encode($user->cn) ?>"><?= Html::encode($user->cn) ?></div>
                            <div class="user-dept text-truncate" title="<?= Html::encode($user->department) ?>"><?= Html::encode($user->department ?: '—') ?></div>
                            <?php if ($currentUserOu != 'rpp-register'): ?>
                            <div class="user-role">
                                <?php if ($isAdmin): ?>
                                    <span class="badge bg-success">ผู้ดูแลระบบ</span>
                                <?php elseif ($isSuperUser): ?>
                                    <span class="badge bg-secondary">Superuser</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark">ผู้ใช้ทั่วไป</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-divider"></div>
                        <div class="user-dropdown-body">
                            <?= Html::a('<i class="fas fa-user"></i>Profile', ['/site/profile'], ['class' => 'dropdown-item']) ?>
                            <?= Html::a('<i class="fas fa-key"></i>Change Password', ['/site/change-password'], ['class' => 'dropdown-item']) ?>
                            <div class="dropdown-divider"></div>
                            <?= Html::beginForm(['/site/logout'], 'post', ['class' => 'mb-0']) ?>
                                <?= Html::submitButton('<i class="fas fa-sign-out-alt"></i>Logout', ['class' => 'dropdown-item logout-item border-0 bg-transparent w-100 text-start']) ?>
                            <?= Html::endForm() ?>
                        </div>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="<?= Yii::$app->homeUrl ?>" class="brand-link">
            <img src="<?= Yii::getAlias('@web/img/logo.png') ?>" alt="<?= Html::encode(Yii::$app->name) ?>" class="brand-image elevation-2" style="opacity:.95">
            <span class="brand-text font-weight-light ms-1">ระบบจัดการผู้ใช้งาน</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="<?= Yii::$app->homeUrl ?>" class="nav-link">
                            <i class="nav-icon fas fa-home"></i>
                            <p>Home</p>
                        </a>
                    </li>
                    <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a href="<?= Yii::$app->urlManager->createUrl(['ad-user/create']) ?>" class="nav-link">
                            <i class="nav-icon fas fa-user-plus"></i>
                            <p>Register New Account</p>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($canViewOuRegister): ?>
                    <li class="nav-item">
                        <a href="<?= Yii::$app->urlManager->createUrl(['ldapuser/ou-register']) ?>" class="nav-link">
                            <i class="nav-icon fas fa-user-clock"></i>
                            <p>
                                ผู้ลงทะเบียนรออนุมัติ
                                <?php if ($registerUserCount > 0): ?>
                                    <span class="badge badge-info right"><?= $registerUserCount ?></span>
                                <?php endif; ?>
                            </p>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($isAdmin || $isSuperUser): ?>
                    <li class="nav-item">
                        <a href="<?= Yii::$app->urlManager->createUrl(['ldapuser/ou-user']) ?>" class="nav-link">
                            <i class="nav-icon fas fa-sitemap"></i>
                            <p> All User</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a href="<?= Yii::$app->urlManager->createUrl(['group/index']) ?>" class="nav-link">
                            <i class="nav-icon fas fa-users-cog"></i>
                            <p> Group Management</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= Yii::$app->urlManager->createUrl(['site-menu/index']) ?>" class="nav-link">
                            <i class="nav-icon fas fa-th-list"></i>
                            <p> จัดการเมนูระบบงาน</p>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-6">
                        <h1 class="m-0 page-content-title"><?= Html::encode($this->title) ?></h1>
                        <?php if (!empty($this->params['pageSubtitle']) && is_array($this->params['pageSubtitle'])): ?>
                            <?php
                            $pageSubtitle = $this->params['pageSubtitle'];
                            $subtitleEditor = isset($pageSubtitle['editor']) ? (string) $pageSubtitle['editor'] : '';
                            $subtitleEditedAt = isset($pageSubtitle['editedAt']) ? (string) $pageSubtitle['editedAt'] : '';
                            $subtitleEditorLabel = isset($pageSubtitle['editorLabel']) ? (string) $pageSubtitle['editorLabel'] : 'ผู้แก้ไข';
                            $subtitleEditedAtLabel = isset($pageSubtitle['editedAtLabel']) ? (string) $pageSubtitle['editedAtLabel'] : 'วันที่-เวลาที่แก้ไข';
                            ?>
                            <div class="page-subtitle-meta d-flex flex-wrap align-items-center gap-3 text-muted small mt-1">
                                <?php if ($subtitleEditor !== ''): ?>
                                    <span>
                                        <i class="fas fa-user-edit me-1"></i>
                                        <?= Html::encode($subtitleEditorLabel) ?>:
                                        <strong class="text-secondary fw-semibold"><?= Html::encode($subtitleEditor) ?></strong>
                                    </span>
                                <?php endif; ?>
                                <?php if ($subtitleEditedAt !== ''): ?>
                                    <span>
                                        <i class="fas fa-clock me-1"></i>
                                        <?= Html::encode($subtitleEditedAtLabel) ?>:
                                        <strong class="text-secondary fw-semibold"><?= Html::encode($subtitleEditedAt) ?></strong>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex justify-content-sm-end align-items-center gap-2 mt-2 mt-sm-0">
                            <?= Breadcrumbs::widget([
                                'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
                                'options' => ['class' => 'breadcrumb mb-0 justify-content-sm-end']
                            ]) ?>
                            <?php $appVersion = Yii::$app->params['appVersion'] ?? '1.0.0'; ?>
                            <span class="badge bg-secondary text-nowrap" title="เวอร์ชันระบบ">v<?= Html::encode($appVersion) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="content">
            <div class="container-fluid">
                <?= Alert::widget([
                    'excludeTypes' => (Yii::$app->controller->id === 'ldapuser' && Yii::$app->controller->action->id === 'update')
                        ? ['success', 'info', 'error']
                        : [],
                ]) ?>
                <?= $content ?>
            </div>
        </div>
    </div>

    <!-- Footer - Only show for logged in users -->
    <?php if (!Yii::$app->user->isGuest): ?>
    <footer class="main-footer py-2">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small class="text-muted">&copy; <?= Html::encode(Yii::$app->name) ?> <?= date('Y') ?></small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">เวอร์ชัน <?= Html::encode(Yii::$app->params['appVersion'] ?? '1.0.0') ?></small>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>
</div>

<?php if (!Yii::$app->user->isGuest): ?>
<!-- Account Status Modal -->
<div class="modal fade" id="accountDisabledModal" tabindex="-1" aria-labelledby="accountDisabledModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="accountDisabledModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>บัญชีถูกปิดการใช้งาน
                </h5>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    <strong>บัญชีของคุณถูกปิดการใช้งานใน Active Directory</strong><br>
                    กรุณาติดต่อผู้ดูแลระบบเพื่อขอเปิดใช้งานบัญชีของคุณ
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="window.location.href='<?= Yii::$app->urlManager->createUrl(['site/login']) ?>'">
                    <i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    var accountStatus = {
        isDisabled: false,
        checkInProgress: false,
        lastCheck: 0,
        checkCacheTimeout: 5000, // Cache check for 5 seconds
        checkInterval: 30000, // Periodic check every 30 seconds
        checkBeforeAction: true
    };
    
    // Check account status from server
    var checkAccountStatus = function(silent) {
        silent = silent || false;
        
        // Prevent multiple simultaneous checks
        if (accountStatus.checkInProgress) {
            return Promise.resolve();
        }
        
        // Use cache if recent check was done
        var now = Date.now();
        if (now - accountStatus.lastCheck < accountStatus.checkCacheTimeout) {
            if (accountStatus.isDisabled) {
                showAccountDisabledModal();
                return Promise.reject(new Error('Account disabled'));
            }
            return Promise.resolve();
        }
        
        accountStatus.checkInProgress = true;
        accountStatus.lastCheck = now;
        
        var url = '<?= Yii::$app->urlManager->createUrl(['site/check-account-status']) ?>';
        var csrf = '<?= Yii::$app->request->getCsrfToken() ?>';
        var csrfParam = '<?= Yii::$app->request->csrfParam ?>';
        
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: csrfParam + '=' + encodeURIComponent(csrf),
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(data) {
            accountStatus.checkInProgress = false;
            
            if (!data.authenticated || data.accountDisabled || data.accessDenied) {
                accountStatus.isDisabled = true;
                
                if (!silent) {
                    if (data.accessDenied) {
                        // Show different message for OU access denied
                        var modalElement = document.getElementById('accountDisabledModal');
                        if (modalElement) {
                            var modalBody = modalElement.querySelector('.modal-body p');
                            if (modalBody) {
                                modalBody.innerHTML = '<strong>บัญชีของคุณถูกย้ายไป OU ที่ไม่มีสิทธิ์เข้าถึงระบบ</strong><br>กรุณาติดต่อผู้ดูแลระบบ';
                            }
                        }
                    }
                    showAccountDisabledModal();
                    blockAllInteractions();
                }
                
                // Redirect after a short delay
                setTimeout(function() {
                    window.location.href = '<?= Yii::$app->urlManager->createUrl(['site/login']) ?>';
                }, 3000);
                
                return Promise.reject(new Error(data.accessDenied ? 'Access denied' : 'Account disabled'));
            } else if (data.enabled && data.accountReEnabled) {
                // Account was re-enabled - refresh page to get updated session
                accountStatus.isDisabled = false;
                window.location.reload();
                return Promise.resolve();
            } else if (data.sessionRefreshed && data.ouChanged) {
                // OU changed - refresh page to apply new permissions
                accountStatus.isDisabled = false;
                if (!silent) {
                    console.log('OU changed - refreshing page to apply new permissions');
                }
                window.location.reload();
                return Promise.resolve();
            } else if (data.sessionRefreshed) {
                // Session refreshed but no OU change - continue normally
                accountStatus.isDisabled = false;
                return Promise.resolve();
            } else {
                accountStatus.isDisabled = false;
                return Promise.resolve();
            }
        })
        .catch(function(error) {
            accountStatus.checkInProgress = false;
            if (!silent) {
                console.error('Account status check error:', error);
            }
            // Don't block on error, allow user to continue
            return Promise.resolve();
        });
    };
    
    // Show account disabled modal
    var showAccountDisabledModal = function() {
        var modalElement = document.getElementById('accountDisabledModal');
        if (modalElement) {
            var modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        }
    };
    
    // Block all user interactions
    var blockAllInteractions = function() {
        // Disable all form inputs
        var inputs = document.querySelectorAll('input, select, textarea, button, a.btn');
        inputs.forEach(function(input) {
            input.disabled = true;
            input.style.pointerEvents = 'none';
            input.style.opacity = '0.5';
        });
        
        // Add overlay to prevent clicks
        var overlay = document.createElement('div');
        overlay.id = 'account-disabled-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9998;cursor:not-allowed;';
        document.body.appendChild(overlay);
    };
    
    // Intercept fetch requests
    if (window.fetch) {
        var originalFetch = window.fetch;
        window.fetch = function(input, init) {
            init = init || {};
            
            // Check account status before making request
            if (accountStatus.checkBeforeAction && !accountStatus.isDisabled) {
                return checkAccountStatus(true).then(function() {
                    if (accountStatus.isDisabled) {
                        return Promise.reject(new Error('Account disabled'));
                    }
                    return originalFetch(input, init);
                }).catch(function(error) {
                    if (error.message === 'Account disabled') {
                        showAccountDisabledModal();
                        blockAllInteractions();
                        return Promise.reject(error);
                    }
                    return originalFetch(input, init);
                });
            }
            
            if (accountStatus.isDisabled) {
                showAccountDisabledModal();
                return Promise.reject(new Error('Account disabled'));
            }
            
            return originalFetch(input, init);
        };
    }
    
    // Intercept form submissions
    document.addEventListener('submit', function(e) {
        if (accountStatus.isDisabled) {
            e.preventDefault();
            e.stopPropagation();
            showAccountDisabledModal();
            return false;
        }
        
        // Check before submit
        if (accountStatus.checkBeforeAction) {
            checkAccountStatus(true).then(function() {
                if (accountStatus.isDisabled) {
                    e.preventDefault();
                    e.stopPropagation();
                    showAccountDisabledModal();
                    blockAllInteractions();
                    return false;
                }
            }).catch(function() {
                e.preventDefault();
                e.stopPropagation();
                showAccountDisabledModal();
                blockAllInteractions();
                return false;
            });
        }
    }, true);
    
    // Intercept button clicks on important actions
    document.addEventListener('click', function(e) {
        var target = e.target.closest('button, a.btn, input[type="submit"]');
        
        if (target && accountStatus.isDisabled) {
            e.preventDefault();
            e.stopPropagation();
            showAccountDisabledModal();
            return false;
        }
        
        // Check before important actions
        if (target && accountStatus.checkBeforeAction && !target.classList.contains('btn-secondary')) {
            checkAccountStatus(true).then(function() {
                if (accountStatus.isDisabled) {
                    e.preventDefault();
                    e.stopPropagation();
                    showAccountDisabledModal();
                    blockAllInteractions();
                    return false;
                }
            }).catch(function() {
                e.preventDefault();
                e.stopPropagation();
                showAccountDisabledModal();
                blockAllInteractions();
                return false;
            });
        }
    }, true);
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Check immediately on page load
        checkAccountStatus(false);
        
        // Then check periodically
        setInterval(function() {
            checkAccountStatus(false);
        }, accountStatus.checkInterval);
        
        // Also check when page becomes visible (user switches back to tab)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                checkAccountStatus(false);
            }
        });
    });
})();
</script>
<?php endif; ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
