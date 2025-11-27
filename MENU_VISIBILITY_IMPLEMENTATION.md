# Menu Visibility Based on Access Permissions

## Overview
หน้า `index.php` ได้ถูกปรับปรุงให้แสดงเมนูตามสิทธิ์การเข้าถึงที่กำหนดไว้ในระบบ RBAC โดยใช้ PermissionManager แทนการตรวจสอบ admin แบบเก่า

## Changes Made

### 1. Permission Integration
```php
use common\components\PermissionManager;

$permissionManager = new PermissionManager();

// Check admin status using PermissionManager
$isAdmin = $permissionManager->isLdapAdmin();

// Check specific permissions
$canViewAdUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_VIEW);
$canCreateAdUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_CREATE);
$canViewLdapUsers = $permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_VIEW);
```

### 2. Admin Menu Section
แสดงเฉพาะสำหรับ admin users เท่านั้น:

#### Add User Card
- **Condition:** `$canCreateAdUsers`
- **Link:** `/ad-user/create`
- **Description:** เพิ่ม ผู้ใช้งาน

#### User Management Card
- **Condition:** `$canViewLdapUsers`
- **Link:** `/ldapuser/ou-user`
- **Description:** จัดการผู้ใช้งานทั้งหมด

#### Registration Card
- **Condition:** `$canViewLdapUsers`
- **Link:** `/ldapuser/ou-register`
- **Description:** User ที่รออนุมัติ

### 3. Regular User Menu Section
แสดงสำหรับผู้ใช้ทั่วไปที่มีสิทธิ์ดูข้อมูล:

#### View AD Users
- **Condition:** `$canViewAdUsers`
- **Link:** `/ad-user/index`
- **Description:** ดูรายการผู้ใช้ AD

#### View LDAP Users
- **Condition:** `$canViewLdapUsers`
- **Link:** `/ldapuser/index`
- **Description:** ดูรายการผู้ใช้ LDAP

### 4. Registration Button
ปรับปรุงปุ่มลงทะเบียนให้แสดงข้อมูลที่เหมาะสม:

```php
<?php if ($canCreateAdUsers): ?>
    <a href="..." class="btn btn-info btn-lg">
        <i class="fas fa-user-plus"></i> ลงทะเบียน
    </a>
<?php else: ?>
    <a href="..." class="btn btn-info btn-lg">
        <i class="fas fa-user-plus"></i> ลงทะเบียน
    </a>
    <small class="d-block text-muted mt-2">การลงทะเบียนเปิดให้ทุกคน</small>
<?php endif; ?>
```

### 5. User Permission Info
แสดงข้อมูลสิทธิ์สำหรับผู้ใช้ที่ login แล้ว:

- **สถานะ:** ผู้ดูแลระบบ / ผู้ใช้ทั่วไป
- **OU:** Organizational Unit ปัจจุบัน
- **สิทธิ์:** รายการสิทธิ์ที่มี

## Menu Visibility Rules

### Admin Users
- ✅ Add User (ถ้ามีสิทธิ์สร้างผู้ใช้ AD)
- ✅ User Management (ถ้ามีสิทธิ์ดูผู้ใช้ LDAP)
- ✅ Registration (ถ้ามีสิทธิ์ดูผู้ใช้ LDAP)
- ✅ View AD Users (ถ้ามีสิทธิ์ดูผู้ใช้ AD)
- ✅ View LDAP Users (ถ้ามีสิทธิ์ดูผู้ใช้ LDAP)

### Regular Users
- ❌ Add User (เฉพาะ admin)
- ❌ User Management (เฉพาะ admin)
- ❌ Registration (เฉพาะ admin)
- ✅ View AD Users (ถ้ามีสิทธิ์ดูผู้ใช้ AD)
- ✅ View LDAP Users (ถ้ามีสิทธิ์ดูผู้ใช้ LDAP)

### Guest Users
- ❌ Admin Menu (ต้อง login)
- ❌ Regular User Menu (ต้อง login)
- ✅ Registration Button (เปิดให้ทุกคน)

## Permission Mapping

### Admin Groups
- `CN=manage Ad_admin,CN=User,DC=rpphosp,DC=local`
- `CN=Administrators`
- `CN=Domain Admins`
- Users in `OU=IT,OU=rpp-user,DC=rpphosp,DC=local`

### Permission Constants
- `PermissionManager::PERMISSION_AD_USER_VIEW`
- `PermissionManager::PERMISSION_AD_USER_CREATE`
- `PermissionManager::PERMISSION_LDAP_USER_VIEW`

## UI/UX Improvements

### Visual Indicators
- **Admin Status Badge:** แสดงสถานะผู้ดูแลระบบ
- **Permission List:** แสดงรายการสิทธิ์ที่มี
- **OU Information:** แสดง Organizational Unit ปัจจุบัน

### Responsive Design
- เมนูปรับขนาดตามหน้าจอ
- การ์ดแสดงในรูปแบบ grid ที่เหมาะสม
- ปุ่มมี hover effects และ animations

### Color Coding
- **Primary (Blue):** Add User
- **Success (Green):** User Management
- **Warning (Yellow):** Registration
- **Info (Light Blue):** Regular User Menu
- **Light (Gray):** Permission Info

## Testing Scenarios

### Admin User
1. Login ด้วย admin account
2. ควรเห็นเมนู admin ทั้งหมด
3. ควรเห็นข้อมูลสิทธิ์ครบถ้วน
4. สถานะแสดงเป็น "ผู้ดูแลระบบ"

### Regular User with View Permissions
1. Login ด้วย regular user ที่มีสิทธิ์ดู
2. ควรเห็นเมนู Regular User Menu
3. ไม่ควรเห็นเมนู admin
4. สถานะแสดงเป็น "ผู้ใช้ทั่วไป"

### Regular User without Permissions
1. Login ด้วย regular user ที่ไม่มีสิทธิ์พิเศษ
2. ไม่ควรเห็นเมนูใดๆ
3. สถานะแสดงเป็น "ผู้ใช้ทั่วไป"
4. สิทธิ์แสดงเป็น "ไม่มีสิทธิ์พิเศษ"

### Guest User
1. ไม่ login
2. ควรเห็นปุ่มลงทะเบียน
3. ไม่ควรเห็นเมนูอื่นๆ
4. ควรเห็นข้อความต้อนรับ

## Maintenance

### Adding New Permissions
1. เพิ่ม permission constant ใน PermissionManager
2. เพิ่มการตรวจสอบใน index.php
3. เพิ่มเมนูหรือปุ่มตามสิทธิ์
4. อัปเดต documentation

### Changing Admin Groups
แก้ไขใน `common/components/PermissionManager.php`:
```php
const ADMIN_GROUPS = [
    'CN=manage Ad_admin,CN=User,DC=rpphosp,DC=local',
    'CN=Administrators',
    'CN=Domain Admins',
];
```

## Troubleshooting

### Common Issues

1. **Menus Not Showing for Admin**
   - ตรวจสอบ LDAP group membership
   - ตรวจสอบ PermissionManager configuration
   - ตรวจสอบ session data

2. **Regular User Can See Admin Menus**
   - ตรวจสอบ permission checks
   - ตรวจสอบ admin detection logic
   - ตรวจสอบ cache (clear if needed)

3. **Permission Info Not Accurate**
   - ตรวจสอบ permission constants
   - ตรวจสอบ PermissionManager methods
   - ตรวจสอบ user session data

### Debug Commands
```bash
# Check RBAC status
php yii rbac/list

# Check user permissions
# (Use browser developer tools to check session data)
```
