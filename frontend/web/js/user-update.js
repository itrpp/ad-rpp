// User Update Form JavaScript
// This file handles form validation, submission, and group assignment functionality

// Global configuration object (will be set from PHP)
// Don't initialize here - it will be set by PHP before this script loads

// Toggle password fields (no-op for now)
function togglePasswordFields() {
    // No-op: manual password inputs removed; checkbox label explains behavior
}

// Validation function to check for empty fields
function validateForm() {
    const requiredFields = [
        { id: 'ldapuser-samaccountname', name: 'Username' },
        { id: 'ldapuser-displayname', name: 'Display Name' },
        { id: 'ldapuser-department', name: 'Department' },
        { id: 'ldapuser-title', name: 'ตำแหน่ง' }
    ];
    
    // Check email format if provided
    const emailField = document.getElementById('ldapuser-mail');
    if (emailField && emailField.value.trim() !== '') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailField.value.trim())) {
            alert('รูปแบบ Email ไม่ถูกต้อง');
            emailField.focus();
            return false;
        }
    }
    
    // Check username format
    const usernameField = document.getElementById('ldapuser-samaccountname');
    if (usernameField && usernameField.value.trim() !== '') {
        const usernameRegex = /^[a-zA-Z0-9_]+$/;
        if (!usernameRegex.test(usernameField.value.trim())) {
            alert('Username ต้องประกอบด้วยตัวอักษร ตัวเลข และ underscore เท่านั้น');
            usernameField.focus();
            return false;
        }
    }
    
    const emptyFields = [];
    let firstEmptyField = null;
    
    requiredFields.forEach(field => {
        const element = document.getElementById(field.id);
        if (element && element.value.trim() === '') {
            emptyFields.push(field.name);
            if (!firstEmptyField) {
                firstEmptyField = element;
            }
        }
    });
    
    if (emptyFields.length > 0) {
        // Show validation modal
        const emptyFieldsList = document.getElementById('emptyFieldsList');
        if (emptyFieldsList) {
            emptyFieldsList.innerHTML = '';
            
            emptyFields.forEach(fieldName => {
                const li = document.createElement('li');
                li.textContent = fieldName;
                emptyFieldsList.appendChild(li);
            });
            
            // Store first empty field for focus function
            window.firstEmptyField = firstEmptyField;
            
            // Show modal
            const validationModal = new bootstrap.Modal(document.getElementById('validationModal'));
            validationModal.show();
        }
        
        return false; // Prevent form submission
    }
    
    return true; // Allow form submission
}

// Function to focus on first empty field
function focusFirstEmptyField() {
    if (window.firstEmptyField) {
        window.firstEmptyField.focus();
        window.firstEmptyField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Add highlight effect
        window.firstEmptyField.style.borderColor = '#dc3545';
        window.firstEmptyField.style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
        
        // Remove highlight after 3 seconds
        setTimeout(() => {
            window.firstEmptyField.style.borderColor = '';
            window.firstEmptyField.style.boxShadow = '';
        }, 3000);
    }
    
    // Close modal
    const validationModal = bootstrap.Modal.getInstance(document.getElementById('validationModal'));
    if (validationModal) {
        validationModal.hide();
    }
}

