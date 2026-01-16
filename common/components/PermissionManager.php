<?php

namespace common\components;

use Yii;
use yii\web\User;
use yii\rbac\ManagerInterface;

/**
 * Centralized Permission Management Helper
 * 
 * This class provides a centralized way to manage permissions across the application
 * using both LDAP groups and Yii2 RBAC system.
 */
class PermissionManager
{
    /**
     * @var ManagerInterface
     */
    private $authManager;
    
    /**
     * @var User
     */
    private $user;
    
    /**
     * LDAP Admin Groups Configuration
     */
    const ADMIN_GROUPS = [
        'CN=manage Ad_admin,CN=Users-RPP,DC=rpphosp,DC=local',
        'CN=manage Ad_it,CN=Users-RPP,DC=rpphosp,DC=local',
        'CN=manage Ad_admin,CN=Users-RPP,DC=rpphosp,DC=local', // For delete group permission
        'CN=Administrators',
        'CN=Domain Admins',
    ];
    // CN fallback names (match by CN only)
    const ADMIN_GROUP_CNS = [
        'manage Ad_it',
        'manage Ad_admin',
        'Administrators',
        'Domain Admins',
    ];
    
    /**
     * LDAP Group Delete Permission Groups Configuration
     * Only users in this specific group can delete groups
     */
    const GROUP_DELETE_GROUPS = [
        'CN=manage Ad_admin,CN=Users-RPP,DC=rpphosp,DC=local',
    ];
    // CN fallback names (match by CN only)
    const GROUP_DELETE_GROUP_CNS = [
        'manage Ad_admin',
    ];
    
    /**
     * LDAP Superuser Groups Configuration
     * Only users who are members of group with CN=manage Ad_user are considered superusers
     */
    const SUPERUSER_GROUP_CN = 'ManageUser';
    
    /**
     * IT OU Configuration
     */
    const IT_OU = 'OU=IT,OU=rpp-user,DC=rpphosp,DC=local';
    
    /**
     * Restricted OUs that should not have access
     */
    const RESTRICTED_OUS = [
        'OU=rpp-register,DC=rpphosp,DC=local',
    ];
    
    /**
     * Permission Constants
     */
    const PERMISSION_AD_USER_VIEW = 'adUserView';
    const PERMISSION_AD_USER_CREATE = 'adUserCreate';
    const PERMISSION_AD_USER_UPDATE = 'adUserUpdate';
    const PERMISSION_AD_USER_DELETE = 'adUserDelete';
    const PERMISSION_LDAP_USER_VIEW = 'ldapUserView';
    const PERMISSION_LDAP_USER_CREATE = 'ldapUserCreate';
    const PERMISSION_LDAP_USER_UPDATE = 'ldapUserUpdate';
    const PERMISSION_LDAP_USER_DELETE = 'ldapUserDelete';
    const PERMISSION_LDAP_USER_MOVE = 'ldapUserMove';
    const PERMISSION_LDAP_USER_TOGGLE_STATUS = 'ldapUserToggleStatus';
    // Group management
    const PERMISSION_GROUP_VIEW = 'groupView';
    const PERMISSION_GROUP_CREATE = 'groupCreate';
    const PERMISSION_GROUP_UPDATE = 'groupUpdate';
    const PERMISSION_GROUP_DELETE = 'groupDelete';
    const PERMISSION_GROUP_MANAGE_MEMBERS = 'groupManageMembers';
    
    /**
     * Role Constants
     */
    const ROLE_ADMIN = 'admin';
    const ROLE_SUPERUSER = 'superuser';
    const ROLE_SUPERUSER_CN = 'ManageUser';
    const ROLE_USER = 'user';
    const ROLE_GUEST = 'guest';
    
    public function __construct()
    {
        $this->authManager = Yii::$app->has('authManager') ? Yii::$app->authManager : null;
        $this->user = Yii::$app->has('user') ? Yii::$app->user : null;
    }
    
    /**
     * Check if current user has specific permission
     * 
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        // Admin always allowed
        if ($this->isLdapAdmin()) {
            return true;
        }

        // Superuser: allow ONLY view permissions
        $superUserViewPermissions = [
            self::PERMISSION_AD_USER_VIEW,
            self::PERMISSION_LDAP_USER_VIEW,
        ];
        if ($this->isSuperUser() && in_array($permission, $superUserViewPermissions, true)) {
            return true;
        }

        return false;
    }
    
    /**
     * Check if current user is LDAP admin
     * 
     * @return bool
     */
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
        
        // Check IT OU membership
        if ($this->isInITOU($userData)) {
            Yii::debug("User is in IT OU");
            return true;
        }
        
        // Check admin groups
        if ($this->isInAdminGroups($userData)) {
            Yii::debug("User is in admin groups");
            return true;
        }
        
