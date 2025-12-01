<?php

namespace common\models;

use Yii;
use yii\base\Model;

class AdUser extends Model
{
    public $samaccountname;
    public $username;
    public $sername;
    public $email;
    public $department;
    public $telephone;
    public $password;
    public $target_ou; // Optional OU selection from UI
    public $confirm_password;
    public $agree_terms;
    
    // New fields for Active Directory
    public $personalTitle; // คำนำหน้าชื่อ --> personalTitle
    public $name_en; // ชื่อ-นามสกุล(อังกฤษ) --> Name
    public $title; // ตำแหน่ง --> Title
    public $id_card; // เลขบัตรประชาชน --> Description
    public $company; // บริษัทที่ติดต่อ --> Company
    public $ephis_code; // เลขรหัสจาก Ephis --> Office Name
    public $streetaddress; // รายละเอียดเพิ่มเติม -> streetAddress

    public function rules()
    {
        return [
            [['samaccountname', 'username', 'sername', 'personalTitle', 'department', 'title', 'password', 'confirm_password', 'streetaddress'], 'required'],
            [['email'], 'email'],
            [['target_ou', 'telephone', 'agree_terms', 'confirm_password', 'personalTitle', 'name_en', 'id_card', 'company', 'ephis_code'], 'safe'],
            [['samaccountname'], 'string', 'max' => 20],
            [['username', 'sername'], 'string', 'max' => 50],
            [['email', 'department'], 'string', 'max' => 100],
            [['telephone'], 'string', 'max' => 30],
            [['password'], 'string', 'min' => 4, 'max' => 50],
            [['name_en'], 'string', 'max' => 100],
            [['personalTitle'], 'string', 'max' => 20],
            [['title'], 'string', 'max' => 50],
            [['id_card'], 'string', 'max' => 13],
            [['company'], 'string', 'max' => 100],
            [['ephis_code'], 'string', 'max' => 20],
            [['id_card'], 'match', 'pattern' => '/^[0-9]{13}$/', 'message' => 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก'],
            [['id_card'], 'validateThaiIdCard'],
            [['samaccountname'], 'match', 'pattern' => '/^[A-Za-z0-9._]+$/', 'message' => 'ชื่อผู้ใช้อนุญาตเฉพาะ a-z, 0-9, ., _ และห้ามเว้นวรรค'],
            [['telephone'], 'match', 'pattern' => '/^\+?[0-9\-()\s]+$/', 'message' => 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง'],
            [["username", "sername"], 'match', 'pattern' => '/^[\p{L}\p{M}\s\'’-]+$/u', 'message' => "ชื่อและนามสกุลต้องเป็นตัวอักษรและช่องว่าง (อนุญาต - และ ')"],
            // ตรวจสอบความซ้ำของ samaccountname แบบ Real-time ผ่าน AJAX validation
            ['samaccountname', 'validateSamaccountnameUnique'],
            // Confirm password
            ['confirm_password', 'compare', 'compareAttribute' => 'password', 'message' => 'รหัสผ่านไม่ตรงกัน'],
            ['email', 'validateEmailDomain'],
        ];
    }

    /**
     * ตรวจสอบความซ้ำของ samaccountname ใน LDAP
     */
    public function validateSamaccountnameUnique($attribute, $params)
    {
        $value = trim((string)$this->$attribute);
        if ($value === '') {
            return;
        }

        try {
            $ldap = new \common\components\LdapHelper();
            $conn = $ldap->getConnection();
            $config = \Yii::$app->params['ldap'] ?? [];

            // ระบุฐานการค้นหา
            if (!empty($config['search_all_ous'])) {
                $bases = [$config['base_dn'] ?? ''];
            } else {
                $bases = [];
                $allowed = $config['allowed_ous'] ?? [];
                if (!empty($allowed)) {
                    $bases = $allowed;
                } else {
                    $bases = array_filter([
                        $config['base_dn_user'] ?? ($config['base_dn'] ?? null),
                        $config['base_dn_reg'] ?? null,
                    ]);
                }
            }

            $exists = false;
            foreach ($bases as $baseDn) {
                if (empty($baseDn)) { continue; }
                $filter = '(sAMAccountName=' . $value . ')';
                $search = @ldap_search($conn, $baseDn, $filter, ['sAMAccountName']);
                if ($search) {
                    $entries = ldap_get_entries($conn, $search);
                    if ($entries && isset($entries['count']) && $entries['count'] > 0) {
                        $exists = true;
                        break;
                    }
                }
            }

            if ($exists) {
                $this->addError($attribute, 'User นี้มีแล้วในระบบ');
            }
        } catch (\Throwable $e) {
            \Yii::error('validateSamaccountnameUnique error: ' . $e->getMessage());
            // ไม่แจ้ง error ระบบให้ผู้ใช้ แต่ไม่บล็อก validation อื่น
        }
    }

    public function attributeLabels()
    {
        return [
            'samaccountname' => 'User (ใช้ในการเข้าระบบ)',
            'username' => 'ชื่อ (ภาษาไทย)',
            'sername' => 'นามสกุล (ภาษาไทย)',
            'email' => 'อีเมล',
            'department' => 'แผนก',
            'telephone' => 'เบอร์โทรศัพท์',
            'password' => 'รหัสผ่าน',
            'confirm_password' => 'ยืนยันรหัสผ่าน',
            'agree_terms' => 'ยอมรับข้อกำหนดและเงื่อนไข',
            'target_ou' => 'เลือก OU',
            'personalTitle' => 'คำนำหน้าชื่อ',
            'name_en' => 'ชื่อ-นามสกุล (ภาษาอังกฤษ)',
            'title' => 'ตำแหน่ง',
            'id_card' => 'เลขบัตรประชาชน',
            'company' => 'ชื่อบริษัทที่ติดต่อ(กรณีไม่ใช้จนท.รพ.)',
            'ephis_code' => 'เลขรหัสจาก Ephis',
            'streetaddress' => 'รายละเอียดเพิ่มเติม',
        ];
    }

    public function validateEmailDomain($attribute, $params)
    {
        if (empty($this->$attribute)) {
            return;
        }
        $allowed = Yii::$app->params['allowed_email_domains'] ?? null;
        if (is_array($allowed) && !empty($allowed)) {
            $domain = substr(strrchr($this->$attribute, '@'), 1);
            if ($domain === false || !in_array(strtolower($domain), array_map('strtolower', $allowed), true)) {
                $this->addError($attribute, 'โดเมนอีเมลไม่อนุญาต');
            }
        }
    }

    public function validateTargetOu($attribute, $params)
    {
        if (empty($this->$attribute)) {
            $this->addError($attribute, 'กรุณาเลือก OU');
            return;
        }
        try {
            $ldap = new \common\components\LdapHelper();
            $ous = $ldap->getOrganizationalUnits(Yii::$app->params['ldap']['base_dn_user']);
            $validDns = array_map(function($o){ return $o['dn'] ?? ''; }, $ous);
            if (!in_array($this->$attribute, $validDns, true)) {
                $this->addError($attribute, 'OU ที่เลือกไม่ถูกต้อง');
            }
        } catch (\Throwable $e) {
            $this->addError($attribute, 'ไม่สามารถตรวจสอบ OU ได้');
        }
    }

    public function validateThaiIdCard($attribute, $params)
    {
        if (empty($this->$attribute)) {
            return; // Skip validation if empty (field is optional)
        }
        
        $idCard = $this->$attribute;
        
        // Check if it's exactly 13 digits
        if (!preg_match('/^[0-9]{13}$/', $idCard)) {
            $this->addError($attribute, 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก');
            return;
        }
        
        // Thai ID card validation algorithm
        $sum = 0;
        $weights = [13, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2];
        
        for ($i = 0; $i < 12; $i++) {
            $sum += intval($idCard[$i]) * $weights[$i];
        }
        
        $remainder = $sum % 11;
        $checkDigit = (11 - $remainder) % 10;
        
        if (intval($idCard[12]) !== $checkDigit) {
            $this->addError($attribute, 'เลขบัตรประชาชนไม่ถูกต้องตามรูปแบบไทย');
        }
    }

    public function createUser()
    {
        if (!$this->validate()) {
            return false;
        }

		// ใช้การเชื่อมต่อ LDAP จาก LdapHelper และค่าคอนฟิกจาก params
		try {
			$ldapHelper = new \common\components\LdapHelper();
			$ldap_conn = $ldapHelper->getConnection();
			$config = \Yii::$app->params['ldap'] ?? [];
			$ldap_base_dn = $config['base_dn'] ?? '';
			$ldap_domain = $config['domain'] ?? '';
		} catch (\Throwable $e) {
			$this->addError('samaccountname', 'ไม่สามารถเชื่อมต่อกับ LDAP ได้');
			return false;
		}

		if ($ldap_conn) {
            // Test LDAP connection before proceeding
            $testSearch = ldap_read($ldap_conn, $ldap_base_dn, "(objectClass=*)", ['dn']);
            if (!$testSearch) {
                $error = ldap_error($ldap_conn);
                $this->addError('samaccountname', 'ไม่สามารถเข้าถึง LDAP server ได้: ' . $error);
                return false;
            }
            // Use display name as CN per requirement
            $displayName = trim($this->username . ' ' . $this->sername);
            $cn = $displayName;
			$userPrincipalName = $this->samaccountname . (!empty($ldap_domain) ? ('@' . $ldap_domain) : '');

            // Build DN from selected OU or fallback to Register OU
			$defaultRegOu = \Yii::$app->params['ldap']['base_dn_reg'] ?? '';
			$baseOuDn = $this->target_ou ?: $defaultRegOu;
			$ouSearchBase = (stripos($baseOuDn, 'DC=') === false && !empty($ldap_base_dn)) ? ($baseOuDn . ',' . $ldap_base_dn) : $baseOuDn;
            $dn = 'cn=' . $cn . ',' . $ouSearchBase;
            
            // Verify the target OU exists
            $ouTest = ldap_read($ldap_conn, $ouSearchBase, "(objectClass=organizationalUnit)", ['ou']);
            if (!$ouTest) {
                $error = ldap_error($ldap_conn);
                $this->addError('target_ou', 'OU ที่เลือกไม่ถูกต้องหรือไม่มีอยู่: ' . $error);
                return false;
            }

            // ตรวจสอบ sAMAccountName ซ้ำ
            $filter = "(sAMAccountName={$this->samaccountname})";
			$result = ldap_search($ldap_conn, $ldap_base_dn, $filter);
            $entries = ldap_get_entries($ldap_conn, $result);

            if ($entries['count'] > 0) {
                $this->addError('samaccountname', 'User นี้มีแล้วในระบบ');
                
                return false;
            }
            // ตรวจสอบ CN ซ้ำภายใต้ OU เป้าหมาย; หากซ้ำให้ต่อท้าย (2), (3), ...
            $cnBase = $cn;
            $i = 2;
            while (true) {
                $cnFilter = '(cn=' . ldap_escape($cn, '', LDAP_ESCAPE_FILTER) . ')';
                $cnSearch = @ldap_search($ldap_conn, $ouSearchBase, $cnFilter, ['cn']);
                if ($cnSearch) {
                    $cnEntries = ldap_get_entries($ldap_conn, $cnSearch);
                    if ($cnEntries && isset($cnEntries['count']) && $cnEntries['count'] > 0) {
                        $cn = $cnBase . ' (' . $i . ')';
                        $dn = 'cn=' . $cn . ',' . $ouSearchBase;
                        $i++;
                        continue;
                    }
                }
                break;
            }

            $entry = [
                "cn" => $cn,
                "sn" => $this->sername,
                "objectClass" => ["top", "person", "organizationalPerson", "user"],
                "department" => $this->department,
                "sAMAccountName" => $this->samaccountname,
                "userPrincipalName" => $userPrincipalName,
                "displayName" => $displayName,
                "givenName" => $this->username,
                // NORMAL_ACCOUNT (512) + PASSWD_NOTREQD (32) + DONT_EXPIRE_PASSWORD (65536) = 66080
                // Use PASSWD_NOTREQD initially, will be updated after password is set
                "userAccountControl" => 66080,
            ];

            // Add mail only when provided (AD rejects empty attribute values)
            if (!empty($this->email)) {
                $entry["mail"] = $this->email;
            }
            
            // Add new Active Directory fields
            if (!empty($this->personalTitle)) {
                $entry["personalTitle"] = $this->personalTitle;
            }
            if (!empty($this->name_en)) {
                $entry["description"] = $this->name_en; // Map name_en to description field
            }
            if (!empty($this->title)) {
                $entry["title"] = $this->title;
            }
            if (!empty($this->id_card)) {
                $entry["postalcode"] = $this->id_card;
            }
            if (!empty($this->company)) {
                $entry["company"] = $this->company;
            }
            if (!empty($this->streetaddress)) {
                $entry["streetAddress"] = $this->streetaddress;
            }
            if (!empty($this->ephis_code)) {
                $entry["physicalDeliveryOfficeName"] = $this->ephis_code;
            }

            // เพิ่มเบอร์โทรศัพท์เฉพาะเมื่อมีค่าเท่านั้น เพื่อเลี่ยง Invalid syntax จากค่าว่าง
            if (!empty($this->telephone)) {
                $entry["telephoneNumber"] = $this->telephone;
            }

            // Create user without password first
            $createResult = ldap_add($ldap_conn, $dn, $entry);
            if (!$createResult) {
                $error = ldap_error($ldap_conn);
                $errno = ldap_errno($ldap_conn);
                Yii::error("Failed to create user: $error (Error code: $errno)");
                Yii::error("User DN: $dn");
                Yii::error("Entry data: " . print_r($entry, true));
                
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
            
            Yii::debug("User created successfully, now setting password");
            
            // Set password after user creation
            if ($this->setUserPassword($ldap_conn, $dn, $this->password)) {
                Yii::debug("User created and password set successfully");
                return true;
            } else {
                // User was created but password setting failed
                Yii::warning("User created but password setting failed for: $dn");
                $this->addError('password', 'ผู้ใช้ถูกสร้างแล้วแต่การตั้งรหัสผ่านล้มเหลว');
                return false;
            }
        }

        $this->addError('samaccountname', 'ไม่สามารถเชื่อมต่อกับ LDAP ได้');
        return false;
    }

    /**
     * Sets password for a user in Active Directory
     * @param mixed $ldap_conn LDAP connection resource
     * @param string $userDn The DN of the user
     * @param string $password The password to set
     * @return bool Whether the operation was successful
     */
    private function setUserPassword($ldap_conn, $userDn, $password)
    {
        try {
            if (!$ldap_conn) {
                Yii::error("LDAP connection not established");
                return false;
            }

            // Convert password to UTF-16LE format required by Active Directory
            $unicodePassword = mb_convert_encoding('"' . $password . '"', 'UTF-16LE');
            $modifyData = ['unicodePwd' => [$unicodePassword]];
            
            Yii::debug("Setting password for user: $userDn");
            
            $result = ldap_modify($ldap_conn, $userDn, $modifyData);
            if (!$result) {
                $error = ldap_error($ldap_conn);
                $errno = ldap_errno($ldap_conn);
                Yii::error("Failed to set password: $error (Error code: $errno)");
                
                // Try alternative method if first attempt fails
                Yii::debug("Trying alternative password setting method");
                $result = ldap_mod_replace($ldap_conn, $userDn, $modifyData);
                if (!$result) {
                    $error = ldap_error($ldap_conn);
                    $errno = ldap_errno($ldap_conn);
                    Yii::error("Alternative password setting also failed: $error (Error code: $errno)");
                    return false;
                }
            }
            
            // Update userAccountControl to remove PASSWD_NOTREQD flag
            $uacUpdate = [
                'userAccountControl' => [66048] // NORMAL_ACCOUNT (512) + DONT_EXPIRE_PASSWORD (65536)
            ];
            $uacResult = ldap_modify($ldap_conn, $userDn, $uacUpdate);
            if (!$uacResult) {
                Yii::warning("Failed to update userAccountControl after password set: " . ldap_error($ldap_conn));
            } else {
                Yii::debug("Successfully updated userAccountControl after password set");
            }
            
            Yii::debug("Password set successfully for user: $userDn");
            return true;
            
        } catch (\Exception $e) {
            Yii::error("Exception while setting password: " . $e->getMessage());
            return false;
        }
    }
} 