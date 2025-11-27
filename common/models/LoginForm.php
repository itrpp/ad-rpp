<?php

namespace common\models;

use Yii;
use yii\base\Model;
use common\components\LdapHelper;

/**
 * Login form
 */
class LoginForm extends Model
{
    public $username;
    public $password;
    public $rememberMe = true;

    private $_user = false;


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['username', 'password'], 'required'],
            // rememberMe must be a boolean value
            ['rememberMe', 'boolean'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $ldap = new LdapHelper();
            $userData = $ldap->authenticate($this->username, $this->password);
            
            if (!$userData) {
                $this->addError($attribute, 'username หรือ password ไม่ถูกต้อง'  .print_r($userData, true));
             
              
                return;
            }

            // Check if account is enabled
            $userAccountControl = isset($userData['useraccountcontrol'][0]) ? intval($userData['useraccountcontrol'][0]) : 0;
            $ACCOUNTDISABLE = 0x0002;
            if ($userAccountControl & $ACCOUNTDISABLE) {
                $this->addError($attribute, 'Your account has been disabled. Please contact your administrator.');
                return;
            }

            // Ensure we have the full distinguishedName
            if (!isset($userData['distinguishedname'])) {
                $this->addError($attribute, 'Unable to retrieve user information. Please try again.');
                return;
            }
            
            // Create User identity from LDAP data
            $this->_user = User::createFromLdapData($userData);
            
            // Store additional user data in session for later use
            // Ensure all LDAP attributes are properly stored in session
            // Helper function to safely extract LDAP values (handle both array and direct formats)
            $getLdapValue = function($key, $default = '') use ($userData) {
                if (!isset($userData[$key])) {
                    return $default;
                }
                if (is_array($userData[$key])) {
                    return $userData[$key][0] ?? $default;
                }
                return $userData[$key];
            };
            
            $sessionData = [
                'cn' => $getLdapValue('cn', $getLdapValue('displayname', '')),
                'samaccountname' => $getLdapValue('samaccountname', $this->username),
                'displayname' => $getLdapValue('displayname', ''),
                'department' => $getLdapValue('department', ''),
                'mail' => $getLdapValue('mail', ''),
                'telephonenumber' => $getLdapValue('telephonenumber', ''),
                'ou' => $getLdapValue('ou', ''),
                'distinguishedname' => $getLdapValue('distinguishedname', ''),
                'memberof' => isset($userData['memberof']) ? (is_array($userData['memberof']) ? array_slice($userData['memberof'], 1) : []) : [],
                'useraccountcontrol' => $userAccountControl,
                'whenchanged' => $getLdapValue('whenchanged', ''),
                'whencreated' => $getLdapValue('whencreated', ''),
            ];
            
            // Extract OU from distinguishedName if not set
            if (empty($sessionData['ou']) && !empty($sessionData['distinguishedname'])) {
                if (preg_match('/OU=([^,]+)/i', $sessionData['distinguishedname'], $matches)) {
                    $sessionData['ou'] = $matches[1];
                }
            }
            
            Yii::$app->session->set('ldapUserData', $sessionData);
            Yii::debug("Session data stored for user: {$this->username}", 'app');

            // Assign RBAC role based on LDAP groups/OU so that menus/permissions reflect immediately
            if ($this->_user instanceof User) {
                try {
                    $this->_user->assignRole();
                } catch (\Throwable $e) {
                    Yii::warning('RBAC role assignment failed: ' . $e->getMessage());
                }
            }

            // Log admin status
            if ($this->_user instanceof User && $this->_user->isAdmin()) {
                Yii::info("User {$this->username} logged in as administrator", 'app');
            }
            
            // Log successful login with AD data
            Yii::info("User {$this->username} logged in successfully. OU: {$sessionData['ou']}, DN: {$sessionData['distinguishedname']}", 'app');
        }
    }

    /**
     * Logs in a user using the provided username and password.
     *
     * @return bool whether the user is logged in successfully
     */
    public function login()
    {
        if ($this->validate()) {
            return Yii::$app->user->login($this->getUser(), $this->rememberMe ? 3600 * 24 * 30 : 0);
        }
        
        return false;
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser()
    {
        if ($this->_user === false) {
            $this->_user = null;
        }
        return $this->_user;
    }
}
