# Admin-Only Pages Implementation

## Overview
หน้า `ou-register.php` และ `ou-user.php` ได้ถูกปรับปรุงให้แสดงเฉพาะสำหรับ admin role เท่านั้น โดยใช้ PermissionManager และ AccessControl

## Changes Made

### 1. View Files Updated

#### `frontend/views/ldapuser/ou-register.php`
- เพิ่ม `use common\components\PermissionManager;`
- เพิ่มการตรวจสอบสิทธิ์ admin:
```php
// Check if user has admin permissions
$permissionManager = new PermissionManager();
if (!$permissionManager->isLdapAdmin()) {
    throw new ForbiddenHttpException('คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้ เฉพาะผู้ดูแลระบบเท่านั้น');
}
```

#### `frontend/views/ldapuser/ou-user.php`
- เพิ่ม `use common\components\PermissionManager;`
- เพิ่มการตรวจสอบสิทธิ์ admin:
```php
// Check if user has admin permissions
$permissionManager = new PermissionManager();
if (!$permissionManager->isLdapAdmin()) {
    throw new ForbiddenHttpException('คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้ เฉพาะผู้ดูแลระบบเท่านั้น');
}
```

### 2. Controller Updated

#### `frontend/controllers/LdapuserController.php`
- เพิ่ม `use common\components\PermissionManager;`
- ปรับปรุง AccessControl rules:
```php
[
    'actions' => ['ou-register', 'ou-user'], // Admin only pages
    'allow' => true,
    'roles' => ['@'],
    'matchCallback' => function ($rule, $action) {
        $permissionManager = new PermissionManager();
        return $permissionManager->isLdapAdmin();
    }
],
```

- เพิ่มการตรวจสอบสิทธิ์ใน action methods:
```php
public function actionOuRegister()
{
    // Check if user has admin permissions
    $permissionManager = new PermissionManager();
    if (!$permissionManager->isLdapAdmin()) {
        Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้ เฉพาะผู้ดูแลระบบเท่านั้น');
        return $this->redirect(['index']);
    }
    // ... rest of method
}

public function actionOuUser()
{
    // Check if user has admin permissions
    $permissionManager = new PermissionManager();
    if (!$permissionManager->isLdapAdmin()) {
        Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้ เฉพาะผู้ดูแลระบบเท่านั้น');
        return $this->redirect(['index']);
    }
    // ... rest of method
}
```

## Security Features

### Multi-Layer Protection
1. **AccessControl Filter** - ป้องกันการเข้าถึงในระดับ controller
2. **View-Level Check** - ตรวจสอบสิทธิ์ใน view files
3. **Action-Level Check** - ตรวจสอบสิทธิ์ใน action methods

### Admin Detection
ระบบจะตรวจสอบ admin status จาก:
- LDAP Groups: `CN=manage Ad_admin,CN=User,DC=rpphosp,DC=local`
- LDAP Groups: `CN=Administrators`, `CN=Domain Admins`
- IT OU Membership: `OU=IT,OU=rpp-user,DC=rpphosp,DC=local`

## Access Control Rules

### Admin Only Access
- `ou-register` - หน้าแสดงผู้ใช้ใน Register OU
- `ou-user` - หน้าแสดงผู้ใช้ทั้งหมดใน Domain

### Error Handling
- **403 Forbidden** - เมื่อผู้ใช้ไม่มีสิทธิ์ admin
- **Redirect to Index** - เมื่อเข้าถึงผ่าน controller
- **Thai Error Messages** - ข้อความแจ้งเตือนเป็นภาษาไทย

## Usage

### For Admin Users
- สามารถเข้าถึงหน้า `ou-register` และ `ou-user` ได้ปกติ
- สามารถจัดการผู้ใช้ในระบบได้

### For Regular Users
- ไม่สามารถเข้าถึงหน้า admin-only ได้
- จะได้รับข้อความแจ้งเตือนและ redirect ไปหน้า index

## Testing

### Test Cases
1. **Admin User Access**
   - Login ด้วย admin account
   - เข้าถึง `/ldapuser/ou-register` - ควรแสดงหน้าได้
   - เข้าถึง `/ldapuser/ou-user` - ควรแสดงหน้าได้

2. **Regular User Access**
   - Login ด้วย regular user account
   - เข้าถึง `/ldapuser/ou-register` - ควรได้รับ 403 error
   - เข้าถึง `/ldapuser/ou-user` - ควรได้รับ 403 error

3. **Guest User Access**
   - ไม่ login
   - เข้าถึง `/ldapuser/ou-register` - ควรได้รับ 403 error
   - เข้าถึง `/ldapuser/ou-user` - ควรได้รับ 403 error

## Maintenance

### Adding New Admin-Only Pages
1. เพิ่ม action ใน AccessControl rules
2. เพิ่มการตรวจสอบสิทธิ์ใน action method
3. เพิ่มการตรวจสอบสิทธิ์ใน view file (ถ้าจำเป็น)

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

1. **403 Error for Admin Users**
   - ตรวจสอบ LDAP group membership
   - ตรวจสอบ session data
   - ตรวจสอบ PermissionManager configuration

2. **Pages Still Accessible to Non-Admins**
   - ตรวจสอบ AccessControl rules
   - ตรวจสอบ matchCallback function
   - ตรวจสอบ cache (clear if needed)

3. **Error Messages Not in Thai**
   - ตรวจสอบ ForbiddenHttpException messages
   - ตรวจสอบ session flash messages

### Debug Commands
```bash
# Check RBAC status
php yii rbac/list

# Check user permissions
# (Use browser developer tools to check session data)
```
