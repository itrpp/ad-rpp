<?php

namespace frontend\models;

use Yii;
use yii\base\Model;
use common\models\LdapUser;
use common\components\LdapHelper;

/**
 * Signup form
 */
class SignupForm extends Model
{


    public $displayName;
    public $department;
    public $cn;
    public $sn;
   
    public $sAMAccountName;
    public $organizationalUnit;
    public $telephoneNumber;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['cn', 'sn', 'sAMAccountName', 'displayName', 'department', 'telephoneNumber'], 'required'],
            [['cn', 'sn', 'sAMAccountName', 'displayName', 'department', 'telephoneNumber'], 'string', 'max' => 255],
            [['organizationalUnit'], 'string', 'max' => 255],
            [['organizationalUnit'], 'default', 'value' => 'OU=Register,OU=rpp-user,DC=rpphosp,DC=local'],
        ];
    }

    /**
     * Validates if username is unique in LDAP
     */
    public function validateUsernameUnique($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $ldap = new LdapHelper();
            $user = $ldap->getUser($this->username);
            
            if ($user) {
                $this->addError($attribute, 'This username has already been taken.');
            }
        }
    }

    /**
     * Validates if email is unique in LDAP
     */
    public function validateEmailUnique($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $ldap = new LdapHelper();
            $user = $ldap->getUserByEmail($this->email);
            
            if ($user) {
                $this->addError($attribute, 'This email address has already been taken.');
            }
        }
    }

    /**
     * Signs user up.
     *
     * @return bool whether the creating new account was successful
     */
    public function signup()
    {
        $ldap = new LdapHelper();   
        $data = $this->attributes;
        
        // ตรวจสอบข้อมูลที่จำเป็น
        if (!$this->validateUserAttributes($data)) {
            return false;
        }

        // จัดรูปแบบข้อมูลให้ถูกต้องสำหรับ LDAP
        $ldapData = [
            'cn' => [$data['cn']],
            'sn' => [$data['sn']],
            'sAMAccountName' => [$data['sAMAccountName']],
            'displayName' => [$data['displayName']],
            'department' => [$data['department']],
            'telephoneNumber' => [$data['telephoneNumber']],
            'organizationalUnit' => [$data['organizationalUnit']]
        ];

        // สร้าง DN สำหรับผู้ใช้ใหม่
        $userDn = "CN={$data['cn']},{$data['organizationalUnit']}";
        
        // สร้างรหัสผ่านเริ่มต้น (8 ตัวอักษรขึ้นไป)
        $password = 'Welcome@' . date('Y');
        
        $result = $ldap->createUser($userDn, $ldapData, $password);
        if ($result === true) {
            Yii::info("New user created successfully: {$data['cn']}");
            return true;
        } else {
            Yii::error("Failed to create LDAP user: " . print_r($result, true));
            $this->addError('username', 'Failed to create user: ' . $result);
            return false;
        }
    }

    public function validateUserAttributes($data)
    {
        $requiredAttributes = [
            'displayName' => 'ชื่อที่แสดง',
            'department' => 'แผนก',
            'cn' => 'ชื่อ',
            'sn' => 'นามสกุล',
            'sAMAccountName' => 'ชื่อผู้ใช้',
            'organizationalUnit' => 'หน่วยงาน',
            'telephoneNumber' => 'เบอร์โทรศัพท์'
        ];

        $missingAttributes = [];
        foreach ($requiredAttributes as $attribute => $label) {
            if (empty($data[$attribute])) {
                $missingAttributes[$attribute] = $label;
            }
        }

        if (!empty($missingAttributes)) {
            $this->addError('userAttributes', 'กรุณากรอกข้อมูลที่จำเป็น: ' . implode(', ', $missingAttributes));
            return false;
        }

        // ตรวจสอบความยาวของ sAMAccountName
        if (strlen($data['sAMAccountName']) < 3) {
            $this->addError('userAttributes', 'ชื่อผู้ใช้ต้องมีความยาวอย่างน้อย 3 ตัวอักษร');
            return false;
        }

        // ตรวจสอบรูปแบบเบอร์โทรศัพท์
        if (!preg_match('/^[0-9]{3,10}$/', $data['telephoneNumber'])) {
            $this->addError('userAttributes', 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 3-10 หลัก');
            return false;
        }

        return true;
    }
}
