<?php

namespace common\models;

use Yii;
use yii\base\Model;
use common\components\LdapHelper;

class ProfileForm extends Model
{
    public $displayName;
    public $department;
    public $email;
    public $telephoneNumber;
    public $streetaddress;
    public $title;
    public $givenname;
    public $currentPassword;
    public $newPassword;
    public $confirmPassword;

    private $_user;

    public function __construct($user, $config = [])
    {
        $this->_user = $user;
        $this->displayName = $user->displayName;
        $this->department = $user->department;
        $this->email = $user->email;
        $this->telephoneNumber = $user->telephoneNumber ?? '';
        
        // ดึงข้อมูลเพิ่มเติมจาก LDAP
        try {
            $ldap = new LdapHelper();
            // ลองค้นหาด้วย sAMAccountName ก่อน (เพราะ username มักเป็น sAMAccountName)
            $ldapUser = null;
            $baseDn = Yii::$app->params['ldap']['base_dn'] ?? '';
            
            if (!empty($baseDn)) {
                // ลองค้นหาด้วย sAMAccountName ก่อน (เพราะ username มักเป็น sAMAccountName)
                // Escape LDAP special characters
                $escapedUsername = ldap_escape($user->username, '', LDAP_ESCAPE_FILTER);
                $filter = "(sAMAccountName=" . $escapedUsername . ")";
                $attributes = [
                    'cn', 'samaccountname', 'displayname', 'department',
                    'mail', 'useraccountcontrol', 'ou', 'distinguishedname',
                    'whencreated', 'company', 'telephonenumber', 'title',
                    'sn', 'givenname', 'initials', 'description', 'streetaddress',
                ];
                
                try {
                    $ldapConn = $ldap->getConnection();
                    if ($ldapConn) {
                        $search = @ldap_search($ldapConn, $baseDn, $filter, $attributes);
                        if ($search) {
                            $entries = ldap_get_entries($ldapConn, $search);
                            if ($entries['count'] > 0) {
                                $ldapUser = $entries[0];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Yii::warning("Failed to search by sAMAccountName: " . $e->getMessage());
                }
                
                // ถ้าไม่พบ ลองค้นหาด้วย cn
                if (!$ldapUser) {
                    $ldapUser = $ldap->getUser($user->username);
                }
            } else {
                // Fallback ใช้ getUser() แบบเดิม
                $ldapUser = $ldap->getUser($user->username);
            }
            
            if ($ldapUser) {
                $getLdapValue = function($key, $default = '') use ($ldapUser) {
                    if (!isset($ldapUser[$key])) {
                        return $default;
                    }
                    if (is_array($ldapUser[$key])) {
                        return $ldapUser[$key][0] ?? $default;
                    }
                    return $ldapUser[$key] ?? $default;
                };
                
                $this->streetaddress = $getLdapValue('streetaddress', '');
                $this->title = $getLdapValue('title', '');
                $this->givenname = $getLdapValue('givenname', '');
            } else {
                // ถ้าไม่พบข้อมูล ให้ใช้ค่า default
                $this->streetaddress = '';
                $this->title = '';
                $this->givenname = '';
            }
        } catch (\Exception $e) {
            Yii::warning("Failed to load additional profile fields from LDAP: " . $e->getMessage());
            $this->streetaddress = '';
            $this->title = '';
            $this->givenname = '';
        }
        
        parent::__construct($config);
    }

    public function rules()
    {
        return [
            [['displayName', 'department', 'email'], 'required', 'when' => function($model) {
                // Only require profile fields when NOT changing password
                return empty($model->newPassword);
            }],
            ['email', 'email', 'skipOnEmpty' => true],
            ['telephoneNumber', 'string', 'max' => 50],
            [['streetaddress', 'title', 'givenname'], 'string'],
            [['currentPassword', 'newPassword', 'confirmPassword'], 'string', 'min' => 4],
            ['currentPassword', 'required', 'when' => function($model) {
                // Require current password when changing password
                return !empty($model->newPassword);
            }, 'message' => 'รหัสผ่านปัจจุบันจำเป็นต้องกรอกเมื่อเปลี่ยนรหัสผ่าน'],
            ['newPassword', 'required', 'when' => function($model) {
                // Require new password when current password is provided
                return !empty($model->currentPassword);
            }],
            ['confirmPassword', 'compare', 'compareAttribute' => 'newPassword', 'message' => 'Passwords do not match.'],
            ['currentPassword', 'validateCurrentPassword'],
        ];
    }

    public function validateCurrentPassword($attribute, $params)
    {
        if (!empty($this->currentPassword)) {
            $ldap = new LdapHelper();
            if (!$ldap->authenticate($this->_user->username, $this->currentPassword)) {
                $this->addError($attribute, 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
            
       
   
            }
            
        }
    }

    public function updateProfile()
    {
        // If changing password, populate profile fields from user identity to avoid validation errors
        if (!empty($this->newPassword)) {
            if (empty($this->email)) {
                $this->email = $this->_user->email ?? '';
            }
            if (empty($this->displayName)) {
                $this->displayName = $this->_user->displayName ?? '';
            }
            if (empty($this->department)) {
                $this->department = $this->_user->department ?? '';
            }
        }
        
        if (!$this->validate()) {
            Yii::error("Profile validation failed: " . print_r($this->errors, true));
            return false;
        }

        $ldap = new LdapHelper();

        // If password is being changed, only update password
        if (!empty($this->newPassword)) {
            if (empty($this->currentPassword)) {
                $this->addError('currentPassword', 'Current password is required to change password.');
                return false;
            }
            // Verify current password before allowing change
            if (!$ldap->authenticate($this->_user->username, $this->currentPassword)) {
                $this->addError('currentPassword', 'Current password is incorrect.');
                return false;
            }
            // Only send password update
            $userData = ['newPassword' => $this->newPassword];
        } else {
            // Update profile fields (not password)
            $userData = [
                'displayname' => $this->displayName,
                'department' => $this->department,
                'telephonenumber' => $this->telephoneNumber,
            ];
            // Only include email if it's provided
            if (!empty($this->email)) {
                $userData['mail'] = $this->email;
            }
            // เพิ่มฟิลด์ใหม่
            if (!empty($this->streetaddress)) {
                $userData['streetaddress'] = $this->streetaddress;
            }
            if (!empty($this->title)) {
                $userData['title'] = $this->title;
            }
            if (!empty($this->givenname)) {
                $userData['givenname'] = $this->givenname;
            }
        }

        Yii::debug("Attempting to update profile for user: " . $this->_user->username);
        Yii::debug("Update data: " . print_r($userData, true));

        try {
            if ($ldap->updateUser($this->_user->username, $userData)) {
                // Only update session and user object if updating profile (not just password)
                if (empty($this->newPassword)) {
                // Update session data
                $sessionData = Yii::$app->session->get('ldapUserData');
                $sessionData['displayname'] = $this->displayName;
                $sessionData['department'] = $this->department;
                    if (!empty($this->email)) {
                $sessionData['mail'] = $this->email;
                    }
                $sessionData['telephonenumber'] = [$this->telephoneNumber];
                if (!empty($this->streetaddress)) {
                    $sessionData['streetaddress'] = $this->streetaddress;
                }
                if (!empty($this->title)) {
                    $sessionData['title'] = $this->title;
                }
                if (!empty($this->givenname)) {
                    $sessionData['givenname'] = $this->givenname;
                }
                Yii::$app->session->set('ldapUserData', $sessionData);

                // Update user object
                $this->_user->displayName = $this->displayName;
                $this->_user->department = $this->department;
                    if (!empty($this->email)) {
                $this->_user->email = $this->email;
                    }
                $this->_user->telephoneNumber = $this->telephoneNumber;
                }

                Yii::debug("Update successfully");
                return true;
            } else {
                $this->addError('', 'Failed to update profile in LDAP. Please try again.');
            }
        } catch (\Exception $e) {
            Yii::error("Profile update error: " . $e->getMessage());
            Yii::error("Stack trace: " . $e->getTraceAsString());
            $this->addError('', 'Failed to update profile. Please try again.');
        }

        return false;
    }
} 