<?php
use yii\helpers\Html;
use yii\web\ForbiddenHttpException;

// Check if user is logged in
if (Yii::$app->user->isGuest) {
    throw new ForbiddenHttpException('You are not allowed to access this page. Please login first.');
}

$this->title = 'Move User to OU';
$this->params['breadcrumbs'][] = ['label' => 'update user', 'url' => ['update', 'cn' => $user['cn'][0] ?? $user['cn']]];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-exchange-alt me-2"></i>Move User: <?= Html::encode($user['cn'][0] ?? $user['cn']) ?>
        </h3>
    </div>
    <div class="card-body">
        <?php 
        // Extract current OU name from user's distinguished name
        $currentOu = $user['distinguishedname'][0] ?? '';
        $currentOuName = '';
        if (preg_match('/OU=([^,]+)/', $currentOu, $matches)) {
            $currentOuName = $matches[1];
        }
        ?>
        
        <!-- Current User Information -->
        <div class="alert alert-warning mb-4 opacity-75">
            <h5 class="alert-heading">
                <i class="fas fa-user me-2"></i>Current User Information
            </h5>
            <div class="row">
                <div class="col-md-6">
                    <strong>Display Name:</strong> <?= Html::encode($user['displayname'][0] ?? $user['cn'][0] ?? 'N/A') ?><br>
                    <strong>Username:</strong> <?= Html::encode($user['samaccountname'][0] ?? 'N/A') ?><br>
                    <strong>Email:</strong> <?= Html::encode($user['mail'][0] ?? 'N/A') ?>
                </div>
                <div class="col-md-6">
                    <strong>Department:</strong> <?= Html::encode($user['department'][0] ?? 'N/A') ?><br>
                    <strong>Current OU:</strong> 
                    <span class="badge badge-secondary">
                        <?= Html::encode($currentOuName ?: 'Unknown') ?>
                    </span>
                </div>
            </div>
        </div>
        <?= Html::beginForm(['ldapuser/move', 'cn' => $user['cn'][0] ?? $user['cn']], 'post', ['id' => 'move-form']) ?>
            <div class="form-group">
                <label for="organizationalUnit">
                    <i class="fas fa-sitemap me-2"></i>Select Target Organizational Unit
                </label>
                <select class="form-control ou-select-colorized" id="organizationalUnit" name="LdapUser[organizationalUnit]" required>
                    <option value="" disabled selected>-- Select Organizational Unit ที่ต้องการย้าย --</option>
                    <?php 
                    // Define color mapping for badge types
                    $badgeColors = [
                        'primary' => '#007bff',
                        'success' => '#28a745',
                        'info' => '#17a2b8',
                        'warning' => '#ffc107',
                        'danger' => '#dc3545',
                        'secondary' => '#6c757d'
                    ];
                    
                    // Group OUs by type
                    $groupedOus = [];
                    foreach ($subOus as $ou) {
                        $type = $ou['type'] ?? 'Other';
                        if (!isset($groupedOus[$type])) {
                            $groupedOus[$type] = [];
                        }
                        $groupedOus[$type][] = $ou;
                    }
                    
                    // Display grouped OUs
                    foreach ($groupedOus as $type => $ous) {
                        $groupColor = isset($ous[0]['badge']) && isset($badgeColors[$ous[0]['badge']]) 
                            ? $badgeColors[$ous[0]['badge']] 
                            : $badgeColors['primary'];
                        echo '<optgroup label="' . Html::encode($type) . '" data-group-color="' . $groupColor . '">';
                        foreach ($ous as $ou): 
                            // Allow specific OUs: ฝ่ายการพยาบาล and ฝ่ายการพยาบาล(Nurse) under rpp-user
                            // But exclude their sub-OUs (depth > 2)
                            $ouDn = $ou['dn'] ?? '';
                            $ouName = $ou['ou'] ?? '';
                            
                            // Check if OU should be excluded
                            $shouldExclude = false;
                            
                            if (!empty($ouDn)) {
                                // Parse DN to check OU hierarchy
                                $dnParts = explode(',', $ouDn);
                                $ouNames = [];
                                foreach ($dnParts as $part) {
                                    $part = trim($part);
                                    if (stripos($part, 'OU=') === 0) {
                                        $ouNameFromDn = substr($part, 3);
                                        $ouNames[] = $ouNameFromDn; // Keep original case for matching
                                    }
                                }
                                
                                // Reverse to get parent -> child order
                                $ouNames = array_reverse($ouNames);
                                $ouDepth = count($ouNames);
                                
                                // Exclude rpp-computer -> IT path
                                if ($ouDepth >= 2) {
                                    $firstOu = strtolower($ouNames[0] ?? '');
                                    $secondOu = strtolower($ouNames[1] ?? '');
                                    
                                    // Check if path is rpp-computer -> IT
                                    if (stripos($firstOu, 'rpp-computer') !== false) {
                                        $shouldExclude = true;
                                    }
                                }
                                
                                // Check if path starts with rpp-user -> ฝ่ายการพยาบาล
                                if ($ouDepth >= 2 && !$shouldExclude) {
                                    $firstOu = strtolower($ouNames[0] ?? '');
                                    $secondOu = $ouNames[1] ?? ''; // Keep original for exact matching
                                    $secondOuLower = strtolower($secondOu);
                                    
                                    // Check if first OU is rpp-user and second OU contains "ฝ่ายการพยาบาล"
                                    if (stripos($firstOu, 'rpp-user') !== false && 
                                        stripos($secondOuLower, 'ฝ่ายการพยาบาล') !== false) {
                                        
                                        // Check if this is one of the 2 allowed OUs (depth = 2 exactly)
                                        // 1. "ฝ่ายการพยาบาล" (without "nurse" in name)
                                        // 2. "ฝ่ายการพยาบาล(Nurse)" or "ฝ่ายการพยาบาล (Nurse)" (with "nurse")
                                        
                                        $hasNurse = (stripos($secondOuLower, 'nurse') !== false);
                                        
                                        if ($ouDepth === 2) {
                                            // This is one of the 2 allowed OUs - keep it
                                            $shouldExclude = false;
                                        } else {
                                            // Depth > 2 means this is a sub-OU under one of the allowed OUs - exclude it
                                            $shouldExclude = true;
                                        }
                                    }
                                }
                            }
                            
                            // Skip this OU if it should be excluded
                            if ($shouldExclude) {
                                continue;
                            }
                            
                            $isCurrentOu = strpos($currentOu, $ou['dn']) !== false;
                            $icon = $ou['icon'] ?? 'fas fa-folder';
                            $badge = $ou['badge'] ?? 'primary';
                            $userCount = $ou['user_count'] ?? 0;
                            $hierarchicalPath = $ou['hierarchical_path'] ?? $ou['ou'];
                            $badgeColor = isset($badgeColors[$badge]) ? $badgeColors[$badge] : $badgeColors['primary'];
                        ?>
                            <option value="<?= Html::encode($ou['dn']) ?>" 
                                    class="ou-option ou-badge-<?= $badge ?>"
                                    <?= $isCurrentOu ? 'disabled' : '' ?>
                                    data-icon="<?= $icon ?>"
                                    data-badge="<?= $badge ?>"
                                    data-badge-color="<?= $badgeColor ?>"
                                    data-user-count="<?= $userCount ?>"
                                    data-ou-name="<?= Html::encode($ou['ou']) ?>">
                                <?= Html::encode($hierarchicalPath) ?> (<?= $userCount ?> users)
                                <?= $isCurrentOu ? ' (Current OU)' : '' ?>
                            </option>
                        <?php endforeach;
                        echo '</optgroup>';
                    }
                    ?>
                </select>
                <small class="text-muted mt-2 d-block">
                    <i class="fas fa-info-circle"></i> Current OU is disabled and cannot be selected
                </small>
            </div>
            
            <!-- Available OUs Information -->
            <div class="mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="text-muted mb-0">
                            <i class="fas fa-list me-2"></i>Available Organizational Units
                        </h6>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Total: <?= count($subOus) ?> OUs available
                        </small>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="toggleOuList">
                        <i class="fas fa-eye me-1"></i>Show OUs
                    </button>
                </div>
                <div class="row" id="ouListContainer" style="display: none;">
                    <?php 
                    $groupedOus = [];
                    foreach ($subOus as $ou) {
                        $type = $ou['type'] ?? 'Other';
                        if (!isset($groupedOus[$type])) {
                            $groupedOus[$type] = [];
                        }
                        $groupedOus[$type][] = $ou;
                    }
                    
                    foreach ($groupedOus as $type => $ous): 
                    ?>
                    <div class="col-md-6 mb-3">
                        <div class="card border-left-primary">
                            <div class="card-body py-2">
                                <h6 class="card-title text-primary mb-2">
                                    <i class="fas fa-folder me-2"></i><?= Html::encode($type) ?>
                                </h6>
                                <div class="small">
                                    <?php foreach ($ous as $ou): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center">
                                                    <i class="<?= $ou['icon'] ?? 'fas fa-folder' ?> me-1"></i>
                                                    <span class="fw-bold"><?= Html::encode($ou['ou']) ?></span>
                                                </div>
                                                <small class="text-muted ms-3">
                                                    <i class="fas fa-sitemap me-1"></i>
                                                    <?= Html::encode($ou['hierarchical_path'] ?? $ou['ou']) ?>
                                                </small>
                                                <br>
                                                <small class="text-muted ms-3">
                                                    <i class="fas fa-code me-1"></i>
                                                    <code><?= Html::encode($ou['dn']) ?></code>
                                                </small>
                                            </div>
                                            <span class="badge badge-<?= $ou['badge'] ?? 'primary' ?> badge-sm">
                                                <?= $ou['user_count'] ?? 0 ?> users
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <?= Html::submitButton('Move User', ['class' => 'btn btn-dark', 'id' => 'move-button']) ?>
                <?= Html::a('Cancel', ['view', 'cn' => $user['cn'][0] ?? $user['cn']], ['class' => 'btn btn-default']) ?>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmMoveModal" tabindex="-1" aria-labelledby="confirmMoveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="confirmMoveModalLabel">
                    <i class="fas fa-exchange-alt me-2"></i>Confirm Move User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center text-secondary">
            
                    <span id="confirmMessage"></span>
                </div>
                <!-- <div class="text-center">
                    <i class="fas fa-user-shield fa-3x text-warning mb-3"></i>
                    <p class="text-muted">This action cannot be undone. Please make sure you have selected the correct Organizational Unit.</p>
                </div> -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-info" id="confirmMoveButton">
                    <i class="fas fa-exchange-alt me-2"></i>ยืนยันการย้าย
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.modal-content {
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.modal-header {
    border-bottom: none;
    padding: 1.5rem;
    border-radius: 8px 8px 0 0;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: none;
    padding: 1.5rem;
}

.alert {
    border-radius: 6px;
    padding: 1rem;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn:active {
    transform: translateY(0);
}

.text-muted {
    font-size: 0.9rem;
}

.fa-3x {
    margin-bottom: 1rem;
}

/* New styles for select */
.form-control {
    border-radius: 6px;
    padding: 0.5rem 1rem;
    border: 1px solid #ced4da;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

select option:disabled {
    background-color: #f8f9fa;
    color: #6c757d;
    font-style: italic;
}

/* Colorized OU Select Styles */
.ou-select-colorized {
    border: 2px solid #007bff;
    background: linear-gradient(to bottom, #ffffff 0%, #f8f9fa 100%);
    box-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
}

.ou-select-colorized:focus {
    border-color: #0056b3;
    box-shadow: 0 0 0 0.3rem rgba(0, 123, 255, 0.35);
    background: #ffffff;
}

/* Optgroup styles with colors */
optgroup {
    font-weight: bold;
    font-size: 1.05em;
    color: #ffffff;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    padding: 8px 12px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

/* Color variations for different optgroup types */
optgroup[label*="User OU"] {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
}

optgroup[label*="Register OU"] {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
}

optgroup[label*="Other"] {
    background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
}

/* Option styles with badge colors */
select option {
    padding: 10px 15px;
    margin: 2px 0;
    border-left: 4px solid transparent;
    transition: all 0.2s ease;
    font-weight: 500;
}

/* Color coded options by badge */
.ou-option.ou-badge-primary {
    border-left-color: #007bff;
    color: #004085;
    background-color: #e7f3ff;
}

.ou-option.ou-badge-success {
    border-left-color: #28a745;
    color: #155724;
    background-color: #d4edda;
}

.ou-option.ou-badge-info {
    border-left-color: #17a2b8;
    color: #0c5460;
    background-color: #d1ecf1;
}

.ou-option.ou-badge-warning {
    border-left-color: #ffc107;
    color: #856404;
    background-color: #fff3cd;
}

.ou-option.ou-badge-danger {
    border-left-color: #dc3545;
    color: #721c24;
    background-color: #f8d7da;
}

.ou-option.ou-badge-secondary {
    border-left-color: #6c757d;
    color: #383d41;
    background-color: #e2e3e5;
}

/* Hover effect for options */
select option:hover,
select option:focus {
    background-color: #fff !important;
    border-left-width: 6px;
    padding-left: 13px;
    font-weight: 600;
    transform: translateX(3px);
    box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
}

/* Selected option styling */
select option:checked,
select option[selected] {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
    color: #ffffff !important;
    font-weight: 700;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    border-left-width: 6px;
    box-shadow: 0 2px 6px rgba(0, 123, 255, 0.4);
}

/* Disabled option styling */
select option:disabled {
    background-color: #f8f9fa !important;
    color: #6c757d !important;
    border-left-color: #dee2e6 !important;
    font-style: italic;
    opacity: 0.6;
    cursor: not-allowed;
}

.alert-heading {
    color: #0c5460;
    font-size: 1.1rem;
}

.alert strong {
    display: inline-block;
    min-width: 120px;
    font-weight: 700;
}

.badge {
    font-size: 0.875em;
    padding: 0.375rem 0.75rem;
}

.badge-sm {
    font-size: 0.75em;
    padding: 0.25rem 0.5rem;
}

.border-left-primary {
    border-left: 4px solid #007bff !important;
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.text-primary {
    color: #007bff !important;
}

.small {
    font-size: 0.875rem;
}

.fw-bold {
    font-weight: 700 !important;
}

code {
    font-size: 0.75rem;
    background-color: #f8f9fa;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    color: #e83e8c;
}

.flex-grow-1 {
    flex-grow: 1 !important;
}

.ms-3 {
    margin-left: 1rem !important;
}

.me-1 {
    margin-right: 0.25rem !important;
}

.btn-outline-primary {
    border-color: #007bff;
    color: #007bff;
}

.btn-outline-primary:hover {
    background-color: #007bff;
    border-color: #007bff;
    color: white;
}

.btn-outline-danger {
    border-color: #dc3545;
    color: #dc3545;
}

.btn-outline-danger:hover {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}

#ouListContainer {
    transition: all 0.3s ease;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border-radius: 0.2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #495057;
}

.text-muted i {
    margin-right: 0.25rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('move-form');
    const moveButton = document.getElementById('move-button');
    const targetOu = document.getElementById('organizationalUnit');
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmMoveModal'));
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmMoveButton = document.getElementById('confirmMoveButton');
    const toggleOuList = document.getElementById('toggleOuList');
    const ouListContainer = document.getElementById('ouListContainer');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const selectedOption = targetOu.options[targetOu.selectedIndex];
        const selectedOu = selectedOption.text;
        const selectedValue = selectedOption.value;
        
        if (!selectedValue) {
            alert('กรุณาเลือก Organizational Unit ที่ต้องการย้าย');
            return;
        }
        
        const userCount = selectedOption.getAttribute('data-user-count') || '0';
        
        confirmMessage.innerHTML = `
            <div class="text-center">
                <i class="fas fa-exchange-alt fa-2x text-warning mb-3"></i>
         
                <p class="mb-2">ต้องการย้ายผู้ใช้งาน <strong><?= Html::encode($user['cn'][0] ?? $user['cn']) ?></strong></p>
                <p class="mb-2">จาก <span class="badge badge-secondary"><?= Html::encode($currentOuName ?: 'Unknown') ?></span></p>
                <p class="mb-2">ไปยัง <span class="badge badge-primary">${selectedOu}</span></p>
          
            </div>
        `;
        confirmModal.show();
    });

    confirmMoveButton.addEventListener('click', function() {
        form.submit();
    });

    // Toggle OU List functionality
    toggleOuList.addEventListener('click', function() {
        const isVisible = ouListContainer.style.display !== 'none';
        
        if (isVisible) {
            // Hide the list
            ouListContainer.style.display = 'none';
            toggleOuList.innerHTML = '<i class="fas fa-eye me-1"></i>Show OUs';
            toggleOuList.classList.remove('btn-outline-danger');
            toggleOuList.classList.add('btn-outline-primary');
        } else {
            // Show the list
            ouListContainer.style.display = 'block';
            toggleOuList.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Hide OUs';
            toggleOuList.classList.remove('btn-outline-primary');
            toggleOuList.classList.add('btn-outline-danger');
        }
    });
});
</script>