# RBAC System Implementation Guide

## Overview
ระบบสิทธิ์การเข้าถึง (RBAC - Role-Based Access Control) ได้ถูกปรับปรุงให้ใช้ Yii2 RBAC system อย่างเต็มรูปแบบ พร้อมกับ centralized permission management

**สำคัญ:** ระบบ Registration ไม่ต้องการการ login - ผู้ใช้ที่ไม่ login สามารถสร้างผู้ใช้ใหม่ได้

## Key Components

### 1. PermissionManager Component
**Location:** `common/components/PermissionManager.php`

**Features:**
- Centralized permission management
- LDAP group-based admin detection
- RBAC integration
- Support for custom admin groups

**Admin Groups Configuration:**
```php
const ADMIN_GROUPS = [
    'CN=manage Ad_admin,CN=User,DC=rpphosp,DC=local',
    'CN=Administrators',
    'CN=Domain Admins',
];
```

### 2. RBAC Configuration
**Location:** `frontend/config/main.php`

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

### 3. Database Tables
Migration file: `console/migrations/m20241201_000000_create_rbac_tables.php`

Tables created:
- `auth_rule` - RBAC rules
- `auth_item` - Roles and permissions
- `auth_item_child` - Role-permission relationships
- `auth_assignment` - User-role assignments

## Permissions

### AD User Permissions
- `adUserView` - View AD users (Open to all)
- `adUserCreate` - Create AD users (Open to all - No login required)
- `adUserUpdate` - Update AD users (Admin only)
- `adUserDelete` - Delete AD users (Admin only)

### LDAP User Permissions
- `ldapUserView` - View LDAP users
- `ldapUserCreate` - Create LDAP users
- `ldapUserUpdate` - Update LDAP users
- `ldapUserDelete` - Delete LDAP users

## Roles

### Admin Role
- Has all permissions
- Assigned to users in admin groups or IT OU
- Can perform all operations including update/delete

### User Role
- Has view permissions only
- Assigned to regular users
- Limited access

### Guest Role
- Can view and create users (registration)
- No update/delete permissions
- For non-authenticated users

## Access Control Rules

### Open Access (No Login Required)
- `actionIndex` - View user list
- `actionView` - View individual user
- `actionCreate` - Create new user (Registration)
- `actionCheckUsername` - Check username availability

### Admin Only Access
- `actionUpdate` - Update user information
- `actionDelete` - Delete user

## Setup Instructions

### 1. Run Migration
```bash
php yii migrate
```

### 2. Initialize RBAC
```bash
php yii rbac/init
```

### 3. Assign Admin Role (Optional)
```bash
php yii rbac/assign-admin <username>
```

## Usage Examples

### In Controllers
```php
use common\components\PermissionManager;

// For admin-only actions
public function actionUpdate()
{
    $permissionManager = new PermissionManager();
    
    if (!$permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_UPDATE)) {
        Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ในการแก้ไขข้อมูลผู้ใช้ AD');
        return $this->redirect(['index']);
    }
    
    // Controller logic here
}

// For open registration (no permission check needed)
public function actionCreate()
{
    // No permission check needed - registration is open to everyone
    // Controller logic here
}
```

### In Views
```php
use common\components\PermissionManager;

$permissionManager = new PermissionManager();

// Always show create button (registration is open)
echo Html::a('Create User', ['create'], ['class' => 'btn btn-primary']);

// Only show update/delete for admins
if ($permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_UPDATE)) {
    echo Html::a('Update', ['update', 'cn' => $model->cn], ['class' => 'btn btn-warning']);
}

if ($permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_DELETE)) {
    echo Html::a('Delete', ['delete', 'cn' => $model->cn], [
        'class' => 'btn btn-danger',
        'data-confirm' => 'Are you sure?',
        'data-method' => 'post'
    ]);
}
```

## Admin Group Configuration

The system recognizes the following as admin groups:
- `CN=manage Ad_admin,CN=User,DC=rpphosp,DC=local`
- `CN=Administrators`
- `CN=Domain Admins`
- Users in `OU=IT,OU=rpp-user,DC=rpphosp,DC=local`

## Console Commands

### Initialize RBAC
```bash
php yii rbac/init
```

### List Roles and Permissions
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

## Security Features

1. **LDAP Integration** - Admin status determined by LDAP groups
2. **RBAC Database** - Permissions stored in database
3. **Centralized Management** - All permission logic in PermissionManager
4. **Role-based Access** - Different access levels for different roles
5. **Session-based Authentication** - User data stored in session
6. **Open Registration** - No login required for user creation

## Registration Flow

1. **Guest User** visits registration page
2. **No Login Required** - Can access create form directly
3. **Username Check** - Available without authentication
4. **User Creation** - Creates user in LDAP Register OU
5. **Success Message** - Redirects to home page

## Migration from Old System

The old permission system has been replaced with:
- Centralized PermissionManager
- Database-based RBAC
- Standardized permission constants
- Improved security through role-based access
- Open registration system

## Troubleshooting

### Common Issues

1. **Permission Denied Errors**
   - Check if user is in admin groups
   - Verify RBAC initialization
   - Check user role assignments

2. **RBAC Not Working**
   - Ensure migration is run
   - Run `php yii rbac/init`
   - Check database connection

3. **Admin Group Not Recognized**
   - Verify group name in PermissionManager
   - Check LDAP data in session
   - Ensure user is properly authenticated

4. **Registration Not Working**
   - Check LDAP connection
   - Verify Register OU exists
   - Check username availability

### Debug Commands
```bash
# Check RBAC status
php yii rbac/list

# Reinitialize RBAC
php yii rbac/clear
php yii rbac/init
```
