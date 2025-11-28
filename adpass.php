<?php
// ตั้งค่าผู้ใช้และรหัสผ่านใหม่โดยตรงในโค้ด
// ⚠️ คำเตือน: วิธีนี้ไม่ปลอดภัยสำหรับการใช้งานจริง
$username = "newuser2";       // ชื่อผู้ใช้ sAMAccountName ที่คุณต้องการเปลี่ยนรหัสผ่าน
$newPassword = "P@ssw0rd@2025"; // รหัสผ่านใหม่ที่ต้องเป็นไปตาม Password Policy ของ AD

// ตั้งค่า LDAP
$ldapServer = "ldaps://rpp-srv-ad.rpphosp.local";
$ldapUser   = "arin@rpphosp.local";
$ldapPass   = "o6Udojkiydot";
$baseDn     = "DC=rpphosp,DC=local"; // Base DN ของ AD

// เข้ารหัสรหัสผ่านใหม่สำหรับ AD
$newPasswordEnc = mb_convert_encoding('"' . $newPassword . '"', "UTF-16LE");

// Connect to LDAP arserver
$ldapConn = ldap_connect($ldapServer,636);

if ($ldapConn) {
    // ตั้งค่าโปรโตคอล LDAP
    ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);

    ldap_set_option(NULL,LDAP_OPT_X_TLS_REQUIRE_CERT,LDAP_OPT_X_TLS_NEVER);

    // Bind ด้วย Service Account
    if (@ldap_bind($ldapConn, $ldapUser, $ldapPass)) {
        
        // ค้นหา Distinguished Name (DN) ของผู้ใช้
        $filter = "(&(objectCategory=person)(objectClass=user)(sAMAccountName=$username))";
        $searchResult = ldap_search($ldapConn, $baseDn, $filter, array("distinguishedname"));
        $entries = ldap_get_entries($ldapConn, $searchResult);

        if ($entries["count"] > 0) {
            $userDn = $entries[0]["distinguishedname"][0];

            // 1. ตั้งรหัสผ่านใหม่
            $entry = array();
            $entry["unicodePwd"] = $newPasswordEnc;
            
            if (@ldap_modify($ldapConn, $userDn, $entry)) {
                echo "✅ ตั้งรหัสผ่านสำเร็จ<br>";
                
                // 2. ตั้งค่า User Account Control ให้เป็น Normal Account (512)
                $uacEntry = array();
                $uacEntry["userAccountControl"] = 512;
                
                if (@ldap_modify($ldapConn, $userDn, $uacEntry)) {
                    echo "✅ บัญชีถูกเปิดใช้งานเรียบร้อยแล้ว";
                } else {
                    echo "❌ เปิดใช้งานบัญชีล้มเหลว: " . ldap_error($ldapConn);
                }
            } else {
                echo "❌ ตั้งรหัสผ่านล้มเหลว: " . ldap_error($ldapConn);
            }
        } else {
            echo "❌ ไม่พบผู้ใช้งาน '$username' ในระบบ Active Directory";
        }
    } else {
        echo "❌ Bind ไม่สำเร็จ: " . ldap_error($ldapConn);
    }
    ldap_close($ldapConn);
} else {
    echo "❌ Connect ไม่สำเร็จ";
}
?>