// Escape HTML helper function
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize form submission handler
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('update-user-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return false;
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังอัปเดต...';
            submitBtn.disabled = true;
            
            // Submit form via AJAX
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Show success modal
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                    
                    // Update form fields with new data if provided
                    if (data.user) {
                        if (data.user.username) {
                            const usernameField = document.getElementById('ldapuser-samaccountname');
                            if (usernameField) usernameField.value = data.user.username;
                        }
                        if (data.user.displayname) {
                            const displayNameField = document.getElementById('ldapuser-displayname');
                            if (displayNameField) displayNameField.value = data.user.displayname;
                        }
                        if (data.user.department) {
                            const departmentField = document.getElementById('ldapuser-department');
                            if (departmentField) departmentField.value = data.user.department;
                        }
                        if (data.user.title) {
                            const titleField = document.getElementById('ldapuser-title');
                            if (titleField) titleField.value = data.user.title;
                        }
                        if (data.user.email) {
                            const emailField = document.getElementById('ldapuser-mail');
                            if (emailField) emailField.value = data.user.email;
                        }
                        if (data.user.physicalDeliveryOfficeName) {
                            const officeField = document.getElementById('ldapuser-physicaldeliveryofficename');
                            if (officeField) officeField.value = data.user.physicalDeliveryOfficeName;
                        }
                    }
                } else {
                    // Show error message
                    alert('เกิดข้อผิดพลาด: ' + (data.message || 'ไม่สามารถอัปเดตข้อมูลได้'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message);
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

// Add real-time validation feedback
document.addEventListener('DOMContentLoaded', function() {
    const requiredFields = [
        'ldapuser-samaccountname',
        'ldapuser-displayname', 
        'ldapuser-department',
        'ldapuser-title'
    ];
    
    // Add email validation
    const emailField = document.getElementById('ldapuser-mail');
    if (emailField) {
        emailField.addEventListener('blur', function() {
            if (this.value.trim() !== '') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(this.value.trim())) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
        
        emailField.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (emailRegex.test(this.value.trim())) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
    }
    
    // Add username format validation
    const usernameField = document.getElementById('ldapuser-samaccountname');
    if (usernameField) {
        usernameField.addEventListener('blur', function() {
            if (this.value.trim() !== '') {
                const usernameRegex = /^[a-zA-Z0-9_]+$/;
                if (!usernameRegex.test(this.value.trim())) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
        
        usernameField.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                const usernameRegex = /^[a-zA-Z0-9_]+$/;
                if (usernameRegex.test(this.value.trim())) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
    }
    
    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
            
            field.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        }
    });
    
    // Handle physicalDeliveryOfficeName and mail fields
    const officeField = document.getElementById('ldapuser-physicaldeliveryofficename');
    const mailField = document.getElementById('ldapuser-mail');
    
    if (officeField) {
        officeField.addEventListener('blur', function() {
            // If field is empty, show placeholder text
            if (this.value.trim() === '') {
                this.placeholder = 'ยังไม่ระบุ';
            }
        });
        
        officeField.addEventListener('input', function() {
            // Remove placeholder when user starts typing
            if (this.value.trim() !== '') {
                this.placeholder = '';
            }
        });
    }
    
    if (mailField) {
        mailField.addEventListener('blur', function() {
            // If field is empty, show placeholder text
            if (this.value.trim() === '') {
                this.placeholder = 'ยังไม่ระบุ';
            }
        });
        
        mailField.addEventListener('input', function() {
            // Remove placeholder when user starts typing
            if (this.value.trim() !== '') {
                this.placeholder = '';
            }
        });
    }
});

// Group Assignment functionality
document.addEventListener('DOMContentLoaded', function() {
    // Access config from PHP - check if it exists
    if (typeof userUpdateConfig === 'undefined') {
        console.error('userUpdateConfig is not defined. Make sure PHP config is loaded.');
        return;
    }
    
    const config = userUpdateConfig;
    const userDn = config.userDn || '';
    const csrfParam = config.csrfParam || '';
    const csrfToken = config.csrfToken || '';
    const urls = config.urls || {};
    
    console.log('Group Assignment config loaded:', { userDn, csrfParam, urls });
    
    const userGroupsList = document.getElementById('userGroupsList');
    const availableGroupsSelect = document.getElementById('availableGroupsSelect');
    const btnAddToGroup = document.getElementById('btnAddToGroup');
    const groupAssignmentMessage = document.getElementById('groupAssignmentMessage');
    
    // Load user's current groups
    function loadUserGroups() {
        if (!userDn) {
            if (userGroupsList) {
                userGroupsList.innerHTML = '<p class="text-muted text-center py-3">ไม่พบข้อมูลผู้ใช้</p>';
            }
            return;
        }
        
        if (userGroupsList) {
            userGroupsList.innerHTML = '<div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div><p class="mt-2 mb-0">กำลังโหลดกลุ่ม...</p></div>';
        }
        
        // สร้าง URL อย่างถูกต้องโดยใช้ URL และ URLSearchParams
        const baseUrl = urls.getUserGroups || '';
        const urlObj = new URL(baseUrl, window.location.origin);
        urlObj.searchParams.set('userDn', userDn);
        const url = urlObj.toString();
        
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
            }
            return r.json();
        })
        .then(data => {
            console.log('Load user groups response:', data);
            if (!userGroupsList) return;
            
            if (data.success) {
                if (data.groups && data.groups.length > 0) {
                    let html = '<ul class="list-group list-group-flush">';
                    data.groups.forEach(group => {
                        html += `
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${escapeHtml(group.cn)}</strong>
                                    ${group.description ? '<br><small class="text-muted">' + escapeHtml(group.description) + '</small>' : ''}
                                </div>
                                <button type="button" class="btn btn-sm btn-danger btn-remove-from-group" 
                                        data-group-dn="${escapeHtml(group.dn)}" 
                                        data-group-cn="${escapeHtml(group.cn)}"
                                        title="ลบออกจากกลุ่ม">
                                    <i class="fas fa-times"></i>
                                </button>
                            </li>
                        `;
                    });
                    html += '</ul>';
                    userGroupsList.innerHTML = html;
                    
                    // Attach remove event listeners
                    document.querySelectorAll('.btn-remove-from-group').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const groupDn = this.getAttribute('data-group-dn');
                            const groupCn = this.getAttribute('data-group-cn');
                            removeUserFromGroup(groupDn, groupCn);
                        });
                    });
                } else {
                    userGroupsList.innerHTML = '<p class="text-muted text-center py-3">ผู้ใช้ยังไม่ได้เป็นสมาชิกของกลุ่มใดๆ</p>';
                }
            } else {
                const errorMsg = data.message || 'ไม่สามารถโหลดกลุ่มได้';
                console.error('Error loading user groups:', errorMsg);
                userGroupsList.innerHTML = `<p class="text-danger text-center py-3"><i class="fas fa-exclamation-triangle me-2"></i>${escapeHtml(errorMsg)}</p>`;
            }
        })
        .catch(err => {
            console.error('Error loading user groups:', err);
            if (userGroupsList) {
                userGroupsList.innerHTML = '<p class="text-danger text-center py-3"><i class="fas fa-exclamation-triangle me-2"></i>เกิดข้อผิดพลาดในการโหลดกลุ่ม: ' + escapeHtml(err.message) + '</p>';
            }
        });
    }
    
    // Load available groups
    function loadAvailableGroups() {
        if (!availableGroupsSelect) return;
        
        availableGroupsSelect.innerHTML = '<option value="">-- กำลังโหลดกลุ่ม... --</option>';
        availableGroupsSelect.disabled = true;
        
        const url = urls.getAvailableGroups || '';
        
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
            }
            return r.json();
        })
        .then(data => {
            console.log('Load available groups response:', data);
            if (data.success) {
                if (data.groups && data.groups.length > 0) {
                    availableGroupsSelect.innerHTML = '<option value="">-- เลือกกลุ่ม --</option>';
                    data.groups.forEach(group => {
                        const option = document.createElement('option');
                        option.value = group.dn;
                        option.textContent = group.cn + (group.description ? ' - ' + group.description : '');
                        availableGroupsSelect.appendChild(option);
                    });
                } else {
                    availableGroupsSelect.innerHTML = '<option value="">-- ไม่พบกลุ่ม --</option>';
                }
            } else {
                const errorMsg = data.message || 'ไม่สามารถโหลดกลุ่มได้';
                console.error('Error loading available groups:', errorMsg);
                availableGroupsSelect.innerHTML = `<option value="">-- เกิดข้อผิดพลาด: ${escapeHtml(errorMsg)} --</option>`;
            }
            availableGroupsSelect.disabled = false;
        })
        .catch(err => {
            console.error('Error loading available groups:', err);
            availableGroupsSelect.innerHTML = '<option value="">-- เกิดข้อผิดพลาด: ' + escapeHtml(err.message) + ' --</option>';
            availableGroupsSelect.disabled = false;
        });
    }
    
    // Add user to group
    function addUserToGroup() {
        if (!availableGroupsSelect) return;
        
        const groupDn = availableGroupsSelect.value;
        if (!groupDn) {
            showGroupMessage('กรุณาเลือกกลุ่ม', 'warning');
            return;
        }
        
        if (!userDn) {
            showGroupMessage('ไม่พบข้อมูลผู้ใช้', 'danger');
            return;
        }
        
        if (btnAddToGroup) {
            btnAddToGroup.disabled = true;
            btnAddToGroup.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังเพิ่ม...';
        }
        
        const formData = new FormData();
        formData.append(csrfParam, csrfToken);
        formData.append('userDn', userDn);
        formData.append('groupDn', groupDn);
        
        const url = urls.addUserToGroup || '';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showGroupMessage('เพิ่มผู้ใช้เข้าไปในกลุ่มสำเร็จ', 'success');
                if (availableGroupsSelect) {
                    availableGroupsSelect.value = '';
                }
                loadUserGroups();
                loadAvailableGroups(); // Reload to update list
            } else {
                showGroupMessage(data.message || 'ไม่สามารถเพิ่มผู้ใช้เข้าไปในกลุ่มได้', 'danger');
            }
        })
        .catch(err => {
            console.error('Error adding user to group:', err);
            showGroupMessage('เกิดข้อผิดพลาด: ' + err.message, 'danger');
        })
        .finally(() => {
            if (btnAddToGroup) {
                btnAddToGroup.disabled = false;
                btnAddToGroup.innerHTML = '<i class="fas fa-user-plus me-1"></i>เพิ่มเข้าไปในกลุ่ม';
            }
        });
    }
    
    // Remove user from group
    function removeUserFromGroup(groupDn, groupCn) {
        if (!confirm(`คุณต้องการลบผู้ใช้ออกจากกลุ่ม "${groupCn}" ใช่หรือไม่?`)) {
            return;
        }
        
        if (!userDn) {
            showGroupMessage('ไม่พบข้อมูลผู้ใช้', 'danger');
            return;
        }
        
        const formData = new FormData();
        formData.append(csrfParam, csrfToken);
        formData.append('userDn', userDn);
        formData.append('groupDn', groupDn);
        
        const url = urls.removeUserFromGroup || '';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showGroupMessage('ลบผู้ใช้ออกจากกลุ่มสำเร็จ', 'success');
                loadUserGroups();
                loadAvailableGroups(); // Reload to update list
            } else {
                showGroupMessage(data.message || 'ไม่สามารถลบผู้ใช้ออกจากกลุ่มได้', 'danger');
            }
        })
        .catch(err => {
            console.error('Error removing user from group:', err);
            showGroupMessage('เกิดข้อผิดพลาด: ' + err.message, 'danger');
        });
    }
    
    // Show message
    function showGroupMessage(message, type) {
        if (!groupAssignmentMessage) return;
        
        const alertClass = type === 'success' ? 'alert-success' : type === 'warning' ? 'alert-warning' : 'alert-danger';
        groupAssignmentMessage.innerHTML = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            const alert = groupAssignmentMessage.querySelector('.alert');
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    }
    
    // Event listeners
    if (btnAddToGroup) {
        btnAddToGroup.addEventListener('click', addUserToGroup);
    }
    
    // Load data when collapse is opened the first time
    let groupDataLoaded = false;
    const groupAssignmentSection = document.getElementById('groupAssignmentSection');
    if (groupAssignmentSection) {
        groupAssignmentSection.addEventListener('shown.bs.collapse', function () {
            if (!groupDataLoaded && userDn) {
                loadUserGroups();
                loadAvailableGroups();
                groupDataLoaded = true;
            }
        });
    }
});

