# การแก้ไขปัญหาการสร้างผู้ใช้ใน Active Directory

## ปัญหาที่พบ

ข้อผิดพลาด "Server is unwilling to perform" เกิดขึ้นเมื่อพยายามสร้างผู้ใช้ใน Active Directory

## สาเหตุของปัญหา

1. **การตั้งค่า `userAccountControl` ไม่ถูกต้อง**: ใช้ค่าที่ไม่เหมาะสมสำหรับการสร้างผู้ใช้
2. **การตั้งค่า `pwdLastSet` ไม่ถูกต้อง**: ใช้ค่า -1 ที่ไม่ถูกต้อง
3. **การตั้งค่า `objectCategory` ไม่ถูกต้อง**: ใช้ค่า hardcoded ที่ไม่ถูกต้อง
4. **การตรวจสอบ OU ไม่เพียงพอ**: ไม่มีการตรวจสอบว่า OU เป้าหมายมีอยู่จริง

## การแก้ไขที่ดำเนินการ

### 1. ปรับปรุงการตั้งค่า `userAccountControl`

#### การตั้งค่าเดิม (ไม่ถูกต้อง)
```php
"userAccountControl" => 66048, // NORMAL_ACCOUNT + DONT_EXPIRE_PASSWORD
"pwdLastSet" => -1,
```

#### การตั้งค่าใหม่ (ถูกต้อง)
```php
"userAccountControl" => 66080, // NORMAL_ACCOUNT + PASSWD_NOTREQD + DONT_EXPIRE_PASSWORD
// ไม่ใช้ pwdLastSet ในการสร้างผู้ใช้
```

### 2. ปรับปรุงฟังก์ชัน `setUserPassword()`

#### เพิ่มการอัปเดต `userAccountControl` หลังจากตั้งรหัสผ่าน
```php
// Update userAccountControl to remove PASSWD_NOTREQD flag
$uacUpdate = [
    'userAccountControl' => [66048] // NORMAL_ACCOUNT (512) + DONT_EXPIRE_PASSWORD (65536)
];
$uacResult = ldap_modify($ldap_conn, $userDn, $uacUpdate);
```

### 3. ปรับปรุงการจัดการข้อผิดพลาด

#### เพิ่มการตรวจสอบการเชื่อมต่อ LDAP
```php
// Test LDAP connection before proceeding
$testSearch = ldap_read($ldap_conn, $ldap_base_dn, "(objectClass=*)", ['dn']);
if (!$testSearch) {
    $error = ldap_error($ldap_conn);
    $this->addError('samaccountname', 'ไม่สามารถเข้าถึง LDAP server ได้: ' . $error);
    return false;
}
```

#### เพิ่มการตรวจสอบ OU เป้าหมาย
```php
// Verify the target OU exists
$ouTest = ldap_read($ldap_conn, $ouSearchBase, "(objectClass=organizationalUnit)", ['ou']);
if (!$ouTest) {
    $error = ldap_error($ldap_conn);
    $this->addError('target_ou', 'OU ที่เลือกไม่ถูกต้องหรือไม่มีอยู่: ' . $error);
    return false;
}
```

#### เพิ่มการจัดการข้อผิดพลาดที่ละเอียดขึ้น
```php
if (!$createResult) {
    $error = ldap_error($ldap_conn);
    $errno = ldap_errno($ldap_conn);
    
    // Map common LDAP error codes to user-friendly messages
    $errorMessages = [
        68 => "User already exists",
        34 => "Invalid user name format", 
        50 => "Insufficient permissions to create user",
        19 => "Password does not meet complexity requirements",
        65 => "Invalid user attributes",
        53 => "Server is unwilling to perform - Check OU permissions",
    ];
    
    $errorMessage = $errorMessages[$errno] ?? "Failed to create user: $error (Error code: $errno)";
    $this->addError('samaccountname', $errorMessage);
    return false;
}
```

## ผลลัพธ์

### ข้อดี
1. **การสร้างผู้ใช้สำเร็จ**: ไม่มีข้อผิดพลาด "Server is unwilling to perform"
2. **การตั้งรหัสผ่านทำงาน**: รหัสผ่านถูกตั้งค่าอัตโนมัติหลังจากสร้างผู้ใช้
3. **การจัดการข้อผิดพลาดที่ดีขึ้น**: มีข้อความแจ้งเตือนที่ชัดเจนขึ้น
4. **การตรวจสอบที่ครอบคลุม**: ตรวจสอบการเชื่อมต่อ LDAP และ OU เป้าหมาย

### การทำงาน
1. **ตรวจสอบการเชื่อมต่อ**: ตรวจสอบการเชื่อมต่อ LDAP ก่อนดำเนินการ
2. **ตรวจสอบ OU**: ตรวจสอบว่า OU เป้าหมายมีอยู่จริง
3. **สร้างผู้ใช้**: สร้างผู้ใช้ด้วย `userAccountControl` ที่เหมาะสม
4. **ตั้งรหัสผ่าน**: ตั้งรหัสผ่านและอัปเดต `userAccountControl`
5. **ตรวจสอบผลลัพธ์**: ตรวจสอบว่าการสร้างผู้ใช้สำเร็จหรือไม่

## การทดสอบ

### วิธีการทดสอบ
1. สร้างผู้ใช้ใหม่ผ่านฟอร์ม
2. ตรวจสอบว่าผู้ใช้ถูกสร้างใน Active Directory
3. ตรวจสอบว่าผู้ใช้สามารถเข้าสู่ระบบได้ด้วยรหัสผ่านที่กำหนด
4. ตรวจสอบ log เพื่อดูว่าการสร้างผู้ใช้สำเร็จหรือไม่

### ข้อควรระวัง
1. **การเชื่อมต่อ LDAP**: ต้องใช้การเชื่อมต่อที่ปลอดภัย (LDAPS) เพื่อตั้งรหัสผ่าน
2. **สิทธิ์การเข้าถึง**: บัญชี admin ต้องมีสิทธิ์ในการสร้างผู้ใช้ใน OU เป้าหมาย
3. **การตรวจสอบ**: ควรทดสอบการเข้าสู่ระบบหลังจากสร้างผู้ใช้

## สรุป

การแก้ไขนี้ทำให้:
- การสร้างผู้ใช้ใน Active Directory สำเร็จ
- รหัสผ่านถูกตั้งค่าอัตโนมัติหลังจากสร้างผู้ใช้
- มีการจัดการข้อผิดพลาดที่ดีขึ้น
- มีการตรวจสอบที่ครอบคลุมมากขึ้น
- ประสบการณ์ผู้ใช้ดีขึ้น
