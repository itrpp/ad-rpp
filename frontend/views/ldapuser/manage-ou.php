<?php
use yii\helpers\Html;
use yii\web\ForbiddenHttpException;

// Check if user is logged in
if (Yii::$app->user->isGuest) {
    throw new ForbiddenHttpException('You are not allowed to access this page. Please login first.');
}

$this->title = 'Manage Organizational Units';
$this->params['breadcrumbs'][] = ['label' => 'LDAP Management', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Get LDAP helper instance
$ldapHelper = Yii::$app->ldap;
$ous = $ldapHelper->getAllOUs();
?>

<div class="row">
    <div class="col-12">
        <!-- OU Management Card -->
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-sitemap"></i> Organizational Units in rpphosp.local
                </h3>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" name="ouSearch" class="form-control float-right" id="ouSearch" placeholder="Search OUs...">
           
                        <div class="input-group-append">
                            <button type="button" class="btn btn-default" id="searchButton">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>             <div class="card-tools">
                <?= Html::a('<i class="fas fa-plus"></i> เพิ่มOU', ['ldapuser/create-ou'], ['class' => 'btn btn-success btn-sm']) ?>
            </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 50px">No</th>
                                <th>OU Name</th>
                                <th>Type</th>
                                <th>Parent OU</th>
                                <th>Description</th>
                                <th>Users</th>
                                <th>Created</th>
                                <th>Modified</th>
                                <th style="width: 150px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($ous as $ou): 
                            ?>
                            <tr class="ou-row" data-level="<?= $ou['level'] ?>">
                                <td><?= $counter++ ?></td>
                                <td>
                                    <i class="<?= $ou['icon'] ?>"></i>
                                    <?= Html::encode($ou['ou']) ?>
                                    <span class="badge badge-<?= $ou['badge'] ?>"><?= Html::encode($ou['type']) ?></span>
                                </td>
                                <td><?= Html::encode($ou['type']) ?></td>
                                <td>
                                    <?php if ($ou['parent']): ?>
                                        <small class="text-muted"><?= Html::encode($ou['parent']) ?></small>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Root</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= Html::encode($ou['description']) ?></td>
                                <td>
                                    <span class="badge badge-info"><?= count($ou['users']) ?> users</span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('Y-m-d H:i', strtotime(preg_replace('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})\.0Z/', '$1-$2-$3 $4:$5:$6', $ou['created']))) ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('Y-m-d H:i', strtotime(preg_replace('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})\.0Z/', '$1-$2-$3 $4:$5:$6', $ou['modified']))) ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-info view-ou" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#viewOUModal"
                                            data-ou='<?= json_encode([
                                                'name' => $ou['ou'],
                                                'dn' => $ou['dn'],
                                                'type' => $ou['type'],
                                                'parent' => $ou['parent'],
                                                'description' => $ou['description'],
                                                'users' => count($ou['users']),
                                                'created' => $ou['created'],
                                                'modified' => $ou['modified']
                                            ]) ?>'
                                            title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary view-user" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#viewUserModal"
                                            data-ou='<?= json_encode([
                                                'name' => $ou['ou'],
                                                'dn' => $ou['dn'],
                                                'type' => $ou['type'],
                                                'parent' => $ou['parent'],
                                                'description' => $ou['description'],
                                                'users' => $ou['users'],
                                                'created' => $ou['created'],
                                                'modified' => $ou['modified']
                                            ]) ?>'
                                            title="View Users">
                                            <i class="fas fa-users"></i>
                                        </button>
                                        <?= Html::a('<i class="fas fa-edit"></i>', ['update-ou', 'dn' => $ou['dn']], [
                                            'class' => 'btn btn-sm btn-warning',
                                            'title' => 'Edit OU'
                                        ]) ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                        <div class="detail-value" id="modalOUName"></div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Distinguished Name</label>
                        <div class="detail-value" id="modalDN"></div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Type</label>
                        <div class="detail-value">
                            <span class="badge" id="modalType"></span>
                        </div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Parent OU</label>
                        <div class="detail-value" id="modalParent"></div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Description</label>
                        <div class="detail-value" id="modalDescription"></div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Users</label>
                        <div class="detail-value" id="modalUsers"></div>
                    </div>
                    <div class="detail-item mb-3">
                        <label class="text-muted mb-1">Created</label>
                        <div class="detail-value" id="modalCreated"></div>
                    </div>
                    <div class="detail-item">
                        <label class="text-muted mb-1">Last Modified</label>
                        <div class="detail-value" id="modalModified"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewUserModalLabel">
                    <i class="fas fa-users me-2"></i>Users in OU: <span id="modalOUTitle"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 50px">No</th>
                                <th>Username</th>
                                <th>Display Name</th>
                                <th>Department</th>
                                <th>Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="modalUserTableBody">
                            <!-- User rows will be inserted here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View User Modal Functionality
    const viewButtons = document.querySelectorAll('.view-user');
    const modal = document.getElementById('viewUserModal');
    
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const ouData = JSON.parse(this.getAttribute('data-ou'));
            const users = ouData.users || [];
            
            // Update modal title with OU name
            document.getElementById('modalOUTitle').textContent = ouData.name;
            
            // Clear existing table rows
            const tableBody = document.getElementById('modalUserTableBody');
            tableBody.innerHTML = '';
            
            // Add user rows
            users.forEach((user, index) => {
                const isDisabled = user.useraccountcontrol && (user.useraccountcontrol & 0x0002);
                const statusClass = isDisabled ? 'danger' : 'success';
                const statusText = isDisabled ? 'Disabled' : 'Enabled';
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${user.samaccountname || ''}</td>
                    <td>${user.displayname || ''}</td>
                    <td>${user.department || ''}</td>
                    <td>${user.mail || ''}</td>
                    <td><span class="badge badge-${statusClass}">${statusText}</span></td>
                `;
                tableBody.appendChild(row);
            });
            
            // Show message if no users
            if (users.length === 0) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td colspan="6" class="text-center text-muted">
                        <i class="fas fa-info-circle me-2"></i>No users found in this OU
                    </td>
                `;
                tableBody.appendChild(row);
            }
        });
    });

    // Existing OU Search Functionality
    const ouSearch = document.getElementById('ouSearch');
    const searchButton = document.getElementById('searchButton');
    const ouRows = document.querySelectorAll('.ou-row');

    function filterOUs() {
        const searchTerm = ouSearch.value.toLowerCase();
        
        ouRows.forEach(row => {
            const ouName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const ouType = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const ouParent = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            const ouDescription = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
            
            const matches = ouName.includes(searchTerm) || 
                          ouType.includes(searchTerm) || 
                          ouParent.includes(searchTerm) || 
                          ouDescription.includes(searchTerm);
            
            row.style.display = matches ? '' : 'none';
        });
    }

    ouSearch.addEventListener('input', filterOUs);
    searchButton.addEventListener('click', filterOUs);

    // View OU Modal Functionality
    const viewOUButtons = document.querySelectorAll('.view-ou');
    const viewOUModal = document.getElementById('viewOUModal');
    
    viewOUButtons.forEach(button => {
        button.addEventListener('click', function() {
            const ouData = JSON.parse(this.getAttribute('data-ou'));
            
            // Update modal content
            document.getElementById('modalOUName').textContent = ouData.name;
            document.getElementById('modalDN').textContent = ouData.dn;
            document.getElementById('modalParent').textContent = ouData.parent || 'Root';
            document.getElementById('modalDescription').textContent = ouData.description || 'No description';
            document.getElementById('modalUsers').textContent = ouData.users + ' users';
            document.getElementById('modalCreated').textContent = new Date(ouData.created).toLocaleString();
            document.getElementById('modalModified').textContent = new Date(ouData.modified).toLocaleString();
            
            // Update type badge
            const typeBadge = document.getElementById('modalType');
            typeBadge.textContent = ouData.type;
            typeBadge.className = 'badge badge-' + (ouData.type === 'User OU' ? 'primary' : 
                                                   ouData.type === 'Register OU' ? 'success' : 'info');
        });
    });
});
</script>

<style>
.ou-row {
    transition: background-color 0.3s ease;
}

.ou-row:hover {
    background-color: #f8f9fa;
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

.btn-group {
    gap: 5px;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.2rem;
}

.table th {
    background-color: #f8f9fa;
    color: #2c3e50;
    font-weight: 600;
}

.table td {
    vertical-align: middle;
}

.text-muted {
    font-size: 0.9em;
}

.user-details .detail-item {
    padding: 0.75rem;
    background-color: #fff;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.user-details .detail-item:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    transition: all 0.3s ease;
}

.user-details .detail-item label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #6c757d;
}

.user-details .detail-value {
    font-size: 1rem;
    color: #2c3e50;
    font-weight: 500;
}

.modal-lg {
    max-width: 900px;
}

.table th {
    background-color: #f8f9fa;
    color: #2c3e50;
    font-weight: 600;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
    font-weight: 600;
}

.text-muted {
    font-size: 0.9em;
}
</style> 