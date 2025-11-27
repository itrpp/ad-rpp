<?php

namespace common\models;

use Yii;
use yii\base\Model;
use common\components\LdapHelper;

class LdapUser extends Model
{
    public $cn;
    public $sn;
    public $mail;
    public $password;
    public $sAMAccountName;
    public $displayName;
    public $department;
    public $title;
    public $userPassword;
    public $organizationalUnit;
    public $availableOUs = [];
    public $passwordNeverExpires = false;
    public $accountEnabled = true;
    public $newPassword;
    public $confirmPassword;
    public $telephoneNumber;
    
    // Additional LDAP attributes
    public $mobile;
    public $givenName;
    public $initials;
    public $streetAddress;
    public $streetaddress; // alias รองรับการอ้างอิงแบบ lowercase ในบาง view
    public $city;
    public $state;
    public $postalCode;
    public $country;
    public $company;
    public $description;
    public $userPrincipalName;
    public $accountExpires;
    public $pwdLastSet;
    public $lastLogon;
    public $lastLogoff;
    public $logonCount;
    public $primaryGroupId;
    public $samAccountType;
    public $usnCreated;
    public $usnChanged;
    public $whenChanged;
    public $whenCreated;
    public $objectClass;
    public $objectGuid;
    public $objectSid;
    public $instanceType;
    public $codePage;
    public $msdsSupportedEncryptionTypes;
    public $name;
    public $co;
    public $physicalDeliveryOfficeName;
    public $wwwHomepage;
    public $jobTitle;
    public $personalTitle; // คำนำหน้าชื่อ (personalTitle)

    private $_oldAttributes = [];

    public function scenarios()
    {
        return [
            'default' => ['cn', 'sn', 'mail', 'sAMAccountName', 'displayName', 'department', 'title', 'password', 'organizationalUnit', 'passwordNeverExpires', 'accountEnabled', 'userPassword', 'newPassword', 'confirmPassword', 'telephoneNumber', 'mobile', 'givenName', 'initials', 'streetAddress', 'city', 'state', 'postalCode', 'country', 'company', 'description', 'userPrincipalName', 'accountExpires', 'pwdLastSet', 'lastLogon', 'lastLogoff', 'logonCount', 'primaryGroupId', 'samAccountType', 'usnCreated', 'usnChanged', 'whenChanged', 'objectClass', 'objectGuid', 'objectSid', 'instanceType', 'codePage', 'msdsSupportedEncryptionTypes', 'name', 'co', 'physicalDeliveryOfficeName', 'wwwHomepage', 'jobTitle'],
            'update' => ['cn', 'sAMAccountName', 'displayName', 'department', 'title', 'mail', 'newPassword', 'confirmPassword', 'telephoneNumber', 'mobile', 'givenName', 'initials', 'streetAddress', 'city', 'state', 'postalCode', 'country', 'company', 'description', 'userPrincipalName', 'accountExpires', 'pwdLastSet', 'lastLogon', 'lastLogoff', 'logonCount', 'primaryGroupId', 'samAccountType', 'usnCreated', 'usnChanged', 'whenChanged', 'objectClass', 'objectGuid', 'objectSid', 'instanceType', 'codePage', 'msdsSupportedEncryptionTypes', 'name', 'co', 'physicalDeliveryOfficeName', 'wwwHomepage', 'jobTitle', 'personalTitle'],
            'create' => ['cn', 'sn', 'mail', 'sAMAccountName', 'displayName', 'department', 'title', 'password', 'organizationalUnit', 'passwordNeverExpires', 'accountEnabled', 'telephoneNumber', 'mobile', 'givenName', 'initials', 'streetAddress', 'city', 'state', 'postalCode', 'country', 'company', 'description', 'userPrincipalName', 'accountExpires', 'pwdLastSet', 'lastLogon', 'lastLogoff', 'logonCount', 'primaryGroupId', 'samAccountType', 'usnCreated', 'usnChanged', 'whenChanged', 'objectClass', 'objectGuid', 'objectSid', 'instanceType', 'codePage', 'msdsSupportedEncryptionTypes', 'name', 'co', 'physicalDeliveryOfficeName', 'wwwHomepage', 'jobTitle', 'personalTitle'],
        ];
    }

