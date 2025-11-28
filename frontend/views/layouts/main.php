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
$isSuperUser = $permissionManager->isSuperUser();
$canCreateAdUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_CREATE);
$canViewLdapUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_VIEW);

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
        
        /* Dropdown positioning fixes */
        .navbar-nav .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            left: auto;
            z-index: 1000;
            min-width: 200px;
            max-width: 300px;
            margin-top: 0.125rem;
        }
        
        /* Responsive dropdown positioning */
        @media (max-width: 768px) {
            .navbar-nav .dropdown-menu {
                right: 0;
                left: auto;
                max-width: calc(100vw - 2rem);
                transform: translateX(0);
            }
        }
        
        /* Ensure dropdown doesn't overflow on small screens */
        @media (max-width: 576px) {
            .navbar-nav .dropdown-menu {
                right: 0;
                left: auto;
                max-width: calc(100vw - 1rem);
                margin-right: 0.5rem;
            }
        }
        
        /* Text truncation for long names */
        .dropdown-header .text-truncate {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Dropdown menu items hover effect - green highlight */
        .dropdown-menu .dropdown-item:hover,
        .dropdown-menu .dropdown-item:focus {
            background-color: #28a745 !important;
            color: #ffffff !important;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        
        .dropdown-menu .dropdown-item:hover i,
        .dropdown-menu .dropdown-item:focus i {
            color: #ffffff !important;
        }
        
        /* Logout button hover effect - orange highlight */
        .dropdown-menu .logout-item:hover,
        .dropdown-menu .logout-item:focus {
            background-color: #fd7e14 !important;
            color: #ffffff !important;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        
        .dropdown-menu .logout-item:hover i,
        .dropdown-menu .logout-item:focus i {
            color: #ffffff !important;
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
                    <div class="dropdown-menu dropdown-menu-right dropdown-menu-end" style="min-width: 320px; max-width: 420px; padding: 0;">
                        <!-- User Header Section -->
                        <div class="dropdown-header text-center" style="padding: 1rem 1.25rem; background: linear-gradient(135deg,rgb(7, 75, 35) 0%,rgb(42, 82, 47) 100%); color: #ffffff; border-radius: 0.375rem 0.375rem 0 0;">
                            <div class="mb-2">
                                <i class="fas fa-user-circle" style="font-size: 3rem; color: rgba(255,255,255,0.9);"></i>
                            </div>
                            <div class="text-truncate fw-bold" style="font-size: 1rem; margin-bottom: 0.25rem;" title="<?= Html::encode($user->cn) ?>">
                                <?= Html::encode($user->cn) ?>
                            </div>
                            <div class="text-truncate" style="font-size: 0.85rem; opacity: 0.9;" title="<?= Html::encode($user->department) ?>">
                                <?= Html::encode($user->department) ?>
                            </div>
                        </div>
                        
                        <!-- Permission Section -->
                        <?php if ($currentUserOu != 'rpp-register'): ?>
                        <div class="px-3 py-3" style="background-color: #f8f9fa; border-left: 4px solid #007bff; margin: 0.5rem 0.75rem; border-radius: 0.375rem;">
                       
                            
                            <!-- Status Badge -->
                            <div style="font-size: 0.875rem; color: #495057; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-weight: 600; color: #007bff;"><i class="fas fa-user-shield me-2"></i>สิทธิ์:</span>
                                <?php if ($isAdmin): ?>
                                    <span class="badge" style="font-size: 0.8rem; padding: 0.4rem 0.75rem; background-color: #28a745; color: #ffffff; border-radius: 0.25rem;">ผู้ดูแลระบบ</span>
                                <?php elseif ($isSuperUser): ?>
                                    <span class="badge" style="font-size: 0.8rem; padding: 0.4rem 0.75rem; background-color: #9b59b6; color: #ffffff; border-radius: 0.25rem;">Superuser</span>
                                <?php else: ?>
                                    <span class="badge" style="font-size: 0.8rem; padding: 0.4rem 0.75rem; background-color: #17a2b8; color: #ffffff; border-radius: 0.25rem;">ผู้ใช้ทั่วไป</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Access Permissions -->
                            <div style="font-size: 0.85rem; color: #495057; line-height: 1.6;">
                                <div style="margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">ระบบที่สามารถเข้าถึง</div>
                                <div style="padding-left: 0.5rem;">
                                    <?php
                                    $programs = ['KM', 'Pacs', 'Service ช่าง', 'internet/WiFi', 'e-phis'];
                                    $program_colors = [
                                        'KM' => '#3498db',
                                        'Pacs' => '#9b59b6',
                                        'Service ช่าง' => '#e67e22',
                                        'internet/WiFi' => '#16a085',
                                        'e-phis' => '#c0392b'
                                    ];
                                    $program_badges = '';
                                    foreach ($programs as $program) {
                                        $color = $program_colors[$program] ?? '#6c757d';
                                        $program_badges .= '<span class="badge me-1 mb-1" style="font-size: 0.75rem; padding: 0.35rem 0.6rem; background-color: ' . $color . '; color: #ffffff; border-radius: 0.25rem; display: inline-block;">' . $program . '</span>';
                                    }
                                    $e_phisnote = '<div class="mt-2 p-2" style="background-color: #fff3cd; border-left: 3px solid #dc3545; border-radius: 0.25rem;"><small style="color: #dc3545; font-weight: 500; line-height: 1.5;">*ยกเว้นระบบ e-phis ใช้ User จากระบบนี้ แล้วใช้รหัสผ่านเริ่มต้น(1234)จากนั้นสามารถแก้รหัสผ่านระบบ e-phis*</small></div>';
                                    $permissions = [];
                                    if ($isAdmin) {
                                        $permissions[] = '<div style="margin-bottom: 0.6rem;"><span style="color: #27ae60; font-weight: 600;">✓ การจัดการในระบบนี้ทั้งหมด</span></div>';
                                        $permissions[] = '<div style="margin-bottom: 0.4rem; line-height: 1.8;">' . $program_badges . '</div>';
                                    } elseif ($isSuperUser) {
                                        $permissions[] = '<div style="margin-bottom: 0.6rem;"><span style="color: #3498db; font-weight: 600;">✓ View ผู้ใช้งานในระบบ AD</span></div>';
                                        $permissions[] = '<div style="margin-bottom: 0.6rem;"><span style="color: #9b59b6; font-weight: 600;">✓ เป็น Superuser</span></div>';
                                        $permissions[] = '<div style="margin-bottom: 0.4rem; line-height: 1.8;">' . $program_badges . '</div>';
                                    } else {
                                        $permissions[] = '<div style="margin-bottom: 0.4rem; line-height: 1.8;">' . $program_badges . '</div>';
                                    }
                                    echo implode('', $permissions);
                                    echo $e_phisnote;
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider" style="margin: 0.5rem 0;"></div>
                        <?php endif; ?>
                        
                        <!-- Menu Items -->
                        <div style="padding: 0.25rem 0;">
                            <?= Html::a('<i class="fas fa-user me-2" style="width: 20px;"></i>Profile', ['/site/profile'], ['class' => 'dropdown-item', 'style' => 'padding: 0.65rem 1.25rem;']) ?>
                            <?= Html::a('<i class="fas fa-key me-2" style="width: 20px;"></i>Change Password', ['/site/change-password'], ['class' => 'dropdown-item', 'style' => 'padding: 0.65rem 1.25rem;']) ?>
                        </div>
                        
                        <div class="dropdown-divider" style="margin: 0.5rem 0;"></div>
                        
                        <!-- Logout -->
                        <div style="padding: 0.25rem 0;">
                            <?= Html::beginForm(['/site/logout'], 'post', ['class' => 'd-flex', 'style' => 'margin: 0;'])
                                . Html::submitButton(
                                    '<i class="fas fa-sign-out-alt me-2" style="width: 20px;"></i>Logout',
                                    ['class' => 'dropdown-item logout-item', 'style' => 'padding: 0.65rem 1.25rem; width: 100%; text-align: left; border: none; background: none; color: #212529;']
                                )
                                . Html::endForm() ?>
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

                    <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a href="<?= Yii::$app->urlManager->createUrl(['ldapuser/ou-register']) ?>" class="nav-link">
                            <i class="nav-icon fas fa-user-plus"></i>
                            <p>
                                UserRegister
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
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><?= Html::encode($this->title) ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <?= Breadcrumbs::widget([
                            'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
                            'options' => ['class' => 'breadcrumb float-sm-right']
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="content">
            <div class="container-fluid">
                <?= Alert::widget() ?>
                <?= $content ?>
            </div>
        </div>
    </div>

    <!-- Footer - Only show for logged in users -->
    <?php if (!Yii::$app->user->isGuest): ?>
    <!-- <footer class="main-footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <strong>&copy; <?= Html::encode(Yii::$app->name) ?> <?= date('Y') ?></strong>
                </div>
                <div class="col-md-6 text-right">
                    <small class="text-muted">ระบบจัดการผู้ใช้งานโรงพยาบาลราชพิพัฒน์</small>
                </div>
            </div>
        </div>
    </footer> -->
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
