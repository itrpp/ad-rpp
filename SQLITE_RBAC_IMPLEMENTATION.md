# SQLite RBAC Implementation - Problem Resolution

## Problem Description
เกิด TypeError: Illegal offset type ใน PhpManager ของ Yii2 เมื่อพยายามใช้ไฟล์ PHP สำหรับ RBAC configuration

## Root Cause Analysis
1. **PhpManager Format Issue**: PhpManager ของ Yii2 คาดหวังรูปแบบข้อมูลที่แตกต่างจากที่เราสร้าง
2. **Console Application Missing Components**: Console application ไม่มี authManager component
3. **Database Dependency**: ระบบยังต้องการฐานข้อมูลสำหรับ RBAC แม้ว่าจะไม่ใช้ MySQL

## Solution Implemented

### 1. Switch to SQLite Database
แทนที่จะใช้ PhpManager ที่มีปัญหา เราเปลี่ยนไปใช้ DbManager กับ SQLite:

#### `common/config/main-local.php`
```php
'db' => [
    'class' => \yii\db\Connection::class,
    'dsn' => 'sqlite:' . __DIR__ . '/../../runtime/rbac.db',
    'charset' => 'utf8',
],
```

#### `frontend/config/main.php`
```php
'authManager' => [
    'class' => 'yii\rbac\DbManager',
    'db' => 'db',
    'itemTable' => 'auth_item',
    'itemChildTable' => 'auth_item_child',
    'assignmentTable' => 'auth_assignment',
    'ruleTable' => 'auth_rule',
],
```

### 2. Console Application Configuration
เพิ่ม authManager ใน console application:

#### `console/config/main.php`
```php
'components' => [
    'db' => [
        'class' => \yii\db\Connection::class,
        'dsn' => 'sqlite:' . __DIR__ . '/../../runtime/rbac.db',
        'charset' => 'utf8',
    ],
    'authManager' => [
        'class' => 'yii\rbac\DbManager',
        'db' => 'db',
        'itemTable' => 'auth_item',
        'itemChildTable' => 'auth_item_child',
        'assignmentTable' => 'auth_assignment',
        'ruleTable' => 'auth_rule',
    ],
    // ... other components
],
```

### 3. Migration for SQLite Tables
สร้าง migration สำหรับ RBAC tables:

#### `console/migrations/m251028_044423_create_rbac_tables.php`
```php
public function safeUp()
{
    $this->createTable('{{%auth_rule}}', [
        'name' => $this->string(64)->notNull(),
        'data' => $this->binary(),
        'created_at' => $this->integer(),
        'updated_at' => $this->integer(),
        'PRIMARY KEY ([[name]])',
    ]);

    $this->createTable('{{%auth_item}}', [
        'name' => $this->string(64)->notNull(),
        'type' => $this->smallInteger()->notNull(),
        'description' => $this->text(),
        'rule_name' => $this->string(64),
        'data' => $this->binary(),
        'created_at' => $this->integer(),
        'updated_at' => $this->integer(),
        'PRIMARY KEY ([[name]])',
        'FOREIGN KEY ([[rule_name]]) REFERENCES {{%auth_rule}} ([[name]]) ON DELETE SET NULL ON UPDATE CASCADE',
    ]);
    $this->createIndex('idx-auth_item-type', '{{%auth_item}}', 'type');

    $this->createTable('{{%auth_item_child}}', [
        'parent' => $this->string(64)->notNull(),
        'child' => $this->string(64)->notNull(),
        'PRIMARY KEY ([[parent]], [[child]])',
        'FOREIGN KEY ([[parent]]) REFERENCES {{%auth_item}} ([[name]]) ON DELETE CASCADE ON UPDATE CASCADE',
        'FOREIGN KEY ([[child]]) REFERENCES {{%auth_item}} ([[name]]) ON DELETE CASCADE ON UPDATE CASCADE',
    ]);

    $this->createTable('{{%auth_assignment}}', [
        'item_name' => $this->string(64)->notNull(),
        'user_id' => $this->string(64)->notNull(),
        'created_at' => $this->integer(),
        'PRIMARY KEY ([[item_name]], [[user_id]])',
        'FOREIGN KEY ([[item_name]]) REFERENCES {{%auth_item}} ([[name]]) ON DELETE CASCADE ON UPDATE CASCADE',
    ]);
    $this->createIndex('idx-auth_assignment-user_id', '{{%auth_assignment}}', 'user_id');
}
```

### 4. PermissionManager Updates
ปรับปรุง PermissionManager ให้ทำงานกับ console application:

#### `common/components/PermissionManager.php`
```php
public function __construct()
{
    $this->authManager = Yii::$app->has('authManager') ? Yii::$app->authManager : null;
    $this->user = Yii::$app->has('user') ? Yii::$app->user : null;
}

private function createPermissions()
{
    if (!$this->authManager) {
        throw new \Exception('AuthManager is not available');
    }
    // ... rest of method
}
```

### 5. RbacController Updates
ปรับปรุง RbacController ให้ทำงานกับ SQLite:

#### `console/controllers/RbacController.php`
```php
public function actionInit()
{
    $this->stdout("Initializing RBAC system with SQLite...\n");
    // ... implementation
}

public function actionList()
{
    $this->stdout("RBAC Roles and Permissions (SQLite):\n\n");
    // ... implementation
}
```