    public function rules()
    {
        return [
            [['cn', 'mail', 'password'], 'required', 'on' => 'create'],
            [['sAMAccountName', 'displayName', 'department', 'title'], 'required', 'on' => 'update'],
            [['sAMAccountName', 'displayName', 'department', 'title', 'mail', 'telephoneNumber', 'personalTitle'], 'string'],
            ['mail', 'email', 'skipOnEmpty' => true],
            ['password', 'string', 'min' => 6, 'skipOnEmpty' => true],
            ['organizationalUnit', 'string'],
            ['organizationalUnit', 'default', 'value' => 'OU=rpp-register,OU=Register,OU=rpp-user,DC=rpphosp,DC=local'],
            ['passwordNeverExpires', 'boolean'],
            ['accountEnabled', 'boolean'],
            [['userPassword'], 'string'],
            ['newPassword', 'string', 'min' => 6, 'skipOnEmpty' => true],
            ['confirmPassword', 'compare', 'compareAttribute' => 'newPassword', 'skipOnEmpty' => true],
            ['sAMAccountName', 'match', 'pattern' => '/^[a-zA-Z0-9_]+$/', 'message' => 'Username ต้องประกอบด้วยตัวอักษร ตัวเลข และ underscore เท่านั้น'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'cn' => 'Common Name',
            'sn' => 'Surname',
            'mail' => 'Email',
            'sAMAccountName' => 'SAM Account Name',
            'displayName' => 'Display Name',
            'department' => 'Department',
            'title' => 'ตำแหน่ง',
            'userPassword' => 'Password',
            'organizationalUnit' => 'Organizational Unit',
            'passwordNeverExpires' => 'Password Never Expires',
            'accountEnabled' => 'Account Enabled',
            'newPassword' => 'New Password',
            'confirmPassword' => 'Confirm Password',
            'telephoneNumber' => 'Telephone Number',
            'personalTitle' => 'คำนำหน้าชื่อ',
        ];
    }

    public function loadFromLdap()
    {
        $ldap = new LdapHelper();
        $user = $ldap->getUser($this->cn);
        
        if ($user) {
            // Basic attributes
            $this->sn = $user['sn'][0] ?? '';
            $this->mail = $user['mail'][0] ?? '';
            $this->sAMAccountName = $user['samaccountname'][0] ?? '';
            $this->displayName = $user['displayname'][0] ?? '';
            $this->department = $user['department'][0] ?? '';
            $this->title = $user['title'][0] ?? '';
            $this->organizationalUnit = $user['ou'][0] ?? '';
            $this->telephoneNumber = $user['telephonenumber'][0] ?? '';
            
            // Additional attributes
            $this->mobile = $user['mobile'][0] ?? '';
            $this->givenName = $user['givenname'][0] ?? '';
            $this->initials = $user['initials'][0] ?? '';
            $this->streetAddress = $user['streetaddress'][0] ?? '';
            $this->streetaddress = $this->streetAddress;
            $this->city = $user['l'][0] ?? '';
            $this->state = $user['st'][0] ?? '';
            $this->postalCode = $user['postalcode'][0] ?? '';
            $this->country = $user['countrycode'][0] ?? '';
            $this->company = $user['company'][0] ?? '';
            $this->description = $user['description'][0] ?? '';
            $this->userPrincipalName = $user['userprincipalname'][0] ?? '';
            $this->accountExpires = $user['accountexpires'][0] ?? '';
            $this->pwdLastSet = $user['pwdlastset'][0] ?? '';
            $this->lastLogon = $user['lastlogon'][0] ?? '';
            $this->lastLogoff = $user['lastlogoff'][0] ?? '';
            $this->logonCount = $user['logoncount'][0] ?? '';
            $this->primaryGroupId = $user['primarygroupid'][0] ?? '';
            $this->samAccountType = $user['samaccounttype'][0] ?? '';
            $this->usnCreated = $user['usncreated'][0] ?? '';
            $this->usnChanged = $user['usnchanged'][0] ?? '';
            $this->whenChanged = $user['whenchanged'][0] ?? '';
            $this->whenCreated = $user['whencreated'][0] ?? '';
            $this->objectClass = $user['objectclass'] ?? [];
            $this->objectGuid = $user['objectguid'][0] ?? '';
            $this->objectSid = $user['objectsid'][0] ?? '';
            $this->instanceType = $user['instancetype'][0] ?? '';
            $this->codePage = $user['codepage'][0] ?? '';
            $this->msdsSupportedEncryptionTypes = $user['msds-supportedencryptiontypes'][0] ?? '';
            $this->name = $user['name'][0] ?? '';
            $this->co = $user['co'][0] ?? '';
            $this->physicalDeliveryOfficeName = $user['physicaldeliveryofficename'][0] ?? '';
            $this->wwwHomepage = $user['wwwhomepage'][0] ?? '';
            $this->jobTitle = $user['jobtitle'][0] ?? '';
            $this->personalTitle = $user['personaltitle'][0] ?? '';
            
            // Store old attributes after loading
            $this->_oldAttributes = [
                'cn' => $this->cn,
                'sAMAccountName' => $this->sAMAccountName,
                'displayName' => $this->displayName,
                'department' => $this->department,
                'title' => $this->title,
                'mail' => $this->mail,
                'telephoneNumber' => $this->telephoneNumber,
                'mobile' => $this->mobile,
                'givenName' => $this->givenName,
                'initials' => $this->initials,
                'streetAddress' => $this->streetAddress,
                'city' => $this->city,
                'state' => $this->state,
                'postalCode' => $this->postalCode,
                'country' => $this->country,
                'company' => $this->company,
                'description' => $this->description,
                'physicalDeliveryOfficeName' => $this->physicalDeliveryOfficeName,
                'whenChanged' => $this->whenChanged,
                'whenCreated' => $this->whenCreated,
                'personalTitle' => $this->personalTitle,
            ];
            
            return true;
        }
        return false;
    }

    public function getOldAttribute($name)
    {
        return $this->_oldAttributes[$name] ?? null;
    }

    public function updateUser($data)
    {
        $ldap = new LdapHelper();
        $updateData = [];
        
        // Map form fields to LDAP attributes
        $attributeMap = [
            'sAMAccountName' => 'sAMAccountName',
            'displayName' => 'displayName',
            'department' => 'department',
            'title' => 'title',
            'mail' => 'mail',
            'telephoneNumber' => 'telephoneNumber',
            'physicalDeliveryOfficeName' => 'physicalDeliveryOfficeName'
        ];

        // Only update fields that have changed
        foreach ($attributeMap as $formField => $ldapField) {
            if (isset($data[$formField])) {
                $oldValue = $this->getOldAttribute($formField);
                $newValue = trim($data[$formField]); // Trim whitespace
                
                Yii::debug("Comparing field $formField: old='$oldValue', new='$newValue'");
                
                // Special handling for physicalDeliveryOfficeName and mail - always update if present in form
                if ($formField === 'physicalDeliveryOfficeName' || $formField === 'mail') {
                    // If empty, set to "ยังไม่ระบุ"
                    $finalValue = empty($newValue) ? 'ยังไม่ระบุ' : $newValue;
                    $updateData[$ldapField] = $finalValue;
                    Yii::debug("$formField will be updated to: '$finalValue'");
                } elseif ($newValue !== $oldValue) {
                    // For other fields, only update if the value has actually changed
                    
                    // Validate username format
                    if ($formField === 'sAMAccountName' && !empty($newValue)) {
                        if (!preg_match('/^[a-zA-Z0-9_]+$/', $newValue)) {
                            Yii::error("Invalid username format: $newValue");
                            $this->addError('sAMAccountName', 'Username ต้องประกอบด้วยตัวอักษร ตัวเลข และ underscore เท่านั้น');
                            return false;
                        }
                    }
                    
                    $updateData[$ldapField] = $newValue;
                    Yii::debug("Field $formField will be updated from '$oldValue' to '$newValue'");
                }
            }
        }

        // Handle password update if provided
        if (!empty($data['newPassword'])) {
            if (empty($data['confirmPassword'])) {
                $this->addError('confirmPassword', 'กรุณายืนยันรหัสผ่านใหม่');
                return false;
            }
            if ($data['newPassword'] !== $data['confirmPassword']) {
                $this->addError('confirmPassword', 'รหัสผ่านไม่ตรงกัน');
                return false;
            }
            $updateData['newPassword'] = $data['newPassword'];
            Yii::debug("Password update included in update data");
        }

        // Only proceed with update if there are changes
        if (!empty($updateData)) {
            Yii::debug("Updating LDAP user {$this->cn} with data: " . print_r($updateData, true));
            try {
                // Try updating with both cn and sAMAccountName
                $result = $ldap->updateUser($this->cn, $updateData);
                if (!$result && $this->sAMAccountName) {
                    $result = $ldap->updateUser($this->sAMAccountName, $updateData);
                }
                
                if ($result) {
                    // Reload the model with fresh data
                    if ($this->loadFromLdap()) {
                        Yii::debug("User data reloaded successfully after update");
                        return true;
                    } else {
                        Yii::error("Failed to reload user data after update");
                        return false;
                    }
                } else {
                    Yii::error("LDAP update failed for user {$this->cn}");
                    Yii::error("Update data: " . print_r($updateData, true));
                    return false;
                }
            } catch (\Exception $e) {
                Yii::error("Exception while updating LDAP user {$this->cn}: " . $e->getMessage());
                Yii::error("Stack trace: " . $e->getTraceAsString());
                Yii::error("Update data: " . print_r($updateData, true));
                return false;
            }
        } else {
            Yii::debug("No changes detected for user {$this->cn}");
            return true; // No changes to update
        }
    }

    public function deleteUser()
    {
        $ldap = new LdapHelper();
        Yii::debug("Deleting LDAP user: {$this->cn}");
        return $ldap->deleteUser($this->cn);
    }


    
    /**
     * Moves a user to a different organizational unit
     * @param string $cn The common name of the user to move
     * @param string $newOU The new organizational unit to move the user to
     * @return bool Whether the move was successful
     */
    public function moveUser($cn, $newOU)
    {
        Yii::debug("Moving LDAP user $cn to OU: $newOU");
        
        $ldap = new LdapHelper();
        $result = $ldap->moveUser($cn, $newOU);
        
        if ($result) {
            Yii::$app->session->setFlash('success', "User $cn has been moved to $newOU successfully.");
        } else {
            Yii::$app->session->setFlash('error', "Failed to move user $cn to $newOU.");
        }
        
        return $result;
    }
    
    /**
     * Sets password expiration options for a user
     * @param string $cn The common name of the user
     * @param bool $neverExpires Whether the password should never expire
     * @return bool Whether the operation was successful
     */
    public function setPasswordExpiration($cn, $neverExpires = true)
    {
        Yii::debug("Setting password expiration for LDAP user $cn: " . ($neverExpires ? "never expires" : "expires"));
        
        $ldap = new LdapHelper();
        $result = $ldap->setPasswordExpiration($cn, $neverExpires);
        
        if ($result) {
            Yii::$app->session->setFlash('success', "Password expiration settings updated for user $cn.");
        }
        
        return $result;
    }
    
    /**
     * Enables or disables a user account
     * @param string $cn The common name of the user
     * @param bool $enable Whether to enable or disable the account
     * @return bool Whether the operation was successful
     */
    public function setAccountStatus($cn, $enable = true)
    {
        Yii::debug("Setting account status for LDAP user $cn: " . ($enable ? "enabled" : "disabled"));
        
        $ldap = new LdapHelper();
        $result = $ldap->setAccountStatus($cn, $enable);
        
        if ($result) {
            Yii::$app->session->setFlash('success', "Account status updated for user $cn: " . ($enable ? "enabled" : "disabled"));
        }
        
        return $result;
    }



    private function escapeLdapValue($value)
    {
        $specialChars = ['\\', '*', '(', ')', '\0'];
        $escaped = $value;
        foreach ($specialChars as $char) {
            $escaped = str_replace($char, '\\' . $char, $escaped);
        }
        return $escaped;
    }
}

Yii::debug("LDAP Connection Details: " . print_r([
    'server' => Yii::$app->params['ldap']['server'],
    'admin_dn' => Yii::$app->params['ldap']['admin_dn'],
    'target_ou' => Yii::$app->params['ldap']['base_dn_reg']
], true));