        Yii::debug("User is not admin");
        return false;
    }
    
    /**
     * Check if current user is LDAP superuser (non-admin elevated role)
     * 
     * @return bool
     */
    public function isSuperUser()
    {
        $userData = $this->getCurrentUserLdapData();
        if (!$userData) {
            Yii::debug("No user data found in session for superuser check");
            return false;
        }
        
        // If memberof is empty, not set, or not a valid array, try to refresh from LDAP
        $memberof = $userData['memberof'] ?? [];
        $needsRefresh = false;
        
        if (empty($memberof) || !isset($memberof) || !is_array($memberof)) {
            $needsRefresh = true;
        } else {
            // Check if memberof is a valid array (not just empty array or only has 'count' key)
            $validKeys = array_filter(array_keys($memberof), function($key) {
                return $key !== 'count' && $key !== 'Count';
            });
            if (empty($validKeys)) {
                $needsRefresh = true;
            }
        }
        
        if ($needsRefresh) {
            Yii::debug("memberof is empty or invalid, attempting to refresh from LDAP for superuser check");
            $userData = $this->refreshUserLdapData();
            if (!$userData) {
                Yii::debug("Failed to refresh user data from LDAP for superuser check");
                return false;
            }
        }
        
        Yii::debug("Checking superuser status for user: " . ($userData['samaccountname'] ?? 'unknown'));
        Yii::debug("User DN: " . ($userData['distinguishedname'] ?? 'not set'));
        Yii::debug("User groups count: " . (is_array($userData['memberof'] ?? []) ? count($userData['memberof']) : 0));
        Yii::debug("User groups: " . print_r($userData['memberof'] ?? [], true));
        
        // First check using memberof
        $result = $this->isInSuperUserGroups($userData);
        
        // If not found in memberof, the fallback method (checkDirectGroupMembership) 
        // is already called inside isInSuperUserGroups()
        
        Yii::debug("User superuser status: " . ($result ? 'true' : 'false'));
        return $result;
    }
    
    /**
     * Refresh user LDAP data from LDAP server
     * Uses ldap_read() to get complete memberof attribute
     * 
     * @return array|null
     */
    private function refreshUserLdapData()
    {
        if (!$this->user || $this->user->isGuest) {
            return null;
        }
        
        $username = $this->user->identity->username ?? null;
        if (!$username) {
            return null;
        }
        
        try {
            $ldap = new LdapHelper();
            $ldapConn = $ldap->getConnection();
            
            // First, get user DN using getUser()
            $ldapUser = $ldap->getUser($username);
            
            if (!$ldapUser) {
                Yii::debug("User not found: $username");
                return null;
            }
            
            // Format data similar to session format
            $getLdapValue = function($key, $default = '') use ($ldapUser) {
                if (!isset($ldapUser[$key])) {
                    return $default;
                }
                if (is_array($ldapUser[$key])) {
                    return $ldapUser[$key][0] ?? $default;
                }
                return $ldapUser[$key];
            };
            
            $userDN = $getLdapValue('distinguishedname', '');
            if (empty($userDN)) {
                Yii::debug("User DN not found for: $username");
                return null;
            }
            
            // Use ldap_read() to get complete memberof attribute
            // ldap_read() is more reliable for getting all values of multi-valued attributes
            Yii::debug("Reading user data directly from DN: $userDN");
            $attributes = ['memberof', 'cn', 'samaccountname', 'displayname', 'department', 
                          'mail', 'telephonenumber', 'ou', 'distinguishedname', 
                          'useraccountcontrol', 'whenchanged', 'whencreated'];
            
            $readResult = @ldap_read($ldapConn, $userDN, "(objectClass=*)", $attributes);
            if (!$readResult) {
                $error = ldap_error($ldapConn);
                Yii::debug("ldap_read failed, falling back to getUser() data. Error: $error");
                // Fallback to original method
            } else {
                $readEntries = ldap_get_entries($ldapConn, $readResult);
                if ($readEntries && $readEntries['count'] > 0) {
                    $readUser = $readEntries[0];
                    Yii::debug("Successfully read user data with ldap_read()");
                    // Use data from ldap_read() which has complete memberof
                    $ldapUser = $readUser;
                }
            }
            
            // Process memberof array - remove 'count' key and ensure it's a proper array
            $memberof = [];
            if (isset($ldapUser['memberof']) && is_array($ldapUser['memberof'])) {
                foreach ($ldapUser['memberof'] as $key => $value) {
                    // Skip 'count' and 'Count' keys
                    if ($key === 'count' || $key === 'Count') {
                        continue;
                    }
                    // Handle both array and string values
                    if (is_array($value)) {
                        $groupValue = $value[0] ?? '';
                        if (!empty($groupValue) && is_string($groupValue)) {
                            $memberof[] = trim($groupValue);
                        }
                    } elseif (is_string($value) && !empty($value)) {
                        $memberof[] = trim($value);
                    }
                }
            }
            
            Yii::debug("Processed memberof count: " . count($memberof));
            Yii::debug("Memberof groups: " . print_r($memberof, true));
            
            $userData = [
                'cn' => $getLdapValue('cn', $getLdapValue('displayname', '')),
                'samaccountname' => $getLdapValue('samaccountname', $username),
                'displayname' => $getLdapValue('displayname', ''),
                'department' => $getLdapValue('department', ''),
                'mail' => $getLdapValue('mail', ''),
                'telephonenumber' => $getLdapValue('telephonenumber', ''),
                'ou' => $getLdapValue('ou', ''),
                'distinguishedname' => $getLdapValue('distinguishedname', $userDN),
                'memberof' => $memberof,
                'useraccountcontrol' => intval($getLdapValue('useraccountcontrol', 0)),
                'whenchanged' => $getLdapValue('whenchanged', ''),
                'whencreated' => $getLdapValue('whencreated', ''),
            ];
            
            // Extract OU from distinguishedName if not set
            if (empty($userData['ou']) && !empty($userData['distinguishedname'])) {
                if (preg_match('/OU=([^,]+)/i', $userData['distinguishedname'], $matches)) {
                    $userData['ou'] = $matches[1];
                }
            }
            
            // Update session with refreshed data
            Yii::$app->session->set('ldapUserData', $userData);
            Yii::debug("Refreshed user LDAP data from server with " . count($memberof) . " groups");
            
            return $userData;
        } catch (\Exception $e) {
            Yii::error("Error refreshing user LDAP data: " . $e->getMessage());
            Yii::error("Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * Get current user's role name
     * 
     * @return string|null
     */
    public function getCurrentUserRole()
    {
        if (!$this->user || $this->user->isGuest) {
            return null;
        }
        
        $assignments = $this->authManager->getAssignments($this->user->id);
        foreach ($assignments as $assignment) {
            return $assignment->roleName;
        }
        
        return null;
    }
    
    /**
     * Check if current user is specifically a superuser (not admin)
     * 
     * @return bool
     */
    public function isSuperUserOnly()
    {
        return $this->isSuperUser() && !$this->isLdapAdmin();
    }
    
    /**
     * Check if current user can delete groups
     * Only users in CN=manage Ad_admin,CN=Users-RPP,DC=rpphosp,DC=local can delete groups
     * 
     * @return bool
     */
    public function canDeleteGroup()
    {
        $userData = $this->getCurrentUserLdapData();
        if (!$userData) {
            Yii::debug("No user data found in session for group delete check");
            return false;
        }
        
        // If memberof is empty or not set, try to refresh from LDAP
        if (empty($userData['memberof']) || !isset($userData['memberof'])) {
            Yii::debug("memberof is empty, attempting to refresh from LDAP for group delete check");
            $userData = $this->refreshUserLdapData();
            if (!$userData) {
                Yii::debug("Failed to refresh user data from LDAP for group delete check");
                return false;
            }
        }
        
        Yii::debug("Checking group delete permission for user: " . ($userData['samaccountname'] ?? 'unknown'));
        Yii::debug("User groups: " . print_r($userData['memberof'] ?? [], true));
        
        // Check memberof groups
        $userGroups = isset($userData['memberof']) ? $userData['memberof'] : [];
        
        // Handle both array and string formats
        if (!is_array($userGroups)) {
            $userGroups = [$userGroups];
        }
        
        foreach ($userGroups as $index => $group) {
            // Skip if it's the 'count' key from LDAP array
            if ($index === 'count' || $index === 'Count') {
                continue;
            }
            
            // Handle both string and array formats
            if (is_array($group)) {
                $group = isset($group[0]) ? $group[0] : '';
            }
            
            if (empty($group) || !is_string($group)) {
                continue;
            }
            
            // Normalize group DN for comparison
            $normalizedGroup = trim($group);
            Yii::debug("Checking group for delete permission: $normalizedGroup");
            
            // Method 1: Check against full DN (exact or substring match)
            foreach (self::GROUP_DELETE_GROUPS as $deleteGroup) {
                $normalizedDeleteGroup = trim($deleteGroup);
                
                // Exact match or substring match (case-insensitive)
                if (stripos($normalizedGroup, $normalizedDeleteGroup) !== false) {
                    Yii::debug("✓ MATCH: User can delete groups (full DN): $normalizedGroup matches $normalizedDeleteGroup");
                    return true;
                }
                
                // Also check reverse (in case order is different)
                if (stripos($normalizedDeleteGroup, $normalizedGroup) !== false) {
                    Yii::debug("✓ MATCH: User can delete groups (reverse DN): $normalizedDeleteGroup contains $normalizedGroup");
                    return true;
                }
            }
            
            // Method 2: Extract and compare CN only (most flexible)
            $cn = $this->extractCnFromDn($normalizedGroup);
            if ($cn) {
                Yii::debug("Extracted CN from group: $cn");
                if ($this->cnInList($cn, self::GROUP_DELETE_GROUP_CNS)) {
                    // Also check if the group DN contains "Users-RPP" to ensure it's the correct container
                    if (stripos($normalizedGroup, 'Users-RPP') !== false) {
                        Yii::debug("✓ MATCH: User can delete groups by CN and container: $cn in Users-RPP");
                        return true;
                    }
                }
            }
            
            // Method 3: Check if group contains the CN name and container
            foreach (self::GROUP_DELETE_GROUP_CNS as $deleteCn) {
                if (stripos($normalizedGroup, $deleteCn) !== false && stripos($normalizedGroup, 'Users-RPP') !== false) {
                    Yii::debug("✓ MATCH: User can delete groups by CN substring and container: $normalizedGroup contains $deleteCn and Users-RPP");
                    return true;
                }
            }
        }
        
        Yii::debug("✗ NO MATCH: User cannot delete groups");
        return false;
    }
    
    /**
     * Get current user's LDAP data from session
     * 
     * @return array|null
     */
    private function getCurrentUserLdapData()
    {
        return Yii::$app->session->get('ldapUserData');
    }
    
    /**
     * Check if user is in IT OU
     * 
     * @param array $userData
     * @return bool
     */
    private function isInITOU($userData)
    {
        if (!isset($userData['distinguishedname'])) {
            return false;
        }
        
        $dn = $userData['distinguishedname'];
        return stripos($dn, self::IT_OU) !== false;
    }
    
    /**
     * Check if user is in admin groups
     * 
     * @param array $userData
     * @return bool
     */
    private function isInAdminGroups($userData)
    {
        // Check memberof groups
        $userGroups = isset($userData['memberof']) ? $userData['memberof'] : [];
        
        // Handle both array and string formats
        if (!is_array($userGroups)) {
            $userGroups = [$userGroups];
        }
        
        foreach ($userGroups as $index => $group) {
            // Skip if it's the 'count' key from LDAP array
            if ($index === 'count' || $index === 'Count') {
                continue;
            }
            
            // Handle both string and array formats
            if (is_array($group)) {
                $group = isset($group[0]) ? $group[0] : '';
            }
            
            if (empty($group) || !is_string($group)) {
                continue;
            }
            
            // Normalize group DN for comparison
            $normalizedGroup = trim($group);
            Yii::debug("Checking admin group: $normalizedGroup");
            
            // Method 1: Check against full DN (exact or substring match)
            foreach (self::ADMIN_GROUPS as $adminGroup) {
                $normalizedAdminGroup = trim($adminGroup);
                
                // Exact match or substring match (case-insensitive)
                if (stripos($normalizedGroup, $normalizedAdminGroup) !== false) {
                    Yii::debug("✓ MATCH: User is in admin group (full DN): $normalizedGroup matches $normalizedAdminGroup");
                    return true;
                }
                
                // Also check reverse (in case order is different)
                if (stripos($normalizedAdminGroup, $normalizedGroup) !== false) {
                    Yii::debug("✓ MATCH: User is in admin group (reverse DN): $normalizedAdminGroup contains $normalizedGroup");
                    return true;
                }
            }
            
            // Method 2: Extract and compare CN only (most flexible - handles container name differences)
            $cn = $this->extractCnFromDn($normalizedGroup);
            if ($cn) {
                Yii::debug("Extracted CN from group: $cn");
                if ($this->cnInList($cn, self::ADMIN_GROUP_CNS)) {
                    Yii::debug("✓ MATCH: User is in admin group by CN: $cn");
                    return true;
                }
            }
            
            // Method 3: Check if group contains the CN name anywhere (very flexible)
            // Also check with spaces removed (e.g., "manage Ad_it" vs "manageAd_it")
            foreach (self::ADMIN_GROUP_CNS as $adminCn) {
                // Direct substring match
                if (stripos($normalizedGroup, $adminCn) !== false) {
                    Yii::debug("✓ MATCH: User is in admin group by CN substring: $normalizedGroup contains $adminCn");
                    return true;
                }
                // Match with spaces removed (handle "manage Ad_it" vs "manageAd_it")
                $adminCnNoSpace = str_replace(' ', '', $adminCn);
                $groupNoSpace = str_replace(' ', '', $normalizedGroup);
                if (stripos($groupNoSpace, $adminCnNoSpace) !== false) {
                    Yii::debug("✓ MATCH: User is in admin group by CN substring (no spaces): $groupNoSpace contains $adminCnNoSpace");
                    return true;
                }
            }
        }
        
        // Check distinguishedname for direct group membership
        if (isset($userData['distinguishedname'])) {
            $dn = trim($userData['distinguishedname']);
            Yii::debug("Checking user DN for admin groups: $dn");
            foreach (self::ADMIN_GROUPS as $adminGroup) {
                $normalizedAdminGroup = trim($adminGroup);
                if (stripos($dn, $normalizedAdminGroup) !== false) {
                    Yii::debug("✓ MATCH: User DN matches admin group: $dn contains $normalizedAdminGroup");
                    return true;
                }
            }
        }
        
        Yii::debug("✗ NO MATCH: User is NOT in any admin groups");
        return false;
    }
    
    /**
     * Check if user is in superuser groups
     * Checks memberof for group with CN=manage Ad_user (any container/OU)
     * 
     * @param array $userData
     * @return bool
     */
    private function isInSuperUserGroups($userData)
    {
        // Check memberof groups - this is the primary method
        if (!isset($userData['memberof'])) {
            Yii::debug("=== Super User Check Debug ===");
            Yii::debug("memberof not found in userData");
            return false;
        }
        
        $userGroups = $userData['memberof'];
        
        // Debug: Log all user groups for troubleshooting
        Yii::debug("=== Super User Check Debug ===");
        Yii::debug("User groups type: " . gettype($userGroups));
        Yii::debug("User groups count: " . (is_array($userGroups) ? count($userGroups) : 'N/A'));
        Yii::debug("User groups: " . print_r($userGroups, true));
        Yii::debug("Superuser group CN to match: " . self::SUPERUSER_GROUP_CN);
        
        // Handle both array and string formats
        if (!is_array($userGroups)) {
            $userGroups = [$userGroups];
        }
        
        foreach ($userGroups as $index => $group) {
            // Skip if it's the 'count' key from LDAP array
            if ($index === 'count' || $index === 'Count') {
                Yii::debug("Skipping count key: $index");
                continue;
            }
            
            // Handle both string and array formats
            if (is_array($group)) {
                $group = isset($group[0]) ? $group[0] : '';
            }
            
            if (empty($group) || !is_string($group)) {
                Yii::debug("Skipping invalid group at index $index: " . gettype($group));
                continue;
            }
            
            // Normalize group DN for comparison (handle case sensitivity and whitespace)
            $normalizedGroup = trim($group);
            Yii::debug("Checking group: $normalizedGroup");
            
            // Extract CN from group DN
            $cnResult = $this->extractCnFromDn($normalizedGroup);
            // Type check: ensure $cnResult is a string before comparison
            if ($cnResult !== null && is_string($cnResult) && $cnResult !== '') {
                // Use explicit string variable to satisfy type checker
                /** @var string $cnValue */
                $cnValue = $cnResult;
                Yii::debug("Extracted CN from group: $cnValue");
                // Compare CN (case-insensitive)
                $superUserCn = (string) self::SUPERUSER_GROUP_CN;
                if (strcasecmp($cnValue, $superUserCn) === 0) {
                    Yii::debug("✓ MATCH: User is in superuser group (CN match): $cnValue");
                    return true;
                }
            }
        }
        
        // Fallback: Check group membership directly from LDAP group object
        // This is needed because memberof attribute may not always return all groups
        Yii::debug("No match found in memberof, trying direct group membership check...");
        $fallbackResult = $this->checkDirectGroupMembership($userData);
        if ($fallbackResult) {
            Yii::debug("✓ MATCH: User is in superuser group (direct group check)");
            return true;
        }
        
        Yii::debug("✗ NO MATCH: User is NOT in superuser group");
        return false;
    }

    /**
     * Extract CN component from a DN string
     * Handles escaped characters and spaces in CN values
     * 
     * @param string $dn
     * @return string|null Returns string CN value or null if not found
     */
    private function extractCnFromDn($dn): ?string
    {
        if (!is_string($dn) || $dn === '') { 
            return null; 
        }
        // Find first CN=... segment
        // Match CN= followed by value (may contain escaped characters like \20 for space)
        if (preg_match('/CN=([^,]+)/i', $dn, $matches) && isset($matches[1])) {
            /** @var string $cnValue */
            $cnValue = $matches[1];
            if (!is_string($cnValue)) {
                return null;
            }
            $cn = trim($cnValue);
            // Decode LDAP escaped characters (e.g., \20 = space, \2C = comma)
            $cn = preg_replace_callback('/\\\\([0-9A-Fa-f]{2})/', function($m) {
                return chr(hexdec($m[1]));
            }, $cn);
            $result = trim($cn);
            // Ensure result is a string
            return is_string($result) && $result !== '' ? $result : null;
        }
        return null;
    }

    /**
     * Case-insensitive CN list membership
     */
    private function cnInList($cn, array $cnList)
    {
        foreach ($cnList as $expected) {
            if (strcasecmp($cn, $expected) === 0) { return true; }
        }
        return false;
    }
    
    /**
     * Initialize RBAC permissions and roles
     * This method should be called during application setup
     */
    public function initializeRbac()
    {
        // Create permissions
        $this->createPermissions();
        
        // Create roles
        $this->createRoles();
        
        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }
    
    /**
     * Create RBAC permissions
     */
    private function createPermissions()
    {
        if (!$this->authManager) {
            throw new \Exception('AuthManager is not available');
        }
        
        $permissions = [
            self::PERMISSION_AD_USER_VIEW => 'View AD Users',
            self::PERMISSION_AD_USER_CREATE => 'Create AD Users',
            self::PERMISSION_AD_USER_UPDATE => 'Update AD Users',
            self::PERMISSION_AD_USER_DELETE => 'Delete AD Users',
            self::PERMISSION_LDAP_USER_VIEW => 'View LDAP Users',
            self::PERMISSION_LDAP_USER_CREATE => 'Create LDAP Users',
            self::PERMISSION_LDAP_USER_UPDATE => 'Update LDAP Users',
            self::PERMISSION_LDAP_USER_DELETE => 'Delete LDAP Users',
            self::PERMISSION_LDAP_USER_MOVE => 'Move LDAP Users',
            self::PERMISSION_LDAP_USER_TOGGLE_STATUS => 'Toggle LDAP User Status',
            // Group permissions
            self::PERMISSION_GROUP_VIEW => 'View AD Groups',
            self::PERMISSION_GROUP_CREATE => 'Create AD Groups',
            self::PERMISSION_GROUP_UPDATE => 'Update AD Groups',
            self::PERMISSION_GROUP_DELETE => 'Delete AD Groups',
            self::PERMISSION_GROUP_MANAGE_MEMBERS => 'Manage AD Group Members',
        ];
        
        foreach ($permissions as $name => $description) {
            $permission = $this->authManager->getPermission($name);
            if (!$permission) {
                $permission = $this->authManager->createPermission($name);
                $permission->description = $description;
                $this->authManager->add($permission);
            }
        }
    }
    
    /**
     * Create RBAC roles
     */
    private function createRoles()
    {
        if (!$this->authManager) {
            throw new \Exception('AuthManager is not available');
        }
        
        $roles = [
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_SUPERUSER => 'Super User',
            self::ROLE_USER => 'Regular User',
            self::ROLE_GUEST => 'Guest User',
        ];
        
        foreach ($roles as $name => $description) {
            $role = $this->authManager->getRole($name);
            if (!$role) {
                $role = $this->authManager->createRole($name);
                $role->description = $description;
                $this->authManager->add($role);
            }
        }
    }
    
    /**
     * Assign permissions to roles
     */
    private function assignPermissionsToRoles()
    {
        $adminRole = $this->authManager->getRole(self::ROLE_ADMIN);
        $superUserRole = $this->authManager->getRole(self::ROLE_SUPERUSER);
        $userRole = $this->authManager->getRole(self::ROLE_USER);
        
        // Admin gets all permissions
        $adminPermissions = [
            self::PERMISSION_AD_USER_VIEW,
            self::PERMISSION_AD_USER_CREATE,
            self::PERMISSION_AD_USER_UPDATE,
            self::PERMISSION_AD_USER_DELETE,
            self::PERMISSION_LDAP_USER_VIEW,
            self::PERMISSION_LDAP_USER_CREATE,
            self::PERMISSION_LDAP_USER_UPDATE,
            self::PERMISSION_LDAP_USER_DELETE,
            self::PERMISSION_LDAP_USER_MOVE,
            self::PERMISSION_LDAP_USER_TOGGLE_STATUS,
            self::PERMISSION_GROUP_VIEW,
            self::PERMISSION_GROUP_CREATE,
            self::PERMISSION_GROUP_UPDATE,
            self::PERMISSION_GROUP_DELETE,
            self::PERMISSION_GROUP_MANAGE_MEMBERS,
        ];
        
        foreach ($adminPermissions as $permissionName) {
            $permission = $this->authManager->getPermission($permissionName);
            if ($permission && !$this->authManager->hasChild($adminRole, $permission)) {
                $this->authManager->addChild($adminRole, $permission);
            }
        }
        
        // Superuser gets ONLY view permissions (no create/update/delete)
        $superUserPermissions = [
            self::PERMISSION_AD_USER_VIEW,
            self::PERMISSION_LDAP_USER_VIEW,
        ];
        
        foreach ($superUserPermissions as $permissionName) {
            $permission = $this->authManager->getPermission($permissionName);
            if ($permission && !$this->authManager->hasChild($superUserRole, $permission)) {
                $this->authManager->addChild($superUserRole, $permission);
            }
        }
        
        // Regular user gets view permissions only
        $userPermissions = [
            self::PERMISSION_AD_USER_VIEW,
            self::PERMISSION_LDAP_USER_VIEW,
        ];
        
        foreach ($userPermissions as $permissionName) {
            $permission = $this->authManager->getPermission($permissionName);
            if ($permission && !$this->authManager->hasChild($userRole, $permission)) {
                $this->authManager->addChild($userRole, $permission);
            }
        }
    }
    
    /**
     * Assign role to user based on LDAP data
     * 
     * @param string $userId
     * @param array $ldapData
     */
    public function assignRoleToUser($userId, $ldapData)
    {
        // Remove existing assignments
        $this->authManager->revokeAll($userId);
        
        // Determine role based on LDAP data
        // Priority: Admin first, then Superuser, then Regular User
        if ($this->isLdapAdminByData($ldapData)) {
            $role = $this->authManager->getRole(self::ROLE_ADMIN);
            Yii::debug("Assigning ADMIN role to user: $userId");
        } elseif ($this->isSuperUserByData($ldapData)) {
            $role = $this->authManager->getRole(self::ROLE_SUPERUSER);
            Yii::debug("Assigning SUPERUSER role to user: $userId");
        } else {
            $role = $this->authManager->getRole(self::ROLE_USER);
            Yii::debug("Assigning USER role to user: $userId");
        }
        
        if ($role) {
            $this->authManager->assign($role, $userId);
            Yii::debug("Successfully assigned role: " . $role->name . " to user: $userId");
        }
    }
    
    /**
     * Check if LDAP data indicates admin status
     * 
     * @param array $ldapData
     * @return bool
     */
    private function isLdapAdminByData($ldapData)
    {
        // Check IT OU membership
        if (isset($ldapData['distinguishedname'])) {
            $dn = $ldapData['distinguishedname'];
            if (stripos($dn, self::IT_OU) !== false) {
                return true;
            }
        }
        
        // Check admin groups
        if (isset($ldapData['memberof'])) {
            $userGroups = $ldapData['memberof'];
            foreach ($userGroups as $group) {
                foreach (self::ADMIN_GROUPS as $adminGroup) {
                    if (stripos($group, $adminGroup) !== false) {
                        return true;
                    }
                }
                // Fallback by CN
                $cn = $this->extractCnFromDn($group);
                if ($cn && $this->cnInList($cn, self::ADMIN_GROUP_CNS)) {
                    return true;
                }
            }
        }
        
        // Check distinguishedname for direct group membership
        if (isset($ldapData['distinguishedname'])) {
            $dn = $ldapData['distinguishedname'];
            foreach (self::ADMIN_GROUPS as $adminGroup) {
                if (stripos($dn, $adminGroup) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if user's OU allows access to the system
     * Superusers have access regardless of OU
     * Users in rpp-register OU should not have access (unless they are superuser or admin)
     * 
     * @param array $ldapData
     * @return bool
     */
    public function hasAccessByOu($ldapData)
    {
        if (!isset($ldapData['distinguishedname'])) {
            return false;
        }
        
        $dn = $ldapData['distinguishedname'];
        
        // Superuser groups have access regardless of OU
        if ($this->isSuperUserByData($ldapData)) {
            Yii::debug("Superuser has access regardless of OU");
            return true;
        }
        
        // Admin groups always have access regardless of OU
        if ($this->isLdapAdminByData($ldapData)) {
            Yii::debug("Admin has access regardless of OU");
            return true;
        }
        
        // Check if user is in restricted OU
        foreach (self::RESTRICTED_OUS as $restrictedOu) {
            if (stripos($dn, $restrictedOu) !== false) {
                Yii::debug("User is in restricted OU: $restrictedOu");
                return false;
            }
        }
        
        // Users in rpp-user OU (including IT) have access
        if (stripos($dn, 'OU=rpp-user') !== false) {
            return true;
        }
        
        // Default: no access for unknown OUs
        return false;
    }
    
    /**
     * Check if user's current session OU allows access
     * 
     * @return bool
     */
    public function hasAccessByCurrentOu()
    {
        $userData = $this->getCurrentUserLdapData();
        if (!$userData) {
            return false;
        }
        
        return $this->hasAccessByOu($userData);
    }
    
    /**
     * Check if LDAP data indicates superuser status
     * Checks memberof for group with CN=manage Ad_user (any container/OU)
     * 
     * @param array $ldapData
     * @return bool
     */
    private function isSuperUserByData($ldapData)
    {
        // Check memberof groups - this is the primary method
        if (!isset($ldapData['memberof'])) {
            return false;
        }
        
        $userGroups = $ldapData['memberof'];
        
        // Handle both array and string formats
        if (!is_array($userGroups)) {
            $userGroups = [$userGroups];
        }
        
        foreach ($userGroups as $index => $group) {
            // Skip if it's the 'count' key from LDAP array
            if ($index === 'count' || $index === 'Count') {
                continue;
            }
            
            // Handle both string and array formats
            if (is_array($group)) {
                $group = isset($group[0]) ? $group[0] : '';
            }
            
            if (empty($group) || !is_string($group)) {
                continue;
            }
            
            // Normalize group DN for comparison
            $normalizedGroup = trim($group);
            
            // Extract CN from group DN
            $cnResult = $this->extractCnFromDn($normalizedGroup);
            // Type check: ensure $cnResult is a string before comparison
            if ($cnResult !== null && is_string($cnResult) && $cnResult !== '') {
                // Use explicit string variable to satisfy type checker
                /** @var string $cnValue */
                $cnValue = $cnResult;
                // Compare CN (case-insensitive)
                $superUserCn = (string) self::SUPERUSER_GROUP_CN;
                if (strcasecmp($cnValue, $superUserCn) === 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Escape LDAP filter value
     * 
     * @param string $value
     * @return string
     */
    private function escapeLdapValueForFilter($value)
    {
        // Escape special LDAP filter characters
        $specialChars = ['\\', '*', '(', ')', '\0'];
        $escaped = $value;
        foreach ($specialChars as $char) {
            if ($char === '\\') {
                $escaped = str_replace('\\', '\\5c', $escaped);
            } elseif ($char === '*') {
                $escaped = str_replace('*', '\\2a', $escaped);
            } elseif ($char === '(') {
                $escaped = str_replace('(', '\\28', $escaped);
            } elseif ($char === ')') {
                $escaped = str_replace(')', '\\29', $escaped);
            }
        }
        return $escaped;
    }
    
    /**
     * Check group membership using LDAP filter
     * This method searches for groups where the user is a member
     * 
     * @param mixed $ldapConn LDAP connection resource
     * @param string $userDN
     * @param string $baseDn
     * @return bool
     */
    private function checkGroupMembershipByFilter($ldapConn, $userDN, $baseDn)
    {
        try {
            // Escape user DN for filter (escape special characters)
            $escapedUserDN = $this->escapeLdapValueForFilter($userDN);
            $escapedGroupCN = $this->escapeLdapValueForFilter(self::SUPERUSER_GROUP_CN);
            
            // Search for groups with CN=manage Ad_user that have this user as a member
            // Filter: (&(cn=manage Ad_user)(member=userDN))
            $filter = "(&(cn=" . $escapedGroupCN . ")(member=" . $escapedUserDN . "))";
            
            Yii::debug("Checking membership using filter: $filter");
            
            // @phpstan-ignore-next-line - LDAP connection can be resource or LDAP\Connection
            $search = @ldap_search($ldapConn, $baseDn, $filter, ['distinguishedname', 'cn']);
            if (!$search) {
                // @phpstan-ignore-next-line - LDAP connection can be resource or LDAP\Connection
                $error = ldap_error($ldapConn);
                Yii::debug("Filter search failed: $error");
                return false;
            }
            
            // @phpstan-ignore-next-line - LDAP connection can be resource or LDAP\Connection
            $entries = ldap_get_entries($ldapConn, $search);
            if ($entries && $entries['count'] > 0) {
                Yii::debug("✓ User is member of group (filter method): Found " . $entries['count'] . " matching group(s)");
                return true;
            }
            
            Yii::debug("User is not member of group (filter method)");
            return false;
            
        } catch (\Exception $e) {
            Yii::error("Error checking group membership by filter: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check group membership directly from LDAP group object
     * This is a fallback method when memberof attribute doesn't return all groups
     * 
     * @param array $userData
     * @return bool
     */
    private function checkDirectGroupMembership($userData)
    {
        try {
            if (!isset($userData['distinguishedname']) || empty($userData['distinguishedname'])) {
                Yii::debug("No user DN available for direct group membership check");
                return false;
            }
            
            $userDN = trim($userData['distinguishedname']);
            $ldap = new LdapHelper();
            $ldapConn = $ldap->getConnection();
            
            // Escape LDAP value for filter (escape special characters)
            $escapedCn = $this->escapeLdapValueForFilter(self::SUPERUSER_GROUP_CN);
            
            // Method 1: Search for group and check members
            $baseDn = Yii::$app->params['ldap']['base_dn'];
            $filter = "(cn=" . $escapedCn . ")";
            $attributes = ['distinguishedname', 'member'];
            
            Yii::debug("Searching for group with CN: " . self::SUPERUSER_GROUP_CN);
            Yii::debug("Search filter: $filter");
            Yii::debug("Base DN: $baseDn");
            Yii::debug("User DN: $userDN");
            
            $search = @ldap_search($ldapConn, $baseDn, $filter, $attributes);
            if (!$search) {
                $error = ldap_error($ldapConn);
                Yii::debug("Failed to search for group: $error");
                
                // Method 2: Try direct filter to check membership
                Yii::debug("Trying alternative method: direct membership filter");
                return $this->checkGroupMembershipByFilter($ldapConn, $userDN, $baseDn);
            }
            
            $entries = ldap_get_entries($ldapConn, $search);
            if (!$entries || $entries['count'] == 0) {
                Yii::debug("Group with CN=" . self::SUPERUSER_GROUP_CN . " not found");
                
                // Method 2: Try direct filter to check membership
                Yii::debug("Trying alternative method: direct membership filter");
                return $this->checkGroupMembershipByFilter($ldapConn, $userDN, $baseDn);
            }
            
            $group = $entries[0];
            $groupDN = $group['distinguishedname'][0] ?? '';
            Yii::debug("Found group DN: $groupDN");
            
            // Check if user DN is in the group's member attribute
            // Note: For large groups, member attribute might be paginated
            // We'll check what we can get, and if needed, use range retrieval
            if (isset($group['member']) && is_array($group['member'])) {
                $memberCount = isset($group['member']['count']) ? intval($group['member']['count']) : 0;
                Yii::debug("Group has $memberCount members (from count)");
                
                // Check all members we have
                foreach ($group['member'] as $key => $member) {
                    if ($key === 'count') {
                        continue;
                    }
                    
                    $memberDN = is_array($member) ? ($member[0] ?? '') : $member;
                    $memberDN = trim($memberDN);
                    
                    if (empty($memberDN)) {
                        continue;
                    }
                    
                    Yii::debug("Checking member DN: $memberDN");
                    
                    // Compare user DN with member DN (case-insensitive)
                    if (strcasecmp($userDN, $memberDN) === 0) {
                        Yii::debug("✓ User DN matches group member: $userDN");
                        return true;
                    }
                }
                
                // If we didn't find the user and member count is large, 
                // try reading the group directly with range retrieval
                if ($memberCount > 0 && !isset($group['member'][0])) {
                    Yii::debug("Group has members but not in standard format, trying direct read...");
                    // Try reading member attribute with range
                    $rangeAttributes = ['member;range=0-*'];
                    $rangeRead = @ldap_read($ldapConn, $groupDN, "(objectClass=*)", $rangeAttributes);
                    if ($rangeRead) {
                        $rangeEntries = ldap_get_entries($ldapConn, $rangeRead);
                        if ($rangeEntries && $rangeEntries['count'] > 0) {
                            $rangeGroup = $rangeEntries[0];
                            if (isset($rangeGroup['member']) && is_array($rangeGroup['member'])) {
                                foreach ($rangeGroup['member'] as $key => $member) {
                                    if ($key === 'count') {
                                        continue;
                                    }
                                    $memberDN = is_array($member) ? ($member[0] ?? '') : $member;
                                    $memberDN = trim($memberDN);
                                    if (!empty($memberDN) && strcasecmp($userDN, $memberDN) === 0) {
                                        Yii::debug("✓ User DN matches group member (range read): $userDN");
                                        return true;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                Yii::debug("Group has no member attribute or member is not an array");
            }
            
            Yii::debug("User DN not found in group members");
            return false;
            
        } catch (\Exception $e) {
            Yii::error("Error checking direct group membership: " . $e->getMessage());
            Yii::error("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
}
