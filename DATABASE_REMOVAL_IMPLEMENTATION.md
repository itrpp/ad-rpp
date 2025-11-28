# Database Connection Removal - AD/LDAP Only Implementation

## Overview
ระบบได้ถูกปรับปรุงให้ยกเลิกการเชื่อมต่อฐานข้อมูล `192.168.238.211` และใช้ข้อมูลจาก Active Directory (AD) เท่านั้น โดยเปลี่ยนจาก DbManager เป็น PhpManager สำหรับ RBAC

## Changes Made

### 1. Database Connection Removal

#### `common/config/main-local.php`
```php
// Database connection removed - using AD/LDAP data only
// 'db' => [
//     'class' => \yii\db\Connection::class,
//     'dsn' => 'mysql:host=192.168.238.211;dbname=rpp-service',
//     'username' => 'root',
//     'password' => 'rpp14641',
//     'charset' => 'utf8',
// ],
```

### 2. RBAC Manager Change

#### `frontend/config/main.php`
เปลี่ยนจาก DbManager เป็น PhpManager:
```php
'authManager' => [
    'class' => 'yii\rbac\PhpManager',
    'itemFile' => '@app/rbac/items.php',
    'assignmentFile' => '@app/rbac/assignments.php',
    'ruleFile' => '@app/rbac/rules.php',
],
```

### 3. File-Based RBAC Configuration

#### `frontend/rbac/items.php`
กำหนด roles และ permissions:
```php
return [
    // Roles
    'admin' => [
        'type' => 1, // Role
        'description' => 'Administrator',
        // ...
    ],
    'user' => [
        'type' => 1, // Role
        'description' => 'Regular User',
        // ...
    ],
    'guest' => [
        'type' => 1, // Role
        'description' => 'Guest User',
        // ...
    ],

    // Permissions
    'adUserView' => [
        'type' => 2, // Permission
        'description' => 'View AD Users',
        // ...
    ],
    // ... more permissions
];
```

#### `frontend/rbac/assignments.php`
กำหนด role-permission assignments:
```php
return [
    'admin' => [
        'adUserView' => [...],
        'adUserCreate' => [...],
        // ... all permissions
    ],
    'user' => [
        'adUserView' => [...],
        'ldapUserView' => [...],
    ],
    'guest' => [],
];
```

#### `frontend/rbac/rules.php`
กำหนด custom rules (ว่างเปล่าสำหรับการใช้งานปัจจุบัน):
```php
return [
    // No custom rules needed for this implementation
    // All permission checks are handled by PermissionManager
];
```

### 4. PermissionManager Updates

#### `common/components/PermissionManager.php`
ปรับปรุง `initializeRbac()` method:
```php
public function initializeRbac()
{
    // For PhpManager, we don't need to create items programmatically
    // They are already defined in the PHP files
    Yii::info("RBAC system initialized with PhpManager - using file-based configuration");
}
```

### 5. Console Controller Updates

#### `console/controllers/RbacController.php`
ปรับปรุงให้ทำงานกับ PhpManager:

- **actionInit()** - แสดงข้อมูลไฟล์ RBAC
- **actionAssignAdmin()** - แก้ไขไฟล์ assignments.php
- **actionList()** - แสดงข้อมูลจากไฟล์
- **actionClear()** - ล้างข้อมูลในไฟล์ assignments.php

### 6. Migration Files Removal
ลบไฟล์ migration ที่ไม่จำเป็น:
- `console/migrations/m20241201_000000_create_rbac_tables.php`
- `console/migrations/m251028_043435_create_rbac_tables.php`

## Benefits of AD/LDAP Only Implementation

### 1. Simplified Architecture
- ไม่ต้องจัดการฐานข้อมูล MySQL
- ลดความซับซ้อนของระบบ
- ลดจุดที่อาจเกิดปัญหา

### 2. Centralized User Management
- ใช้ข้อมูลผู้ใช้จาก AD เท่านั้น
- ไม่มีการซิงค์ข้อมูลระหว่างระบบ
- ความสอดคล้องของข้อมูลสูง

