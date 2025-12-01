<?php

/** @var yii\web\View $this */
/** @var int $totalUsers */
/** @var int $totalOus */
/** @var array $pendingUsers */
/** @var int $pendingCount */
use common\models\User;
use yii\bootstrap5\Html;
use common\components\PermissionManager;

$user = Yii::$app->user->identity;
$permissionManager = new PermissionManager();

// Check admin status using PermissionManager
$isAdmin = $permissionManager->isLdapAdmin();
// Superuser (view-only role)
$isSuperUser = $permissionManager->isSuperUser();

// Check specific permissions
$canViewAdUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_VIEW);
$canCreateAdUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_CREATE);
$canViewLdapUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_VIEW);

// Helper function to get the most specific OU name
function getLastOuName($ouString) {
    if (empty($ouString)) return '';
    
    $ouParts = explode(',', $ouString);
    $lastOu = '';
    
    foreach ($ouParts as $part) {
        $part = trim($part);
        if (strpos($part, 'OU=') === 0) {
            $lastOu = substr($part, 3);
        }
    }
    
    // If no OU found, try to get the last meaningful part
    if (empty($lastOu)) {
        $lastOu = end($ouParts);
        $lastOu = trim($lastOu);
    }
    
    return $lastOu ?: $ouString;
}

$this->title = 'ระบบจัดการผู้ใช้งาน โรงพยาบาลราชพิพัฒน์ (User AD)';

// Disable debug toolbar
if (class_exists('yii\debug\Module')) {
    $this->off(\yii\web\View::EVENT_END_BODY, [\yii\debug\Module::getInstance(), 'renderToolbar']);
}
// Register external CSS for this page
$this->registerCssFile('@web/css/site-index.css');
?>

