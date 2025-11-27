<?php

namespace common\models;

use Yii;
use yii\base\BaseObject;
use yii\web\IdentityInterface;
use common\components\PermissionManager;

/**
 * User model for LDAP authentication
 */
class User extends BaseObject implements IdentityInterface
{
    const STATUS_ACTIVE = 10;
    const STATUS_INACTIVE = 9;
    const ROLE_Admin = "Admin";
    const ROLE_Officer = "Officer";
    const ROLE_Executive = "Executive";
    
    public $id;
    public $username;
    public $displayName;
    public $department;
    public $email;
    public $telephoneNumber;
    public $ou;
    public $distinguishedName;
    public $memberof;
    public $userAccountControl;
    public $cn;
    public $givenName;

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id)
    {
        $userData = Yii::$app->session->get('ldapUserData');
        if ($userData && isset($userData['samaccountname']) && $userData['samaccountname'] === $id) {
            // Check account status from AD in real-time (not just session cache)
            try {
                $ldap = new \common\components\LdapHelper();
                $ldapUser = $ldap->getUser($id);
                
                if ($ldapUser) {
                    // Get current userAccountControl from AD
                    $getLdapValue = function($key, $default = 0) use ($ldapUser) {
                        if (!isset($ldapUser[$key])) {
                            return $default;
                        }
                        if (is_array($ldapUser[$key])) {
                            return intval($ldapUser[$key][0] ?? $default);
                        }
                        return intval($ldapUser[$key] ?? $default);
                    };
                    
                    $currentUserAccountControl = $getLdapValue('useraccountcontrol', 0);
                    $sessionUserAccountControl = intval($userData['useraccountcontrol'] ?? 0);
                    $ACCOUNTDISABLE = 0x0002;
                    
                    $isCurrentlyDisabled = ($currentUserAccountControl & $ACCOUNTDISABLE) !== 0;
                    $wasDisabled = ($sessionUserAccountControl & $ACCOUNTDISABLE) !== 0;
                    
                    // If account is currently disabled, force logout
                    if ($isCurrentlyDisabled) {
                        Yii::warning("User $id account is disabled in AD - forcing logout");
                        Yii::$app->user->logout(false);
                        Yii::$app->session->destroy();
                        return null;
                    }
                    
                    // If account was disabled but is now enabled (real-time recovery)
                    if ($wasDisabled && !$isCurrentlyDisabled) {
                        Yii::info("User $id account was re-enabled in AD - refreshing session data");
                        
                        // Update session with fresh AD data
                        $sessionData = [
                            'cn' => $getLdapValue('cn', $getLdapValue('displayname', '')),
                            'samaccountname' => $getLdapValue('samaccountname', $id),
                            'displayname' => $getLdapValue('displayname', ''),
                            'department' => $getLdapValue('department', ''),
                            'mail' => $getLdapValue('mail', ''),
                            'telephonenumber' => $getLdapValue('telephonenumber', ''),
                            'ou' => '',
                            'distinguishedname' => $getLdapValue('distinguishedname', ''),
                            'memberof' => isset($ldapUser['memberof']) ? (is_array($ldapUser['memberof']) ? array_slice($ldapUser['memberof'], 1) : []) : [],
                            'useraccountcontrol' => $currentUserAccountControl,
                            'whenchanged' => $getLdapValue('whenchanged', ''),
                            'whencreated' => $getLdapValue('whencreated', ''),
                        ];
                        
                        // Extract OU from distinguishedName
                        if (!empty($sessionData['distinguishedname']) && preg_match('/OU=([^,]+)/i', $sessionData['distinguishedname'], $m)) {
                            $sessionData['ou'] = $m[1];
                        } elseif (isset($ldapUser['ou'])) {
                            $sessionData['ou'] = is_array($ldapUser['ou']) ? ($ldapUser['ou'][0] ?? '') : $ldapUser['ou'];
                        }
                        
                        Yii::$app->session->set('ldapUserData', $sessionData);
                        
                        // Return refreshed user identity
                        return static::createFromLdapData($sessionData);
                    }
                    
                    // Account is enabled - update session if needed and return user
                    if ($currentUserAccountControl !== $sessionUserAccountControl) {
                        // Update session with current userAccountControl
                        $userData['useraccountcontrol'] = $currentUserAccountControl;
                        Yii::$app->session->set('ldapUserData', $userData);
                    }
                }
            } catch (\Throwable $e) {
                // If AD check fails, fall back to session data check
                Yii::warning("Failed to check AD account status for user $id: " . $e->getMessage());
                
                // Check from session data as fallback
                $userAccountControl = intval($userData['useraccountcontrol'] ?? 0);
                $ACCOUNTDISABLE = 0x0002;
                if ($userAccountControl & $ACCOUNTDISABLE) {
                    Yii::warning("User $id account is disabled in session (AD check failed) - forcing logout");
                    Yii::$app->user->logout(false);
                    Yii::$app->session->destroy();
                    return null;
                }
            }
            
            return static::createFromLdapData($userData);
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return null;
    }

    /**
     * Creates a new User instance from LDAP data
     * @param array $userData The LDAP user data
     * @return User
     */
    public static function createFromLdapData($userData)
    {
        $user = new static();
        $user->id = $userData['samaccountname'];
        $user->username = $userData['samaccountname'];
        $user->displayName = $userData['displayname'];
        $user->department = $userData['department'];
        $user->email = $userData['mail'];
        $user->telephoneNumber = is_array($userData['telephonenumber']) ? $userData['telephonenumber'][0] : ($userData['telephonenumber'] ?? '');
        $user->ou = $userData['ou'];
        $user->distinguishedName = $userData['distinguishedname'];
        $user->memberof = $userData['memberof'] ?? [];
        $user->userAccountControl = $userData['useraccountcontrol'] ?? 0;
        $user->givenName = $userData['givenname'] ?? '';
        $user->cn = $userData['cn'] ?? '';
        return $user;
    }

    /** 
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey)
    {
        return false;
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int) substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= time();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Check if user is an administrator using PermissionManager
     * @return bool
     */
    public function isAdmin()
    {
        $permissionManager = new PermissionManager();
        return $permissionManager->isLdapAdmin();
    }
    
    /**
     * Get user's RBAC role based on LDAP data
     * @return string
     */
    public function getRole()
    {
        if ($this->isAdmin()) {
            return PermissionManager::ROLE_ADMIN;
        }
        return PermissionManager::ROLE_USER;
    }
    
    /**
     * Assign RBAC role to user based on LDAP data
     */
    public function assignRole()
    {
        $permissionManager = new PermissionManager();
        $ldapData = [
            'distinguishedname' => $this->distinguishedName,
            'memberof' => $this->memberof,
        ];
        $permissionManager->assignRoleToUser($this->id, $ldapData);
    }

    /**
     * Check if user account is enabled
     * @return bool
     */
    public function isEnabled()
    {
        $ACCOUNTDISABLE = 0x0002;
        return !($this->userAccountControl & $ACCOUNTDISABLE);
    }

    /**
     * Finds user by email
     *
     * @param string $email
     * @return static|null
     */
    public static function findByEmail($email)
    {
        $userData = Yii::$app->session->get('ldapUserData');
        if ($userData && isset($userData['mail']) && $userData['mail'] === $email) {
            return static::createFromLdapData($userData);
        }
        return null;
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        $userData = Yii::$app->session->get('ldapUserData');
        if ($userData && isset($userData['password_reset_token']) && $userData['password_reset_token'] === $token) {
            return static::createFromLdapData($userData);
        }
        return null;
    }

    /**
     * Finds user by verification email token
     *
     * @param string $token verify email token
     * @return static|null
     */
    public static function findByVerificationToken($token)
    {
        $userData = Yii::$app->session->get('ldapUserData');
        if ($userData && isset($userData['verification_token']) && $userData['verification_token'] === $token) {
            return static::createFromLdapData($userData);
        }
        return null;
    }

    /**
     * Refreshes the user's data from LDAP
     * @return bool Whether the refresh was successful
     */
    public function refresh()
    {
        $ldap = new \common\components\LdapHelper();
        $userData = $ldap->getUser($this->username);
        
        if ($userData) {
            $this->displayName = $userData['displayname'][0] ?? $this->displayName;
            $this->department = $userData['department'][0] ?? $this->department;
            $this->email = $userData['mail'][0] ?? $this->email;
            $this->telephoneNumber = $userData['telephonenumber'][0] ?? $this->telephoneNumber;
            $this->ou = $userData['ou'][0] ?? $this->ou;
            $this->distinguishedName = $userData['distinguishedname'][0] ?? $this->distinguishedName;
            $this->memberof = $userData['memberof'] ?? $this->memberof;
            $this->userAccountControl = $userData['useraccountcontrol'][0] ?? $this->userAccountControl;
            
            // Update session data
            $sessionData = Yii::$app->session->get('ldapUserData');
            $sessionData['displayname'] = $this->displayName;
            $sessionData['department'] = $this->department;
            $sessionData['mail'] = $this->email;
            $sessionData['telephonenumber'] = [$this->telephoneNumber];
            $sessionData['ou'] = $this->ou;
            $sessionData['distinguishedname'] = $this->distinguishedName;
            $sessionData['memberof'] = $this->memberof;
            $sessionData['useraccountcontrol'] = $this->userAccountControl;
            Yii::$app->session->set('ldapUserData', $sessionData);
            
            return true;
        }
        return false;
    }
}
