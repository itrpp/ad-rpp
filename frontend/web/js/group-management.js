document.addEventListener('DOMContentLoaded', function(){
    // ตรวจสอบว่ามี config หรือไม่
    if (typeof groupManagementConfig === 'undefined') {
        console.error('groupManagementConfig is not defined');
        return;
    }

    const config = groupManagementConfig;
    const csrfParam = config.csrfParam;
    const csrfToken = config.csrfToken;
    const urls = config.urls;

    // Create Group functionality
    const createGroupForm = document.getElementById('createGroupForm');
    const btnCreateGroup = document.getElementById('btnCreateGroup');
    const createGroupModal = new bootstrap.Modal(document.getElementById('createGroupModal'));
    
    if (btnCreateGroup) {
        btnCreateGroup.addEventListener('click', function() {
            const groupName = document.getElementById('groupName').value.trim();
            const groupDescription = document.getElementById('groupDescription').value.trim();
            
            // Reset validation
            document.getElementById('groupName').classList.remove('is-invalid');
            document.getElementById('groupDescription').classList.remove('is-invalid');
            document.getElementById('groupNameError').textContent = '';
            document.getElementById('groupDescriptionError').textContent = '';
            
            // Validate
            let isValid = true;
            if (!groupName) {
                document.getElementById('groupName').classList.add('is-invalid');
                document.getElementById('groupNameError').textContent = 'Group Name is required';
                isValid = false;
            }
            
            if (!groupDescription) {
                document.getElementById('groupDescription').classList.add('is-invalid');
                document.getElementById('groupDescriptionError').textContent = 'Description is required';
                isValid = false;
            }
            
            if (!isValid) {
                return;
            }
            
            // Disable button and show loading
            btnCreateGroup.disabled = true;
            btnCreateGroup.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';
            
            // Create group
            const formData = new FormData();
            formData.append(csrfParam, csrfToken);
            formData.append('cn', groupName);
            formData.append('description', groupDescription);
            
            fetch(urls.create, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Group created successfully!');
                    createGroupModal.hide();
                    // Reset form
                    createGroupForm.reset();
                    // Reload page to show new group
                    location.reload();
                } else {
                    const errorMsg = data.message || 'Failed to create group';
                    alert(errorMsg);
                    
                    // Show validation error if it's a duplicate name
                    if (errorMsg.includes('already exists') || errorMsg.includes('duplicate')) {
                        document.getElementById('groupName').classList.add('is-invalid');
                        document.getElementById('groupNameError').textContent = errorMsg;
                    }
                    
                    btnCreateGroup.disabled = false;
                    btnCreateGroup.innerHTML = '<i class="fas fa-save me-1"></i>Create Group';
                }
            })
            .catch(err => {
                alert('An error occurred while creating the group: ' + err.message);
                btnCreateGroup.disabled = false;
                btnCreateGroup.innerHTML = '<i class="fas fa-save me-1"></i>Create Group';
            });
        });
    }
    
    // Reset form when modal is closed
    const createGroupModalElement = document.getElementById('createGroupModal');
    if (createGroupModalElement) {
        createGroupModalElement.addEventListener('hidden.bs.modal', function() {
            createGroupForm.reset();
            document.getElementById('groupName').classList.remove('is-invalid');
            document.getElementById('groupDescription').classList.remove('is-invalid');
            document.getElementById('groupNameError').textContent = '';
            document.getElementById('groupDescriptionError').textContent = '';
            if (btnCreateGroup) {
                btnCreateGroup.disabled = false;
                btnCreateGroup.innerHTML = '<i class="fas fa-save me-1"></i>Create Group';
            }
        });
    }

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Edit Group functionality
    const editGroupModal = new bootstrap.Modal(document.getElementById('editGroupModal'));
    const btnUpdateGroup = document.getElementById('btnUpdateGroup');
    let currentEditGroupDn = '';
    let currentEditGroupCn = '';
    
    document.querySelectorAll('.btn-edit-group').forEach(btn => {
        btn.addEventListener('click', function() {
            const dn = this.getAttribute('data-dn');
            const cn = this.getAttribute('data-cn');
            const description = this.getAttribute('data-description');
            
            // เก็บค่าไว้สำหรับใช้ในปุ่ม delete
            currentEditGroupDn = dn;
            currentEditGroupCn = cn;
            
            // Populate form
            document.getElementById('editGroupDn').value = dn;
            document.getElementById('editGroupName').value = cn;
            document.getElementById('editGroupDescription').value = description || '';
            
            // Reset validation
            document.getElementById('editGroupDescription').classList.remove('is-invalid');
            document.getElementById('editGroupDescriptionError').textContent = '';
            
            // Show modal
            editGroupModal.show();
        });
    });
    
    if (btnUpdateGroup) {
        btnUpdateGroup.addEventListener('click', function() {
            const dn = document.getElementById('editGroupDn').value;
            const description = document.getElementById('editGroupDescription').value.trim();
            
            // Reset validation
            document.getElementById('editGroupDescription').classList.remove('is-invalid');
            document.getElementById('editGroupDescriptionError').textContent = '';
            
            // Validate
            if (!description) {
                document.getElementById('editGroupDescription').classList.add('is-invalid');
                document.getElementById('editGroupDescriptionError').textContent = 'Description is required';
                return;
            }
            
            // Disable button and show loading
            btnUpdateGroup.disabled = true;
            btnUpdateGroup.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Updating...';
            
            // Update group
            const formData = new FormData();
            formData.append(csrfParam, csrfToken);
            formData.append('dn', dn);
            formData.append('description', description);
            
            fetch(urls.update, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Group updated successfully!');
                    editGroupModal.hide();
                    location.reload();
                } else {
                    const errorMsg = data.message || 'Failed to update group';
                    alert(errorMsg);
                    btnUpdateGroup.disabled = false;
                    btnUpdateGroup.innerHTML = '<i class="fas fa-save me-1"></i>Update Group';
                }
            })
            .catch(err => {
                alert('An error occurred while updating the group: ' + err.message);
                btnUpdateGroup.disabled = false;
                btnUpdateGroup.innerHTML = '<i class="fas fa-save me-1"></i>Update Group';
            });
        });
    }
    
    // Reset edit form when modal is closed
    const editGroupModalElement = document.getElementById('editGroupModal');
    if (editGroupModalElement) {
        editGroupModalElement.addEventListener('hidden.bs.modal', function() {
            document.getElementById('editGroupForm').reset();
            document.getElementById('editGroupDescription').classList.remove('is-invalid');
            document.getElementById('editGroupDescriptionError').textContent = '';
            currentEditGroupDn = '';
            currentEditGroupCn = '';
            if (btnUpdateGroup) {
                btnUpdateGroup.disabled = false;
                btnUpdateGroup.innerHTML = '<i class="fas fa-save me-1"></i>Update Group';
            }
        });
    }

    // Delete Group functionality (ปุ่มใน modal)
    const btnDeleteGroupInModal = document.getElementById('btnDeleteGroupInModal');
    if (btnDeleteGroupInModal) {
        btnDeleteGroupInModal.addEventListener('click', function() {
            if (!currentEditGroupDn || !currentEditGroupCn) {
                alert('ไม่พบข้อมูลกลุ่ม');
                return;
            }
            
            // แจ้งเตือนก่อนลบ
            if (!confirm('⚠️ คำเตือน: คุณต้องการลบกลุ่ม "' + currentEditGroupCn + '" หรือไม่?\n\n' +
                        'การกระทำนี้จะ:\n' +
                        '• ลบกลุ่มออกจากระบบอย่างถาวร\n' +
                        '• ลบสมาชิกทั้งหมดออกจากกลุ่ม\n' +
                        '• ไม่สามารถกู้คืนได้\n\n' +
                        'คุณแน่ใจหรือไม่ว่าต้องการดำเนินการต่อ?')) {
                return;
            }
            
            // Double confirmation for safety
            if (!confirm('⚠️ คำเตือนขั้นสุดท้าย!\n\n' +
                        'คุณกำลังจะลบกลุ่ม "' + currentEditGroupCn + '" อย่างถาวร\n\n' +
                        'กด OK เพื่อยืนยันการลบ หรือ Cancel เพื่อยกเลิก')) {
                return;
            }
            
            // ปิด modal ก่อนลบ
            editGroupModal.hide();
            
            // Delete group
            const formData = new FormData();
            formData.append(csrfParam, csrfToken);
            formData.append('dn', currentEditGroupDn);
            
            fetch(urls.delete, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ลบกลุ่ม "' + currentEditGroupCn + '" สำเร็จแล้ว');
                    location.reload();
                } else {
                    const errorMsg = data.message || 'ไม่สามารถลบกลุ่มได้';
                    alert('❌ ' + errorMsg);
                }
            })
            .catch(err => {
                alert('❌ เกิดข้อผิดพลาดในการลบกลุ่ม: ' + err.message);
            });
        });
    }

    const memberModal = new bootstrap.Modal(document.getElementById('memberModal'));
    let currentGroupDn = '';
    let currentGroupCn = '';

    // ฟังก์ชัน escape HTML เพื่อป้องกัน XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ฟังก์ชันโหลดรายชื่อสมาชิก
    function loadMembers() {
        if (!currentGroupDn) return;
        
        const container = document.getElementById('membersListContainer');
        container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">กำลังโหลด...</p></div>';
        
        const url = new URL(urls.getMembers, window.location.origin);
        url.searchParams.set('groupDn', currentGroupDn);
        fetch(url.toString())
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP error! status: ' + r.status);
                }
                return r.json();
            })
            .then(data => {
                console.log('Get members response:', data);
                if (data.success) {
                    if (data.members && Array.isArray(data.members) && data.members.length > 0) {
                        console.log('Displaying ' + data.members.length + ' members');
                        displayMembers(data.members);
                        document.getElementById('memberCount').textContent = data.members.length;
                    } else {
                        console.log('No members found in group');
                        container.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>ยังไม่มีสมาชิกในกลุ่มนี้</div>';
                        document.getElementById('memberCount').textContent = '0';
                    }
                } else {
                    const errorMsg = data.message || 'ไม่สามารถโหลดข้อมูลสมาชิกได้';
                    container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + escapeHtml(errorMsg) + '</div>';
                    document.getElementById('memberCount').textContent = '0';
                    console.error('Error loading members:', data);
                }
            })
            .catch(err => {
                container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>เกิดข้อผิดพลาดในการโหลดข้อมูล: ' + escapeHtml(err.message) + '</div>';
                document.getElementById('memberCount').textContent = '0';
                console.error('Fetch error:', err);
            });
    }

    // ฟังก์ชันแสดงรายชื่อสมาชิก
    function displayMembers(members) {
        const container = document.getElementById('membersListContainer');
        if (!container) return;
        
        if (members.length === 0) {
            container.innerHTML = '<div class="alert alert-info">ยังไม่มีสมาชิกในกลุ่มนี้</div>';
            // ซ่อนปุ่มลบที่เลือก
            const btnDeleteSelected = document.getElementById('btnDeleteSelectedMembers');
            if (btnDeleteSelected) {
                btnDeleteSelected.style.display = 'none';
            }
            return;
        }

        let html = '<div class="table-responsive"><table class="table table-sm table-hover">';
        html += '<thead><tr>';
        html += '<th style="width:40px"><input type="checkbox" id="selectAllMembers" title="เลือกทั้งหมด"></th>';
        html += '<th>ชื่อที่แสดง (DisplayName)</th>';
        html += '<th>User</th>';
        html += '<th>ชื่อ-นามสกุล (CN)</th>';
        html += '<th>แผนก</th>';
        html += '<th style="width:100px">การจัดการ</th>';
        html += '</tr></thead><tbody>';
        
        members.forEach(member => {
            const displayName = escapeHtml(member.displayName || member.cn || member.dn || '');
            const samAccountName = escapeHtml(member.samAccountName || '-');
            const cn = escapeHtml(member.cn || '-');
            const department = escapeHtml(member.department || '-');
            const dn = escapeHtml(member.dn || '');
            
            html += '<tr>';
            html += '<td><input type="checkbox" class="member-checkbox" data-user-dn="' + dn + '" data-user-cn="' + cn + '"></td>';
            html += '<td>' + displayName + '</td>';
            html += '<td>' + samAccountName + '</td>';
            html += '<td>' + cn + '</td>';
            html += '<td>' + department + '</td>';
            html += '<td>';
            html += '<button class="btn btn-sm btn-danger btn-remove-member" data-dn="' + dn + '" data-cn="' + cn + '" title="ลบสมาชิก">';
            html += '<i class="fas fa-user-minus"></i></button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table></div>';
        container.innerHTML = html;

        // เพิ่ม event listener สำหรับปุ่มลบ
        container.querySelectorAll('.btn-remove-member').forEach(btn => {
            btn.addEventListener('click', function() {
                const userDn = this.getAttribute('data-dn');
                const userCn = this.getAttribute('data-cn');
                if (confirm('คุณต้องการลบสมาชิก "' + (userCn || '') + '" จากกลุ่มหรือไม่?')) {
                    removeMember(userDn, userCn);
                }
            });
        });
        
        // Attach event listeners for checkboxes
        const selectAllCheckbox = document.getElementById('selectAllMembers');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = container.querySelectorAll('.member-checkbox');
                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
                updateDeleteSelectedButton();
            });
        }
        
        container.querySelectorAll('.member-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateDeleteSelectedButton();
                updateSelectAllCheckbox();
            });
        });
        
        // แสดง/ซ่อนปุ่มลบที่เลือก
        updateDeleteSelectedButton();
    }
    
    // Update delete selected button visibility
    function updateDeleteSelectedButton() {
        const btnDeleteSelected = document.getElementById('btnDeleteSelectedMembers');
        if (!btnDeleteSelected) return;
        
        const container = document.getElementById('membersListContainer');
        if (!container) return;
        
        const checkedBoxes = container.querySelectorAll('.member-checkbox:checked');
        if (checkedBoxes.length > 0) {
            btnDeleteSelected.style.display = 'inline-block';
            btnDeleteSelected.innerHTML = '<i class="fas fa-trash me-1"></i>ลบที่เลือก (' + checkedBoxes.length + ')';
        } else {
            btnDeleteSelected.style.display = 'none';
        }
    }
    
    // Update select all checkbox state
    function updateSelectAllCheckbox() {
        const selectAllCheckbox = document.getElementById('selectAllMembers');
        if (!selectAllCheckbox) return;
        
        const container = document.getElementById('membersListContainer');
        if (!container) return;
        
        const checkboxes = container.querySelectorAll('.member-checkbox');
        const checkedBoxes = container.querySelectorAll('.member-checkbox:checked');
        
        if (checkboxes.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedBoxes.length === checkboxes.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedBoxes.length > 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
    }
    
    // Delete selected members
    function deleteSelectedMembers() {
        const container = document.getElementById('membersListContainer');
        if (!container) return;
        
        const checkedBoxes = container.querySelectorAll('.member-checkbox:checked');
        if (checkedBoxes.length === 0) {
            alert('กรุณาเลือกสมาชิกที่ต้องการลบ');
            return;
        }
        
        const selectedMembers = [];
        checkedBoxes.forEach(cb => {
            selectedMembers.push({
                dn: cb.getAttribute('data-user-dn'),
                cn: cb.getAttribute('data-user-cn')
            });
        });
        
        const memberNames = selectedMembers.map(m => m.cn || 'N/A').join(', ');
        if (!confirm('คุณต้องการลบสมาชิก ' + checkedBoxes.length + ' คนออกจากกลุ่มหรือไม่?\n\n' +
                    'สมาชิกที่เลือก:\n' + memberNames)) {
            return;
        }
        
        const btnDeleteSelected = document.getElementById('btnDeleteSelectedMembers');
        if (btnDeleteSelected) {
            btnDeleteSelected.disabled = true;
            btnDeleteSelected.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังลบ...';
        }
        
        // ลบทีละคน
        let completed = 0;
        let failed = 0;
        const total = selectedMembers.length;
        
        selectedMembers.forEach((member) => {
            removeMember(member.dn, member.cn, false).then(success => {
                completed++;
                if (!success) {
                    failed++;
                }
                
                // เมื่อลบเสร็จทั้งหมด
                if (completed + failed === total) {
                    if (btnDeleteSelected) {
                        btnDeleteSelected.disabled = false;
                        btnDeleteSelected.innerHTML = '<i class="fas fa-trash me-1"></i>ลบที่เลือก';
                    }
                    
                    if (failed === 0) {
                        alert('ลบสมาชิก ' + total + ' คนออกจากกลุ่มสำเร็จ');
                        loadMembers(); // Reload members list
                    } else {
                        alert('ลบสมาชิกสำเร็จ ' + (total - failed) + ' คน จาก ' + total + ' คน\n' +
                              'ไม่สามารถลบได้ ' + failed + ' คน');
                        loadMembers(); // Reload members list
                    }
                }
            });
        });
    }
    
    // Attach delete selected button event
    const btnDeleteSelectedMembers = document.getElementById('btnDeleteSelectedMembers');
    if (btnDeleteSelectedMembers) {
        btnDeleteSelectedMembers.addEventListener('click', deleteSelectedMembers);
    }

    // ฟังก์ชันลบสมาชิก
    function removeMember(userDn, userCn = '', showAlert = true) {
        console.log('Removing member - Group DN:', currentGroupDn, 'User DN:', userDn);
        
        // ใช้ FormData เพื่อส่งข้อมูลโดยไม่ต้อง encode หลายครั้ง
        const formData = new FormData();
        formData.append(csrfParam, csrfToken);
        formData.append('groupDn', currentGroupDn);
        formData.append('userDn', userDn);
        
        return fetch(urls.removeMember, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(r => {
            if (!r.ok) {
                throw new Error('HTTP error! status: ' + r.status);
            }
            return r.json();
        })
        .then(data => {
            console.log('Remove member response:', data);
            if (data.success) {
                if (showAlert) {
                    alert('ลบสมาชิกออกจากกลุ่มสำเร็จ');
                    loadMembers(); // โหลดรายชื่อใหม่
                }
                return true;
            } else {
                const errorMsg = data.message || 'ไม่สามารถลบสมาชิกได้';
                if (showAlert) {
                    alert(errorMsg);
                }
                console.error('Remove member failed:', data);
                return false;
            }
        })
        .catch(err => {
            if (showAlert) {
                alert('เกิดข้อผิดพลาดในการลบสมาชิก: ' + err.message);
            }
            console.error('Remove member error:', err);
            return false;
        });
    }

    // ฟังก์ชันค้นหาผู้ใช้
    function searchUsers() {
        const searchTerm = document.getElementById('userSearchInput').value.trim();
        if (!searchTerm) {
            alert('กรุณากรอกคำค้นหา');
            return;
        }

        const container = document.getElementById('searchResultsContainer');
        container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">กำลังค้นหา...</p></div>';

        const url = new URL(urls.searchUsers, window.location.origin);
        url.searchParams.set('q', searchTerm);
        fetch(url.toString())
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP error! status: ' + r.status);
                }
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    if (data.users && data.users.length > 0) {
                        displaySearchResults(data.users);
                    } else {
                        container.innerHTML = '<div class="alert alert-info">ไม่พบผู้ใช้ที่ค้นหา</div>';
                    }
                } else {
                    const errorMsg = data.message || 'ไม่สามารถค้นหาผู้ใช้ได้';
                    container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + escapeHtml(errorMsg) + '</div>';
                    console.error('Error searching users:', data);
                }
            })
            .catch(err => {
                container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>เกิดข้อผิดพลาดในการค้นหา: ' + escapeHtml(err.message) + '</div>';
                console.error('Fetch error:', err);
            });
    }

    // ฟังก์ชันแสดงผลการค้นหา
    function displaySearchResults(users) {
        const container = document.getElementById('searchResultsContainer');
        if (users.length === 0) {
            container.innerHTML = '<div class="alert alert-info">ไม่พบผู้ใช้ที่ค้นหา</div>';
            return;
        }

        let html = '<div class="list-group">';
        users.forEach(user => {
            const displayName = escapeHtml(user.displayName || user.cn || user.dn || '');
            const samAccountName = escapeHtml(user.samAccountName || '');
            const cn = escapeHtml(user.cn || '');
            const department = escapeHtml(user.department || '');
            const dn = escapeHtml(user.dn || '');
            
            html += '<div class="list-group-item">';
            html += '<div class="d-flex justify-content-between align-items-center">';
            html += '<div>';
            html += '<strong>' + displayName + '</strong><br>';
            html += '<small class="text-muted">';
            if (user.samAccountName) html += 'Username: ' + samAccountName + ' | ';
            if (user.cn) html += 'CN: ' + cn + ' | ';
            if (user.department) html += 'แผนก: ' + department;
            html += '</small>';
            html += '</div>';
            html += '<button class="btn btn-sm btn-success btn-add-user" data-dn="' + dn + '" data-name="' + displayName + '">';
            html += '<i class="fas fa-plus me-1"></i>เพิ่ม</button>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        container.innerHTML = html;

        // เพิ่ม event listener สำหรับปุ่มเพิ่ม
        container.querySelectorAll('.btn-add-user').forEach(btn => {
            btn.addEventListener('click', function() {
                const userDn = this.getAttribute('data-dn');
                const userName = this.getAttribute('data-name');
                if (confirm('คุณต้องการเพิ่ม ' + userName + ' เป็นสมาชิกของกลุ่มนี้หรือไม่?')) {
                    addMember(userDn);
                }
            });
        });
    }

    // ฟังก์ชันเพิ่มสมาชิก (return Promise)
    function addMember(userDn, showAlert = true) {
        console.log('Adding member - Group DN:', currentGroupDn, 'User DN:', userDn);
        
        // ใช้ FormData เพื่อส่งข้อมูลโดยไม่ต้อง encode หลายครั้ง
        const formData = new FormData();
        formData.append(csrfParam, csrfToken);
        formData.append('groupDn', currentGroupDn);
        formData.append('userDn', userDn);
        
        return fetch(urls.addMember, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(r => {
            if (!r.ok) {
                throw new Error('HTTP error! status: ' + r.status);
            }
            return r.json();
        })
        .then(data => {
            console.log('Add member response:', data);
            if (data.success) {
                if (showAlert) {
                    // แสดงข้อความยืนยันที่ชัดเจนขึ้น
                    const userName = data.user ? (data.user.displayName || data.user.cn || 'ผู้ใช้') : 'ผู้ใช้';
                    const groupName = data.group ? (data.group.cn || 'กลุ่ม') : 'กลุ่ม';
                    const message = `เพิ่มสมาชิกสำเร็จ!\n\nผู้ใช้: ${userName}\nกลุ่ม: ${groupName}\n\nสมาชิกถูกเพิ่มเข้าไปในกลุ่มเรียบร้อยแล้ว`;
                    alert(message);
                    
                    // สลับไปที่แท็บดูสมาชิกและโหลดรายชื่อใหม่
                    const viewTab = document.getElementById('view-members-tab');
                    const addTab = document.getElementById('add-member-tab');
                    const viewPane = document.getElementById('view-members');
                    const addPane = document.getElementById('add-member');
                    
                    if (viewTab && addTab && viewPane && addPane) {
                        viewTab.classList.add('active');
                        addTab.classList.remove('active');
                        viewPane.classList.add('show', 'active');
                        addPane.classList.remove('show', 'active');
                    }
                    
                    loadMembers();
                }
                return true;
            } else {
                const errorMsg = data.message || 'ไม่สามารถเพิ่มสมาชิกได้';
                if (showAlert) {
                    // แสดงข้อความ error ที่ชัดเจน
                    if (errorMsg.includes('already a member') || errorMsg.includes('already exists')) {
                        alert('ไม่สามารถเพิ่มสมาชิกได้\n\nผู้ใช้นี้เป็นสมาชิกของกลุ่มนี้อยู่แล้ว');
                    } else {
                        alert('ไม่สามารถเพิ่มสมาชิกได้\n\n' + errorMsg);
                    }
                }
                console.error('Add member failed:', data);
                return false;
            }
        })
        .catch(err => {
            if (showAlert) {
                alert('เกิดข้อผิดพลาดในการเพิ่มสมาชิก: ' + err.message);
            }
            console.error('Add member error:', err);
            return false;
        });
    }

    // Event listeners
    document.querySelectorAll('.btn-manage-members').forEach(btn => {
        btn.addEventListener('click', function() {
            currentGroupDn = this.getAttribute('data-dn');
            currentGroupCn = this.getAttribute('data-cn');
            document.getElementById('mmGroupCn').textContent = currentGroupCn;
            
            // โหลดรายชื่อสมาชิกเมื่อเปิด modal
            loadMembers();
            
            // รีเซ็ตแท็บและฟอร์มค้นหา
            const viewTab = document.getElementById('view-members-tab');
            const addTab = document.getElementById('add-member-tab');
            viewTab.click(); // เปิดแท็บดูสมาชิก
            document.getElementById('userSearchInput').value = '';
            document.getElementById('searchResultsContainer').innerHTML = '<p class="text-muted text-center py-3">กรุณาค้นหาผู้ใช้เพื่อเพิ่มเป็นสมาชิก</p>';
            
            memberModal.show();
        });
    });

    // ปุ่มรีเฟรชรายชื่อสมาชิก
    document.getElementById('btnRefreshMembers')?.addEventListener('click', function() {
        loadMembers();
    });

    // ปุ่มค้นหาผู้ใช้
    document.getElementById('btnSearchUsers')?.addEventListener('click', function() {
        searchUsers();
    });

    // ค้นหาเมื่อกด Enter
    document.getElementById('userSearchInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchUsers();
        }
    });

    // ฟังก์ชันโหลดรายชื่อ OU
    function loadOus() {
        const ouSelect = document.getElementById('ouSelect');
        if (!ouSelect) return;
        
        ouSelect.innerHTML = '<option value="">-- กำลังโหลด... --</option>';
        
        fetch(urls.getOus)
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP error! status: ' + r.status);
                }
                return r.json();
            })
            .then(data => {
                console.log('Get OUs response:', data);
                if (data.success) {
                    ouSelect.innerHTML = '<option value="">-- เลือก OU. --</option>';
                    if (data.ous && data.ous.length > 0) {
                        data.ous.forEach(ou => {
                            const option = document.createElement('option');
                            option.value = ou.dn || '';
                            // แสดงจำนวนสมาชิกต่อท้ายชื่อ OU ถ้ามีข้อมูล user_count
                            const baseLabel = (ou.label || ou.ou || ou.dn || '');
                            const count = typeof ou.user_count === 'number' ? ou.user_count : null;
                            option.textContent = count !== null ? `${baseLabel} (${count})` : baseLabel;
                            ouSelect.appendChild(option);
                        });
                    }
                } else {
                    ouSelect.innerHTML = '<option value="">-- ไม่สามารถโหลด OU ได้ --</option>';
                    console.error('Error loading OUs:', data);
                }
            })
            .catch(err => {
                ouSelect.innerHTML = '<option value="">-- เกิดข้อผิดพลาด --</option>';
                console.error('Fetch error:', err);
            });
    }

    // ฟังก์ชันโหลดผู้ใช้ใน OU
    function loadUsersByOu(ouDn) {
        // ซ่อนปุ่มและตัวนับเมื่อเปลี่ยน OU
        const btnAddSelectedUsers = document.getElementById('btnAddSelectedUsers');
        const selectedCountElement = document.getElementById('selectedCount');
        if (btnAddSelectedUsers) {
            btnAddSelectedUsers.style.display = 'none';
        }
        if (selectedCountElement) {
            selectedCountElement.style.display = 'none';
        }
        
        if (!ouDn) {
            document.getElementById('ouUsersContainer').innerHTML = '<p class="text-muted text-center py-3">กรุณาเลือก OU เพื่อดูรายชื่อผู้ใช้</p>';
            return;
        }
        
        const container = document.getElementById('ouUsersContainer');
        container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">กำลังโหลด...</p></div>';
        
        const url = new URL(urls.getUsersByOu, window.location.origin);
        url.searchParams.set('ouDn', ouDn);
        
        fetch(url.toString())
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP error! status: ' + r.status);
                }
                return r.json();
            })
            .then(data => {
                console.log('Get users by OU response:', data);
                if (data.success) {
                    if (data.users && Array.isArray(data.users) && data.users.length > 0) {
                        displayOuUsers(data.users);
                    } else {
                        container.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>ไม่พบผู้ใช้ใน OU นี้</div>';
                    }
                } else {
                    const errorMsg = data.message || 'ไม่สามารถโหลดข้อมูลผู้ใช้ได้';
                    container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + escapeHtml(errorMsg) + '</div>';
                    console.error('Error loading users:', data);
                }
            })
            .catch(err => {
                container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>เกิดข้อผิดพลาดในการโหลดข้อมูล: ' + escapeHtml(err.message) + '</div>';
                console.error('Fetch error:', err);
            });
    }

    // ฟังก์ชันแสดงผู้ใช้ใน OU
    function displayOuUsers(users) {
        const container = document.getElementById('ouUsersContainer');
        if (users.length === 0) {
            container.innerHTML = '<div class="alert alert-info">ไม่พบผู้ใช้ใน OU นี้</div>';
            return;
        }

        let html = '<div class="table-responsive"><table class="table table-sm table-hover">';
        html += '<thead><tr><th style="width:40px"><input type="checkbox" id="selectAllUsers" title="เลือกทั้งหมด"></th><th>ชื่อที่แสดง</th><th>User</th><th>ชื่อ-นามสกุล (CN)</th><th>แผนก</th></tr></thead><tbody>';
        
        users.forEach(user => {
            const displayName = escapeHtml(user.displayName || user.cn || user.dn || '');
            const samAccountName = escapeHtml(user.samAccountName || '-');
            const cn = escapeHtml(user.cn || '-');
            const department = escapeHtml(user.department || '-');
            const dn = escapeHtml(user.dn || '');
            
            html += '<tr>';
            html += '<td><input type="checkbox" class="user-checkbox" data-dn="' + dn + '" data-name="' + escapeHtml(displayName) + '"></td>';
            html += '<td>' + displayName + '</td>';
            html += '<td>' + samAccountName + '</td>';
            html += '<td>' + cn + '</td>';
            html += '<td>' + department + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table></div>';
        
        container.innerHTML = html;
        
        // แสดงปุ่มเพิ่มผู้ใช้ที่เลือกและตัวนับ
        const btnAddSelectedUsers = document.getElementById('btnAddSelectedUsers');
        const selectedCountElement = document.getElementById('selectedCount');
        if (btnAddSelectedUsers) {
            btnAddSelectedUsers.style.display = 'inline-block';
        }
        if (selectedCountElement) {
            selectedCountElement.style.display = 'inline-block';
        }

        // Event listener สำหรับ checkbox ทั้งหมด
        document.getElementById('selectAllUsers')?.addEventListener('change', function() {
            const checkboxes = container.querySelectorAll('.user-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });

        // Event listener สำหรับแต่ละ checkbox
        container.querySelectorAll('.user-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        // Event listener สำหรับปุ่มเพิ่มผู้ใช้ที่เลือก (ปุ่มอยู่นอก container)
        attachAddSelectedUsersListener();
    }

    // ฟังก์ชัน attach event listener สำหรับปุ่มเพิ่มผู้ใช้ที่เลือก
    function attachAddSelectedUsersListener() {
        const btn = document.getElementById('btnAddSelectedUsers');
        if (!btn) return;
        
        // ลบ event listener เก่าถ้ามี (โดยการ clone node)
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        newBtn.addEventListener('click', function() {
            const container = document.getElementById('ouUsersContainer');
            const selectedUsers = [];
            container.querySelectorAll('.user-checkbox:checked').forEach(cb => {
                selectedUsers.push({
                    dn: cb.getAttribute('data-dn'),
                    name: cb.getAttribute('data-name')
                });
            });

            if (selectedUsers.length === 0) {
                alert('กรุณาเลือกผู้ใช้ที่ต้องการเพิ่ม');
                return;
            }

            if (confirm('คุณต้องการเพิ่มผู้ใช้ ' + selectedUsers.length + ' คนเข้าไปในกลุ่มนี้หรือไม่?')) {
                addMultipleMembers(selectedUsers);
            }
        });
    }

    // ฟังก์ชันอัปเดตจำนวนผู้ใช้ที่เลือก
    function updateSelectedCount() {
        const container = document.getElementById('ouUsersContainer');
        const selectedCount = container.querySelectorAll('.user-checkbox:checked').length;
        const countElement = document.getElementById('selectedCount');
        if (countElement) {
            if (selectedCount > 0) {
                countElement.textContent = 'เลือกแล้ว: ' + selectedCount + ' คน';
                countElement.style.display = 'inline-block';
            } else {
                countElement.style.display = 'none';
            }
        }
        
        // แสดง/ซ่อนปุ่มเพิ่มผู้ใช้ที่เลือก
        const btnAddSelectedUsers = document.getElementById('btnAddSelectedUsers');
        if (btnAddSelectedUsers) {
            if (selectedCount > 0) {
                btnAddSelectedUsers.style.display = 'inline-block';
            } else {
                btnAddSelectedUsers.style.display = 'none';
            }
        }
    }

    // ฟังก์ชันเพิ่มผู้ใช้หลายคน
    function addMultipleMembers(users) {
        if (users.length === 0) return;

        const btn = document.getElementById('btnAddSelectedUsers');
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังเพิ่ม...';
        }

        let successCount = 0;
        let failCount = 0;
        let currentIndex = 0;

        function addNext() {
            if (currentIndex >= users.length) {
                // เสร็จสิ้น
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
                
                if (failCount === 0) {
                    alert('เพิ่มผู้ใช้ ' + successCount + ' คนสำเร็จ');
                    // สลับไปที่แท็บดูสมาชิกและโหลดรายชื่อใหม่
                    const viewTab = document.getElementById('view-members-tab');
                    const ouTab = document.getElementById('add-from-ou-tab');
                    const viewPane = document.getElementById('view-members');
                    const ouPane = document.getElementById('add-from-ou');
                    
                    viewTab.classList.add('active');
                    ouTab.classList.remove('active');
                    viewPane.classList.add('show', 'active');
                    ouPane.classList.remove('show', 'active');
                    
                    loadMembers();
                } else {
                    alert('เพิ่มผู้ใช้สำเร็จ ' + successCount + ' คน, ล้มเหลว ' + failCount + ' คน');
                    loadUsersByOu(document.getElementById('ouSelect').value);
                }
                return;
            }

            const user = users[currentIndex];
            currentIndex++;

            addMember(user.dn, false) // ไม่แสดง alert แต่ละคน
                .then(success => {
                    if (success) {
                        successCount++;
                    } else {
                        failCount++;
                    }
                    addNext();
                })
                .catch(err => {
                    failCount++;
                    console.error('Error adding user:', err);
                    addNext();
                });
        }

        addNext();
    }

    // Event listeners สำหรับ OU
    document.getElementById('ouSelect')?.addEventListener('change', function() {
        const ouDn = this.value;
        loadUsersByOu(ouDn);
    });

    document.getElementById('btnRefreshOus')?.addEventListener('click', function() {
        loadOus();
    });

    // โหลดรายชื่อ OU เมื่อเปิด modal
    const memberModalElement = document.getElementById('memberModal');
    if (memberModalElement) {
        memberModalElement.addEventListener('show.bs.modal', function() {
            loadOus();
        });
    }
});