<!-- Main Content -->
<div class="row">
    <div class="col-12">
        <!-- Info Boxes -->
        <?php if ($isAdmin): ?>
        <div class="row">
            <?php if ($canCreateAdUsers): ?>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users"></i> Add User
                        </h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">เพิ่ม ผู้ใช้งาน</p>
                        <a href="<?= Yii::$app->urlManager->createUrl(['ad-user/create']) ?>"
                            class="btn btn-primary">
                            <i class="fas fa-users"></i> Register Users
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($canViewLdapUsers): ?>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="card card-success card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-sitemap"></i> User Management
                        </h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">จัดการผู้ใช้งานทั้งหมด</p>
                        <a href="<?= Yii::$app->urlManager->createUrl(['ldapuser/ou-user']) ?>" class="btn btn-success">
                            <i class="fas fa-sitemap"></i> View All User
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($canViewLdapUsers): ?>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="card card-warning card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-plus"></i> Registration
                        </h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">User ที่รออนุมัติ</p>
                        <a href="<?= Yii::$app->urlManager->createUrl(['ldapuser/ou-register']) ?>"
                            class="btn btn-warning">
                            <i class="fas fa-user-plus"></i> Registration OU
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Regular User Menu (for users with view permissions) -->
        <?php if (!$isAdmin && ($canViewAdUsers || $canViewLdapUsers)): ?>

        <?php endif; ?>
        
        <!-- Welcome Section -->
        <div class="card card-outline">
            <!-- <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-home"></i> โรงพยาบาลราชพิพัฒน์
                </h3>
            </div> -->
            <div class="card-body">
                <!-- <?php if (Yii::$app->user->isGuest): ?> -->
                <!-- Guest Welcome Section -->
                <div class="row welcome-section">
                    <div class="col-md-8">
                    <div class="card bg-light">
                    <div class="card-body text-left">
                            <h4 class="alert-heading">
                                <img src="<?= Yii::getAlias('@web/img/logo.png') ?>" alt="logo" class="logo-img"> ระบบจัดการผู้ใช้งาน 
                            </h4>
                            <p class="mb-2">ระบบจัดการผู้ใช้งานของโรงพยาบาลราชพิพัฒน์ เพื่อการจัดการบัญชีผู้ใช้และสิทธิ์การเข้าถึงระบบต่างๆ</p>
                            
                            <hr>
                            <p class="mb-0">
                                <strong>คุณสามารถ:</strong>
                            </p>
                            <ul class="mb-2">
                                <li>เข้าสู่ระบบด้วยบัญชีที่มีอยู่ เพื่อแก้ไขรหัสผ่าน และข้อมูลส่วนตัว</li>
                                <li>ลงทะเบียนบัญชีใหม่ (สำหรับบุคลากรโรงพยาบาล)</li>
                                <li>เข้าถึงระบบต่างๆ ของโรงพยาบาล ( Pacs,Service,KM, wifi-internet)</li>
                                <li>บุคคลภายนอกขอใช้ระบบ VPN </li>
                                <li>ตรวจสอบสิทธิ์การเข้าถึงระบบต่างๆ-และสถานะการลงทะเบียน</li>
                            </ul>
                       
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <!-- <h5 class="card-title">เข้าสู่ระบบ</h5> -->
                                <p class="card-text">ผู้ใช้ที่มีบัญชี/หรือลงทะเบียนแล้ว</p>
                                <a href="<?= Yii::$app->urlManager->createUrl(['site/login']) ?>" class="btn btn-primary btn-lg mb-3 btn-modern">
                                    <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                                </a>
                                <hr>
                                <!-- <h5 class="card-title">ลงทะเบียนใหม่</h5> -->
                                <p class="card-text">ผู้ใช้ที่ยังไม่มีบัญชี</p>
                                <?php if ($canCreateAdUsers): ?>
                                <a href="<?= Yii::$app->urlManager->createUrl(['ad-user/create']) ?>" class="btn btn-info btn-lg btn-modern">
                                    <i class="fas fa-user-plus"></i> ลงทะเบียน
                                </a>
                                <?php else: ?>
                                <a href="<?= Yii::$app->urlManager->createUrl(['ad-user/create']) ?>" class="btn btn-info btn-lg">
                                    <i class="fas fa-user-plus"></i> ลงทะเบียน
                                </a>
                                <small class="d-block text-muted mt-2">การลงทะเบียนเปิดให้บุคลากรโรงพยาบาลทุกคน</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Logged in user section -->


                <div class="row">
                    <div class="col-12">
                        <?php if ($currentUserOu === 'rpp-register'): ?>
                        <div class="alert alert-warning" id="pending-approval-alert">
                            <i class="fas fa-clock"></i> 
                            <strong>Pending Approval</strong> - บัญชีของคุณกำลังรอการอนุมัติจากผู้ดูแลระบบ
                            <div class="mt-2">
                                <small class="text-muted">กรุณารอการอนุมัติจากผู้ดูแลระบบก่อนที่จะสามารถใช้งานระบบได้เต็มรูปแบบ</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>


            </div>
        </div>

        <!-- Hospital Systems Section - Only show for logged in users -->
        <?php if (!Yii::$app->user->isGuest): ?>
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-sitemap"></i> ระบบงานที่เกี่ยวข้องในโรงพยาบาล
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="info-box" style="background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('<?= Yii::getAlias('@web/img/e-learning.jpg') ?>') center / cover no-repeat; color:#ffffff; -webkit-text-stroke: 0.3px #000; text-shadow: 1px 1px 2px #000, -1px 1px 2px #000, 1px -1px 2px #000, -1px -1px 2px #000;">
                            <span class="info-box-icon bg-info elevation-1">
                                <a href="https://elearning.rpphosp.go.th/" target="_blank" class="d-block text-white text-decoration-none">
                                    <i class="fas fa-book"></i>
                                </a>
                            </span>
                            <div class="info-box-content" style="position: relative; z-index: 1;">
                                <a href="https://elearning.rpphosp.go.th/" target="_blank" class="text-decoration-none" style="color: #ffffff !important;">
                                    <span class="info-box-text" style="font-weight: 700; font-size: 1.1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8), -2px -2px 4px rgba(0,0,0,0.8), 2px -2px 4px rgba(0,0,0,0.8), -2px 2px 4px rgba(0,0,0,0.8), 0 0 8px rgba(0,0,0,0.9); display: block; margin-bottom: 0.5rem;">ระบบจัดการความรู้ (KM)</span>
                                    <span class="info-box-number" style="font-weight: 600; font-size: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8), -2px -2px 4px rgba(0,0,0,0.8), 2px -2px 4px rgba(0,0,0,0.8), -2px 2px 4px rgba(0,0,0,0.8), 0 0 8px rgba(0,0,0,0.9); display: block;">RPP e-Learning </span>
                                </a>
                            </div>
                        </div>
                    </div>


                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="info-box" style="background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('<?= Yii::getAlias('@web/img/authen.jpg') ?>') center / cover no-repeat; color:#ffffff; -webkit-text-stroke: 0.3px #000; text-shadow: 1px 1px 2px #000, -1px 1px 2px #000, 1px -1px 2px #000, -1px -1px 2px #000;">
                        <span class="info-box-icon bg-success elevation-1">
                        <a href="https://authen.rpphosp.go.th:1003/login?0009cd366581f1cb/" target="_blank" class="d-block text-white text-decoration-none">
                            <i class="fas fa-wifi"></i>
                        </a>
                            </span>
                            <div class="info-box-content" style="position: relative; z-index: 1;">
                                <a href="https://authen.rpphosp.go.th:1003/login?0009cd366581f1cb/" target="_blank" class="text-decoration-none" style="color: #ffffff !important;">
                                    <span class="info-box-text" style="font-weight: 700; font-size: 1.1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8), -2px -2px 4px rgba(0,0,0,0.8), 2px -2px 4px rgba(0,0,0,0.8), -2px 2px 4px rgba(0,0,0,0.8), 0 0 8px rgba(0,0,0,0.9); display: block; margin-bottom: 0.5rem;">ใช้งาน Internet </span>
                                    <span class="info-box-number" style="font-weight: 600; font-size: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8), -2px -2px 4px rgba(0,0,0,0.8), 2px -2px 4px rgba(0,0,0,0.8), -2px 2px 4px rgba(0,0,0,0.8), 0 0 8px rgba(0,0,0,0.9); display: block;">login-Logout Internet</span>
                                </a>
                            </div>
                        </div>
                    </div>


                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="info-box" style="background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('<?= Yii::getAlias('@web/img/intra.jpg') ?>') center / cover no-repeat; color:#ffffff; -webkit-text-stroke: 0.3px #000; text-shadow: 1px 1px 2px #000, -1px 1px 2px #000, 1px -1px 2px #000, -1px -1px 2px #000;">
                            <span class="info-box-icon elevation-1" style="background-color: #0f5132 !important;">
                                <a href="https://intranet.rpphosp.go.th/" target="_blank" class="d-block text-white text-decoration-none">
                                    <i class="fas fa-globe"></i>
                                </a>
                            </span>
                            <div class="info-box-content" style="position: relative; z-index: 1;">
                                <a href="https://intranet.rpphosp.go.th/" target="_blank" class="text-decoration-none" style="color: #ffffff !important;">
                                    <span class="info-box-text" style="font-weight: 700; font-size: 1.1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8), -2px -2px 4px rgba(0,0,0,0.8), 2px -2px 4px rgba(0,0,0,0.8), -2px 2px 4px rgba(0,0,0,0.8), 0 0 8px rgba(0,0,0,0.9); display: block; margin-bottom: 0.5rem;">ระบบภายในโรงพยาบาล</span>
                                    <span class="info-box-number" style="font-weight: 600; font-size: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8), -2px -2px 4px rgba(0,0,0,0.8), 2px -2px 4px rgba(0,0,0,0.8), -2px 2px 4px rgba(0,0,0,0.8), 0 0 8px rgba(0,0,0,0.9); display: block;">INTRANET</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="info-box" style="background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('<?= Yii::getAlias('@web/img/web.jpg') ?>') center / cover no-repeat; color:#ffffff; -webkit-text-stroke: 0.3px #000; text-shadow: 1px 1px 2px #000, -1px 1px 2px #000, 1px -1px 2px #000, -1px -1px 2px #000;">
                            <span class="info-box-icon bg-danger elevation-1">
                                <a href="https://www.rpphosp.go.th/" target="_blank" class="d-block text-white text-decoration-none">
                                    <i class="fas fa-hospital"></i>
                                </a>
                            </span>
                            <div class="info-box-content" style="position: relative; z-index: 1;">
                                <a href="https://www.rpphosp.go.th/" target="_blank" class="text-decoration-none" style="color: #ffffff !important;">
                                    <span class="info-box-text" style="font-weight: 700; font-size: 1.1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8), -2px -2px 4px rgba(0,0,0,0.8), 2px -2px 4px rgba(0,0,0,0.8), -2px 2px 4px rgba(0,0,0,0.8), 0 0 8px rgba(0,0,0,0.9); display: block; margin-bottom: 0.5rem;">เว็บไซต์โรงพยาบาล</span>
                                    <span class="info-box-number" style="font-weight: 600; font-size: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8), -2px -2px 4px rgba(0,0,0,0.8), 2px -2px 4px rgba(0,0,0,0.8), -2px 2px 4px rgba(0,0,0,0.8), 0 0 8px rgba(0,0,0,0.9); display: block;">ไปที่เว็บไซต์</span>
                                </a>
                            </div>
                        </div>
                    </div>  
                       <?php if ($isAdmin): ?>
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="info-box" style="background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('<?= Yii::getAlias('@web/img/equipment.jpg') ?>') center / cover no-repeat; color:#ffffff; -webkit-text-stroke: 0.3px #000; text-shadow: 1px 1px 2px #000, -1px 1px 2px #000, 1px -1px 2px #000, -1px -1px 2px #000;">
                            <span class="info-box-icon bg-warning elevation-1">
                                <a href="https://www.rpphosp.go.th/" target="_blank" class="d-block text-white text-decoration-none">
                                    <i class="fas fa-laptop" style="color: #ffffff; -webkit-text-stroke: 2px #000000; text-shadow: 0 0 0 #000000;"></i>
                                </a>
                            </span>
                       
                            <div class="info-box-content" style="position: relative; z-index: 1;">
                                <a href="http://equipment.rpphosp.go.th/ " target="_blank" class="text-decoration-none" style="color: #ffffff !important;">
                                    <span class="info-box-text" style="font-weight: 700; font-size: 1.1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8), -2px -2px 4px rgba(0,0,0,0.8), 2px -2px 4px rgba(0,0,0,0.8), -2px 2px 4px rgba(0,0,0,0.8), 0 0 8px rgba(0,0,0,0.9); display: block; margin-bottom: 0.5rem;">ทะเบียนอุปกรณ์ IT</span>
                                    <span class="info-box-number" style="font-weight: 600; font-size: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8), -2px -2px 4px rgba(0,0,0,0.8), 2px -2px 4px rgba(0,0,0,0.8), -2px 2px 4px rgba(0,0,0,0.8), 0 0 8px rgba(0,0,0,0.9); display: block;">Equipment </span>
                                </a>
                            </div>
                        
                        </div>
                    </div>  
                      <?php endif; ?>
                      <?php if ($isAdmin): ?>
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="info-box" style="background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('<?= Yii::getAlias('@web/img/monitor.jpg') ?>') center / cover no-repeat; color:#ffffff; -webkit-text-stroke: 0.3px #000; text-shadow: 1px 1px 2px #000, -1px 1px 2px #000, 1px -1px 2px #000, -1px -1px 2px #000;">
                            <span class="info-box-icon bg-warning elevation-1" style="background-color:rgb(91, 135, 228) !important;">
                                <a href="https://www.rpphosp.go.th/" target="_blank" class="d-block text-white text-decoration-none">
                                    <i class="fas fa-server" style="color: #ffffff; -webkit-text-stroke: 2px #000000; text-shadow: 0 0 0 #000000;"></i>
                                </a>
                            </span>
                       
                            <div class="info-box-content">
                                <a href="http://intranet2.rpphosp.go.th/monitor/" target="_blank" class="text-decoration-none">
                                    <span class="info-box-text">ตรวจสอบสถานะ Server/Switch</span>
                                    <span class="info-box-number">Monitor </span>
                                </a>
                            </div>
                        
                        </div>
                    </div>  
                      <?php endif; ?>


                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="info-box" style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('<?= Yii::getAlias('@web/img/gtw-backoffice.jpg') ?>') center / cover no-repeat; color:#ffffff; -webkit-text-stroke: 0.3px #000; text-shadow: 1px 1px 2px #000, -1px 1px 2px #000, 1px -1px 2px #000, -1px -1px 2px #000;">
                            <span class="info-box-icon elevation-1" style="background-color:rgb(27, 84, 207) !important;">
                                <a href="https://www.rpphosp.go.th/" target="_blank" class="d-block text-white text-decoration-none">
                                    <i class="fas fa-columns"></i>
                                </a>
                            </span>
                       
                            <div class="info-box-content">
                                <a href="https://14641.gtwoffice.com/login " target="_blank" class="text-decoration-none">
                                    <span class="info-box-text">ระบบ GTW BACKOffice</span>
                                    <span class="info-box-number">GTW BACKOffice </span>
                                </a>
                            </div>
                        
                        </div>
                    </div> 
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="info-box" style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('<?= Yii::getAlias('@web/img/inventory.jpg') ?>') center / cover no-repeat; color:#ffffff; -webkit-text-stroke: 0.3px #000; text-shadow: 1px 1px 2px #000, -1px 1px 2px #000, 1px -1px 2px #000, -1px -1px 2px #000;">
                            <span class="info-box-icon elevation-1" style="background-color:rgb(152, 199, 98) !important;">
                                <a href="https://www.rpphosp.go.th/" target="_blank" class="d-block text-white text-decoration-none">
                                    <i class="fas fa-store"></i>
                                </a>
                            </span>
                       
                            <div class="info-box-content">
                                <a href="https://inventory.rpphosp.go.th/ " target="_blank" class="text-decoration-none">
                                    <span class="info-box-text">ระบบ OneStore</span>
                                    <span class="info-box-number">INVENTORY </span>
                                </a>
                            </div>
                        
                        </div>
                    </div> 
                    <div class="col-12 col-sm-6 col-lg-3 mb-3">
                        <div class="info-box" style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('<?= Yii::getAlias('@web/img/services.jpg') ?>') center / cover no-repeat; color:#ffffff; -webkit-text-stroke: 0.3px #000; text-shadow: 1px 1px 2px #000, -1px 1px 2px #000, 1px -1px 2px #000, -1px -1px 2px #000;">
                            <span class="info-box-icon elevation-1" style="background-color:rgb(177, 10, 10) !important;">
                                <a href="https://www.rpphosp.go.th/" target="_blank" class="d-block text-white text-decoration-none">
                                    <i class="fas fa-tools"></i>
                                </a>
                            </span>
                       
                            <div class="info-box-content">
                                <a href="https://services.rpphosp.go.th/auth " target="_blank" class="text-decoration-none">
                                    <span class="info-box-text">ระบบแจ้งซ่อมช่าง</span>
                                    <span class="info-box-number">RPP Services </span>
                                </a>
                            </div>
                        
                        </div>
                    </div> 
                </div>
            </div>
        </div>
        <?php endif; ?>



