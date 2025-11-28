# การแก้ไขปัญหาการตรวจสอบกลุ่ม LDAP

## ปัญหาที่พบ
การกำหนดค่าสำหรับ `CN=manage Ad_user,CN=User,DC=rpphosp,DC=local` และ `CN=manage Ad_admin,CN=User,DC=rpphosp,DC=local` ไม่ทำงานตามที่กำหนด ผู้ใช้ที่มี distinguishedName ตรงกับรายการเหล่านี้ยังไม่ได้รับสิทธิ์การเข้าถึงระบบตามกฎที่กำหนด

## สาเหตุของปัญหา

### 1. **ข้อมูล `memberof` ไม่ได้ถูกดึงมาจาก LDAP**
- ใน `LdapHelper.php` ไม่ได้ดึงข้อมูล `memberof` มาเก็บใน session
- ทำให้ระบบไม่สามารถตรวจสอบกลุ่มที่ผู้ใช้เป็นสมาชิกได้

### 2. **การตรวจสอบกลุ่มไม่ครอบคลุม**
- ระบบตรวจสอบเฉพาะจาก `memberof` เท่านั้น
- ไม่ได้ตรวจสอบจาก `distinguishedname` สำหรับกรณีที่ผู้ใช้อยู่ในกลุ่มโดยตรง

## การแก้ไข

### 1. **อัปเดต LdapHelper.php**
```php
// เพิ่ม 'memberof' ใน attributes ที่ดึงมาจาก LDAP
$attributes = [
    'cn', 'samaccountname', 'displayname', 'department',
    'mail', 'useraccountcontrol', 'ou', 'distinguishedname',
    'telephonenumber', 'memberof'  // เพิ่มบรรทัดนี้
];

// เพิ่ม memberof ในข้อมูลที่ส่งกลับ
$returnData = [
    'cn' => $userData['cn'][0] ?? $userData['displayname'][0] ?? '',
    'samaccountname' => $userData['samaccountname'][0] ?? '',
    'displayname' => $userData['displayname'][0] ?? '',
    'department' => $userData['department'][0] ?? '',
    'mail' => $userData['mail'][0] ?? '',
    'ou' => $userData['ou'][0] ?? '',
    'distinguishedname' => $userData['distinguishedname'][0] ?? '',
    'telephonenumber' => $userData['telephonenumber'][0] ?? '',
    'memberof' => isset($userData['memberof']) ? array_slice($userData['memberof'], 1) : []  // เพิ่มบรรทัดนี้
];
```

### 2. **อัปเดต PermissionManager.php**

#### เพิ่มการตรวจสอบจาก distinguishedname
```php
private function isInAdminGroups($userData)
{
    // Check memberof groups
    $userGroups = isset($userData['memberof']) ? $userData['memberof'] : [];
    
    foreach ($userGroups as $group) {
        foreach (self::ADMIN_GROUPS as $adminGroup) {
            if (stripos($group, $adminGroup) !== false) {
                Yii::debug("User is in admin group: $group matches $adminGroup");
                return true;
            }
        }
    }
    
    // Check distinguishedname for direct group membership
    if (isset($userData['distinguishedname'])) {
        $dn = $userData['distinguishedname'];
        foreach (self::ADMIN_GROUPS as $adminGroup) {
            if (stripos($dn, $adminGroup) !== false) {
                Yii::debug("User DN matches admin group: $dn contains $adminGroup");
                return true;
            }
        }
    }
    
    return false;
}
```

#### เพิ่มการตรวจสอบสำหรับ superuser
```php
private function isInSuperUserGroups($userData)
{
    // Check memberof groups
    $userGroups = isset($userData['memberof']) ? $userData['memberof'] : [];
    
    foreach ($userGroups as $group) {
        foreach (self::SUPERUSER_GROUPS as $superGroup) {
            if (stripos($group, $superGroup) !== false) {
                Yii::debug("User is in superuser group: $group matches $superGroup");
                return true;
            }
        }
    }
    
    // Check distinguishedname for direct group membership
    if (isset($userData['distinguishedname'])) {
        $dn = $userData['distinguishedname'];
        foreach (self::SUPERUSER_GROUPS as $superGroup) {
            if (stripos($dn, $superGroup) !== false) {
                Yii::debug("User DN matches superuser group: $dn contains $superGroup");
                return true;
            }
        }
    }
    
    return false;
}
```

#### เพิ่ม debug logging
```php
public function isLdapAdmin()
{
    $userData = $this->getCurrentUserLdapData();
    if (!$userData) {
        Yii::debug("No user data found in session");
        return false;
    }
    
    Yii::debug("Checking admin status for user: " . ($userData['samaccountname'] ?? 'unknown'));
    Yii::debug("User DN: " . ($userData['distinguishedname'] ?? 'not set'));
    Yii::debug("User groups: " . print_r($userData['memberof'] ?? [], true));
    
    // ... rest of the method
}
```

### 3. **อัปเดต RBAC System**
- รันคำสั่ง `php yii rbac/init` เพื่อสร้าง role `superuser`
- กำหนดสิทธิ์เริ่มต้นให้ superuser: `adUserView`, `ldapUserView`

## การทดสอบ

### 1. **ตรวจสอบข้อมูล LDAP**
- ตรวจสอบว่า `memberof` ถูกดึงมาจาก LDAP และเก็บใน session
- ตรวจสอบว่า `distinguishedname` มีข้อมูลครบถ้วน

### 2. **ตรวจสอบการกำหนดสิทธิ์**
- ผู้ใช้ในกลุ่ม `CN=manage Ad_admin,CN=User,DC=rpphosp,DC=local` ควรได้รับสิทธิ์ admin
- ผู้ใช้ในกลุ่ม `CN=manage Ad_user,CN=User,DC=rpphosp,DC=local` ควรได้รับสิทธิ์ superuser

### 3. **ตรวจสอบ Debug Logs**
- เปิด debug mode ใน Yii2
- ตรวจสอบ logs เพื่อดูการทำงานของระบบตรวจสอบสิทธิ์

## ไฟล์ที่แก้ไข

1. **`common/components/LdapHelper.php`**
   - เพิ่ม `memberof` ใน attributes
   - เพิ่ม `memberof` ในข้อมูลที่ส่งกลับ

2. **`common/components/PermissionManager.php`**
   - ปรับปรุงการตรวจสอบกลุ่ม admin และ superuser
   - เพิ่มการตรวจสอบจาก `distinguishedname`
   - เพิ่ม debug logging

3. **`frontend/views/layouts/main.php`**
   - เพิ่มการตรวจสอบ `isSuperUser()` สำหรับเมนู

4. **`frontend/views/ldapuser/ou-user.php`**
   - เพิ่มการตรวจสอบ `isSuperUser()` สำหรับการเข้าถึงหน้า

## สรุป

การแก้ไขนี้จะทำให้ระบบสามารถ:
- ดึงข้อมูลกลุ่ม LDAP (`memberof`) ได้อย่างถูกต้อง
- ตรวจสอบสิทธิ์จากทั้ง `memberof` และ `distinguishedname`
- กำหนดสิทธิ์ admin และ superuser ตามกลุ่ม LDAP ที่กำหนด
- แสดง debug information เพื่อช่วยในการแก้ไขปัญหา

หลังจากแก้ไขแล้ว ระบบควรจะทำงานตามที่กำหนดไว้สำหรับกลุ่ม `CN=manage Ad_admin` และ `CN=manage Ad_user`