## Benefits of SQLite Solution

### 1. Lightweight Database
- ไม่ต้องติดตั้ง MySQL server
- ไฟล์ฐานข้อมูลเดียว: `runtime/rbac.db`
- ง่ายต่อการ backup และ deploy

### 2. Full RBAC Support
- ใช้ DbManager ที่เสถียร
- รองรับการจัดการ roles และ permissions แบบเต็มรูปแบบ
- Compatible กับ Yii2 framework

### 3. Easy Management
- ใช้ console commands สำหรับการจัดการ
- Migration system สำหรับการอัปเดต schema
- ไม่ต้องจัดการไฟล์ PHP configuration

## Setup Commands

### 1. Run Migrations
```bash
php yii migrate
```

### 2. Initialize RBAC
```bash
php yii rbac/init
```

### 3. List RBAC Data
```bash
php yii rbac/list
```

### 4. Assign Admin Role
```bash
php yii rbac/assign-admin <username>
```

### 5. Clear RBAC Data
```bash
php yii rbac/clear
```

## File Structure

```
runtime/
└── rbac.db                    # SQLite database for RBAC

console/
├── config/
│   ├── main.php              # Console app config with authManager
│   └── main-local.php        # Console app local config
├── controllers/
│   └── RbacController.php    # RBAC management commands
└── migrations/
    └── m251028_044423_create_rbac_tables.php

common/
├── config/
│   └── main-local.php        # SQLite database config
└── components/
    └── PermissionManager.php  # Updated for console compatibility

frontend/
└── config/
    └── main.php              # Frontend app config with authManager
```

## Testing Results

### RBAC Initialization
```
Initializing RBAC system with SQLite...
RBAC system initialized successfully!
Using SQLite database: runtime/rbac.db

Created permissions:
- adUserView
- adUserCreate
- adUserUpdate
- adUserDelete
- ldapUserView
- ldapUserCreate
- ldapUserUpdate
- ldapUserDelete

Created roles:
- admin (with all permissions)
- user (with view permissions only)
- guest
```

### RBAC List
```
RBAC Roles and Permissions (SQLite):

ROLES:
- admin: Administrator
- user: Regular User
- guest: Guest User

PERMISSIONS:
- adUserView: View AD Users
- adUserCreate: Create AD Users
- adUserUpdate: Update AD Users
- adUserDelete: Delete AD Users
- ldapUserView: View LDAP Users
- ldapUserCreate: Create LDAP Users
- ldapUserUpdate: Update LDAP Users
- ldapUserDelete: Delete LDAP Users

ROLE-PERMISSION ASSIGNMENTS:

Role: admin
  - adUserView
  - adUserCreate
  - adUserUpdate
  - adUserDelete
  - ldapUserView
  - ldapUserCreate
  - ldapUserUpdate
  - ldapUserDelete

Role: user
  - adUserView
  - ldapUserView

Role: guest

DATABASE LOCATION:
- SQLite: runtime/rbac.db
```

## Troubleshooting

### Common Issues

1. **SQLite Database Not Created**
   - ตรวจสอบ permissions ของ `runtime/` directory
   - รัน `php yii migrate` ก่อน

2. **AuthManager Not Available**
   - ตรวจสอบ console config มี authManager
   - ตรวจสอบ database connection

3. **Permission Checks Failing**
   - ตรวจสอบ RBAC initialization
   - ตรวจสอบ LDAP connection
   - ตรวจสอบ user session data

### Debug Commands
```bash
# Check database connection
php yii migrate/history

# Check RBAC status
php yii rbac/list

# Test LDAP connection
# (Check logs for LDAP errors)
```

## Migration from PhpManager to SQLite

### Before (PhpManager)
- RBAC data stored in PHP files
- Complex file format requirements
- Console application compatibility issues

### After (SQLite)
- RBAC data stored in SQLite database
- Standard DbManager usage
- Full console application support

## Security Considerations

### SQLite Database
- ไฟล์ `runtime/rbac.db` ควรมี permissions ที่เหมาะสม
- ไม่ควรให้ web server เขียนไฟล์ได้โดยตรง
- ใช้ console commands สำหรับการจัดการ

### LDAP Integration
- ใช้ secure LDAP connection
- ตรวจสอบ certificate
- ใช้ strong authentication

### Access Control
- ตรวจสอบสิทธิ์ในหลายระดับ
- ใช้ PermissionManager สำหรับการตรวจสอบ
- Log การเข้าถึงที่สำคัญ

## Performance Considerations

### SQLite Benefits
- Fast read operations
- No network overhead
- Single file database

### Optimization Tips
- Regular database maintenance
- Proper indexing (already implemented)
- Connection pooling (if needed)

## Maintenance

### Backup RBAC Database
```bash
# Backup SQLite database
cp runtime/rbac.db backup/rbac-$(date +%Y%m%d).db
```

### Adding New Permissions
1. เพิ่ม constant ใน PermissionManager
2. อัปเดต createPermissions() method
3. รัน `php yii rbac/init` อีกครั้ง

### Adding New Roles
1. เพิ่ม constant ใน PermissionManager
2. อัปเดต createRoles() method
3. อัปเดต assignPermissionsToRoles() method
4. รัน `php yii rbac/init` อีกครั้ง