### 3. File-Based RBAC
- ง่ายต่อการจัดการและ backup
- ไม่ต้องใช้ฐานข้อมูลสำหรับ RBAC
- สามารถ version control ได้

### 4. Security Benefits
- ลด attack surface
- ไม่มีข้อมูลสำคัญในฐานข้อมูล MySQL
- ใช้ LDAP authentication เท่านั้น

## Data Sources

### Primary Data Source: Active Directory
- **User Information:** จาก LDAP/AD
- **Authentication:** LDAP authentication
- **Group Membership:** LDAP groups
- **Permissions:** ตรวจสอบจาก LDAP groups

### RBAC Configuration: PHP Files
- **Roles:** `frontend/rbac/items.php`
- **Permissions:** `frontend/rbac/items.php`
- **Assignments:** `frontend/rbac/assignments.php`
- **Rules:** `frontend/rbac/rules.php`

## Admin Detection Logic

ระบบตรวจสอบ admin status จาก:

### LDAP Groups
- `CN=manage Ad_admin,CN=User,DC=rpphosp,DC=local`
- `CN=Administrators`
- `CN=Domain Admins`

### OU Membership
- `OU=IT,OU=rpp-user,DC=rpphosp,DC=local`

## Usage Commands

### Initialize RBAC
```bash
php yii rbac/init
```

### List RBAC Data
```bash
php yii rbac/list
```

### Assign Admin Role
```bash
php yii rbac/assign-admin <username>
```

### Clear RBAC Data
```bash
php yii rbac/clear
```

## File Structure

```
frontend/
├── rbac/
│   ├── items.php          # Roles and permissions
│   ├── assignments.php     # Role-permission assignments
│   └── rules.php          # Custom rules
└── config/
    └── main.php           # PhpManager configuration

common/
├── components/
│   └── PermissionManager.php  # Updated for PhpManager
└── config/
    └── main-local.php        # Database connection removed

console/
└── controllers/
    └── RbacController.php    # Updated for PhpManager
```

## Migration from Database to File-Based

### Before (Database)
- RBAC data stored in MySQL tables
- Required database connection
- Complex migration management

### After (File-Based)
- RBAC data stored in PHP files
- No database dependency
- Simple file management

## Troubleshooting

### Common Issues

1. **RBAC Files Not Found**
   - ตรวจสอบว่าไฟล์อยู่ใน `frontend/rbac/`
   - ตรวจสอบ permissions ของไฟล์

2. **Permission Checks Failing**
   - ตรวจสอบ LDAP connection
   - ตรวจสอบ group membership
   - ตรวจสอบ PermissionManager logic

3. **Admin Detection Not Working**
   - ตรวจสอบ LDAP groups
   - ตรวจสอบ OU membership
   - ตรวจสอบ session data

### Debug Commands
```bash
# Check RBAC configuration
php yii rbac/list

# Test LDAP connection
# (Check logs for LDAP errors)

# Check user session
# (Use browser developer tools)
```

## Maintenance

### Adding New Permissions
1. เพิ่มใน `frontend/rbac/items.php`
2. เพิ่มใน `frontend/rbac/assignments.php`
3. อัปเดต PermissionManager constants
4. อัปเดต documentation

### Adding New Roles
1. เพิ่มใน `frontend/rbac/items.php`
2. เพิ่มใน `frontend/rbac/assignments.php`
3. อัปเดต PermissionManager logic
4. อัปเดต documentation

### Backup RBAC Configuration
```bash
# Backup RBAC files
cp -r frontend/rbac/ backup/rbac-$(date +%Y%m%d)/
```

## Security Considerations

### File Permissions
- RBAC files ควรมี permissions ที่เหมาะสม
- ไม่ควรให้ web server เขียนไฟล์ได้
- ใช้ console commands สำหรับการแก้ไข

### LDAP Security
- ใช้ secure LDAP connection
- ตรวจสอบ certificate
- ใช้ strong authentication

### Access Control
- ตรวจสอบสิทธิ์ในหลายระดับ
- ใช้ PermissionManager สำหรับการตรวจสอบ
- Log การเข้าถึงที่สำคัญ