</div>
</div>


<style></style>

<script>
$(document).ready(function() {
    // Add fade-in animation to cards
    $('.card').addClass('fade-in');
    
    // Periodically check if current user's OU has changed to activate access
    var refreshTimer = null;
    
    function startOuWatcher() {
        // Poll every 20s while user is logged in
        if (refreshTimer) return;
        refreshTimer = setInterval(function() {
            $.ajax({
                url: '<?= Yii::$app->urlManager->createUrl(['site/refresh-session']) ?>',
                method: 'GET',
                dataType: 'json'
            }).done(function(res) {
                if (res && res.authenticated && res.updated) {
                    console.log('OU Status Update:', res.currentUserOu, 'Active Access:', res.activeAccess);
                    
                    // Update pending approval alert based on current OU status
                    updatePendingApprovalAlert(res.currentUserOu);
                    
                    if (res.activeAccess) {
                        // When OU moved out of rpp-register, reload to update menus/permissions
                        console.log('User approved! Reloading page...');
                        clearInterval(refreshTimer);
                        refreshTimer = null;
                        location.reload();
                    }
                }
            }).fail(function() {
                console.log('Session refresh failed');
            });
        }, 20000);
    }
    
    // JavaScript function to get the most specific OU name (same logic as PHP)
    function getLastOuNameFromString(ouString) {
        if (!ouString) return '';
        
        var ouParts = ouString.split(',');
        var lastOu = '';
        
        for (var i = 0; i < ouParts.length; i++) {
            var part = ouParts[i].trim();
            if (part.indexOf('OU=') === 0) {
                lastOu = part.substring(3);
            }
        }
        
        // If no OU found, try to get the last meaningful part
        if (!lastOu) {
            lastOu = ouParts[ouParts.length - 1].trim();
        }
        
        return lastOu || ouString;
    }
    
    function updatePendingApprovalAlert(currentUserOu) {
        var alertContainer = $('#pending-approval-alert');
        console.log('Updating Pending Approval Alert. Current OU:', currentUserOu, 'Alert exists:', alertContainer.length > 0);
        
        // Update OU display if it exists
        var ouDisplay = $('small:contains("กลุ่มผู้ใช้งานระบบ:")');
        if (ouDisplay.length > 0) {
            var lastOuName = getLastOuNameFromString(currentUserOu);
            ouDisplay.html('<strong>กลุ่มผู้ใช้งานระบบ:</strong> ' + lastOuName);
        }
        
        if (currentUserOu === 'rpp-register') {
            // Show pending approval alert if not already shown
            if (alertContainer.length === 0) {
                console.log('Creating Pending Approval Alert...');
                var alertHtml = '<div class="alert alert-warning" id="pending-approval-alert">' +
                    '<i class="fas fa-clock"></i> ' +
                    '<strong>Pending Approval</strong> - บัญชีของคุณกำลังรอการอนุมัติจากผู้ดูแลระบบ' +
                    '<div class="mt-2">' +
                    '<small class="text-muted">กรุณารอการอนุมัติจากผู้ดูแลระบบก่อนที่จะสามารถใช้งานระบบได้เต็มรูปแบบ</small>' +
                    '</div>' +
                    '</div>';
                
                // Insert alert in the logged in user section
                $('.row .col-12').first().prepend(alertHtml);
                console.log('Pending Approval Alert created and displayed');
            } else {
                console.log('Pending Approval Alert already exists');
            }
        } else {
            // Hide pending approval alert if user is no longer in rpp-register OU
            if (alertContainer.length > 0) {
                console.log('Removing Pending Approval Alert...');
                alertContainer.fadeOut(500, function() {
                    $(this).remove();
                    console.log('Pending Approval Alert removed');
                });
            } else {
                console.log('No Pending Approval Alert to remove');
            }
        }
    }

    // Start watcher only when logged in
    <?php if (!Yii::$app->user->isGuest): ?>
    console.log('Starting OU Watcher for logged in user. Current User OU: <?= $currentUserOu ?>');
    console.log('Last OU Name:', getLastOuNameFromString('<?= $currentUserOu ?>'));
    startOuWatcher();
    <?php endif; ?>

    // Add smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 500);
        }
    });

    // Add loading state to buttons
    $('.btn').on('click', function() {
        var $btn = $(this);
        if ($btn.attr('href') && !$btn.attr('href').startsWith('#')) {
            $btn.prop('disabled', true);
            $btn.html('<i class="fas fa-spinner fa-spin"></i> กำลังโหลด...');
        }
    });
});
</script>
<?php endif; ?>