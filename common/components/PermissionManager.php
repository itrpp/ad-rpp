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
     * Support multiple container names for flexibility
     */
    const SUPERUSER_GROUPS = [
        'CN=manage Ad_user,DC=rpphosp,DC=local',
    ];
    // CN fallback names (match by CN only)
    const SUPERUSER_GROUP_CNS = [
        'manage Ad_user',
    ];
    
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
        
        // If memberof is empty or not set, try to refresh from LDAP
        if (empty($userData['memberof']) || !isset($userData['memberof'])) {
            Yii::debug("memberof is empty, attempting to refresh from LDAP");
            $userData = $this->refreshUserLdapData();
            if (!$userData) {
                Yii::debug("Failed to refresh user data from LDAP");
                return false;
            }
        }
        
        Yii::debug("Checking superuser status for user: " . ($userData['samaccountname'] ?? 'unknown'));
        Yii::debug("User DN: " . ($userData['distinguishedname'] ?? 'not set'));
        Yii::debug("User groups: " . print_r($userData['memberof'] ?? [], true));
        
        $result = $this->isInSuperUserGroups($userData);
        Yii::debug("User superuser status: " . ($result ? 'true' : 'false'));
        return $result;
    }
    
    /**
     * Refresh user LDAP data from LDAP server
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
            $ldapUser = $ldap->getUser($username);
            
            if (!$ldapUser) {
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
            
            $userData = [
                'cn' => $getLdapValue('cn', $getLdapValue('displayname', '')),
                'samaccountname' => $getLdapValue('samaccountname', $username),
                'displayname' => $getLdapValue('displayname', ''),
                'department' => $getLdapValue('department', ''),
                'mail' => $getLdapValue('mail', ''),
                'telephonenumber' => $getLdapValue('telephonenumber', ''),
                'ou' => $getLdapValue('ou', ''),
                'distinguishedname' => $getLdapValue('distinguishedname', ''),
                'memberof' => isset($ldapUser['memberof']) ? (is_array($ldapUser['memberof']) ? array_slice($ldapUser['memberof'], 1) : []) : [],
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
            Yii::debug("Refreshed user LDAP data from server");
            
            return $userData;
        } catch (\Exception $e) {
            Yii::error("Error refreshing user LDAP data: " . $e->getMessage());
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
     * 
     * @param array $userData
     * @return bool
     */
    private function isInSuperUserGroups($userData)
    {
        // Check memberof groups
        $userGroups = isset($userData['memberof']) ? $userData['memberof'] : [];
        
        // Debug: Log all user groups for troubleshooting
        Yii::debug("=== Super User Check Debug ===");
        Yii::debug("User groups count: " . count($userGroups));
        Yii::debug("User groups: " . print_r($userGroups, true));
        Yii::debug("Superuser groups to match: " . print_r(self::SUPERUSER_GROUPS, true));
        Yii::debug("Superuser CNs to match: " . print_r(self::SUPERUSER_GROUP_CNS, true));
        
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
            
            // Normalize group DN for comparison (handle case sensitivity and whitespace)
            $normalizedGroup = trim($group);
            Yii::debug("Checking group: $normalizedGroup");
            
            // Method 1: Check against full DN (exact or substring match)
            foreach (self::SUPERUSER_GROUPS as $superGroup) {
                $normalizedSuperGroup = trim($superGroup);
                
                // Exact match or substring match (case-insensitive)
                if (stripos($normalizedGroup, $normalizedSuperGroup) !== false) {
                    Yii::debug("✓ MATCH: User is in superuser group (full DN): $normalizedGroup matches $normalizedSuperGroup");
                    return true;
                }
                
                // Also check reverse (in case order is different)
                if (stripos($normalizedSuperGroup, $normalizedGroup) !== false) {
                    Yii::debug("✓ MATCH: User is in superuser group (reverse DN): $normalizedSuperGroup contains $normalizedGroup");
                    return true;
                }
            }
            
            // Method 2: Extract and compare CN only (most flexible - handles container name differences)
            $cn = $this->extractCnFromDn($normalizedGroup);
            if ($cn) {
                Yii::debug("Extracted CN from group: $cn");
                if ($this->cnInList($cn, self::SUPERUSER_GROUP_CNS)) {
                    Yii::debug("✓ MATCH: User is in superuser group by CN: $cn");
                    return true;
                }
            }
            
            // Method 3: Check if group contains the CN name anywhere (very flexible)
            // Also check with spaces removed (e.g., "manage Ad_user" vs "manageAd_user")
            foreach (self::SUPERUSER_GROUP_CNS as $superCn) {
                // Direct substring match
                if (stripos($normalizedGroup, $superCn) !== false) {
                    Yii::debug("✓ MATCH: User is in superuser group by CN substring: $normalizedGroup contains $superCn");
                    return true;
                }
                // Match with spaces removed (handle "manage Ad_user" vs "manageAd_user")
                $superCnNoSpace = str_replace(' ', '', $superCn);
                $groupNoSpace = str_replace(' ', '', $normalizedGroup);
                if (stripos($groupNoSpace, $superCnNoSpace) !== false) {
                    Yii::debug("✓ MATCH: User is in superuser group by CN substring (no spaces): $groupNoSpace contains $superCnNoSpace");
                    return true;
                }
            }
        }
        
        // Check distinguishedname for direct group membership (unlikely but possible)
        if (isset($userData['distinguishedname'])) {
            $dn = trim($userData['distinguishedname']);
            Yii::debug("Checking user DN: $dn");
            foreach (self::SUPERUSER_GROUPS as $superGroup) {
                $normalizedSuperGroup = trim($superGroup);
                if (stripos($dn, $normalizedSuperGroup) !== false) {
                    Yii::debug("✓ MATCH: User DN matches superuser group: $dn contains $normalizedSuperGroup");
                    return true;
                }
            }
        }
        
        Yii::debug("✗ NO MATCH: User is NOT in any superuser groups");
        return false;
    }

    /**
     * Extract CN component from a DN string
     * Handles escaped characters and spaces in CN values
     */
    private function extractCnFromDn($dn)
    {
        if (!is_string($dn) || $dn === '') { return null; }
        // Find first CN=... segment
        // Match CN= followed by value (may contain escaped characters like \20 for space)
        if (preg_match('/CN=([^,]+)/i', $dn, $matches)) {
            $cn = trim($matches[1]);
            // Decode LDAP escaped characters (e.g., \20 = space, \2C = comma)
            $cn = preg_replace_callback('/\\\\([0-9A-Fa-f]{2})/', function($m) {
                return chr(hexdec($m[1]));
            }, $cn);
            return trim($cn);
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
     * Users in rpp-register OU should not have access
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
        
        // Admin groups always have access regardless of OU
        if ($this->isLdapAdminByData($ldapData)) {
            return true;
        }
        
        // Superuser groups have access
        if ($this->isSuperUserByData($ldapData)) {
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
     * 
     * @param array $ldapData
     * @return bool
     */
    private function isSuperUserByData($ldapData)
    {
        // Check memberof groups
        if (isset($ldapData['memberof'])) {
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
                
                // Method 1: Check against full DN (exact or substring match)
                foreach (self::SUPERUSER_GROUPS as $superGroup) {
                    $normalizedSuperGroup = trim($superGroup);
                    
                    // Exact match or substring match (case-insensitive)
                    if (stripos($normalizedGroup, $normalizedSuperGroup) !== false) {
                        return true;
                    }
                    
                    // Also check reverse (in case order is different)
                    if (stripos($normalizedSuperGroup, $normalizedGroup) !== false) {
                        return true;
                    }
                }
                
                // Method 2: Extract and compare CN only (most flexible)
                $cn = $this->extractCnFromDn($normalizedGroup);
                if ($cn && $this->cnInList($cn, self::SUPERUSER_GROUP_CNS)) {
                    return true;
                }
                
                // Method 3: Check if group contains the CN name anywhere (very flexible)
                foreach (self::SUPERUSER_GROUP_CNS as $superCn) {
                    if (stripos($normalizedGroup, $superCn) !== false) {
                        return true;
                    }
                }
            }
        }
        
        // Check distinguishedname for direct group membership
        if (isset($ldapData['distinguishedname'])) {
            $dn = trim($ldapData['distinguishedname']);
            foreach (self::SUPERUSER_GROUPS as $superGroup) {
                $normalizedSuperGroup = trim($superGroup);
                if (stripos($dn, $normalizedSuperGroup) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
