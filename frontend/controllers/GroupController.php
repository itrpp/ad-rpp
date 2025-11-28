<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use common\components\LdapHelper;
use common\components\PermissionManager;

class GroupController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            $pm = new PermissionManager();
                            return $pm->hasPermission(PermissionManager::PERMISSION_GROUP_VIEW);
                        }
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'create' => ['post'],
                    'update' => ['post'],
                    'add-member' => ['post'],
                    'remove-member' => ['post'],
                ],
            ],
        ];
    }

    // Base DN for the Users Organizational Unit holding groups
    private function getUserContainerDn(): string
    {
        // OU=Users-RPP,DC=rpphosp,DC=local
        $domainDn = Yii::$app->params['ldap']['base_dn'] ?? 'DC=rpphosp,DC=local';
        return 'OU=Users-RPP,' . $domainDn;
    }

    public function actionIndex()
    {
        $pm = new PermissionManager();
        if (!$pm->hasPermission(PermissionManager::PERMISSION_GROUP_VIEW)) {
            Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ดูรายการกลุ่ม');
            return $this->redirect(['site/index']);
        }

        $ldap = new LdapHelper();
        $conn = $ldap->getConnection();
        $groups = [];
        if ($conn) {
            $base = $this->getUserContainerDn();
            $filter = '(objectClass=group)';
            // ดึง groupType เพื่อกรองเฉพาะ Security Group - Global
            $attrs = ['cn', 'description', 'member', 'groupType'];
            $sr = @ldap_list($conn, $base, $filter, $attrs);
            if ($sr) {
                $entries = ldap_get_entries($conn, $sr);
                // Security Group - Global = 0x80000002 = -2147483646
                $securityGroupGlobalType = -2147483646;
                for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
                    $e = $entries[$i];
                    // ตรวจสอบ groupType - LDAP มักจะ return attribute names เป็น lowercase
                    $groupType = null;
                    if (isset($e['grouptype']) && is_array($e['grouptype'])) {
                        $groupType = isset($e['grouptype'][0]) ? intval($e['grouptype'][0]) : null;
                    } elseif (isset($e['groupType']) && is_array($e['groupType'])) {
                        // รองรับกรณี uppercase (ไม่ค่อยพบ)
                        $groupType = isset($e['groupType'][0]) ? intval($e['groupType'][0]) : null;
                    }
                    
                    // กรองเฉพาะ Security Group - Global (groupType = -2147483646 หรือ 0x80000002)
                    // ถ้าไม่มี groupType attribute ให้ข้าม (ไม่แสดง)
                    if ($groupType !== null && $groupType === $securityGroupGlobalType) {
                        $cn = $e['cn'][0] ?? '';
                        
                        // ไม่แสดง group "manage Ad_admin" ในรายการ
                        if (strcasecmp(trim($cn), 'manage Ad_admin') === 0) {
                            continue;
                        }
                        
                        $groups[] = [
                            'dn' => $e['dn'] ?? '',
                            'cn' => $cn,
                            'description' => $e['description'][0] ?? '',
                            'member_count' => isset($e['member']) ? max(($e['member']['count'] ?? 0), 0) : 0,
                        ];
                    }
                }
            }
        }

        return $this->render('index', [
            'groups' => $groups,
        ]);
    }

    public function actionCreate()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $pm = new PermissionManager();
        if (!$pm->hasPermission(PermissionManager::PERMISSION_GROUP_CREATE)) {
            return ['success' => false, 'message' => 'No permission'];
        }

        $cn = trim(Yii::$app->request->post('cn', ''));
        $description = trim(Yii::$app->request->post('description', ''));
        
        // Validate required fields
        if ($cn === '') {
            return ['success' => false, 'message' => 'Group Name is required'];
        }

        // Validate CN format (should not contain special characters that need escaping)
        if (preg_match('/[<>"\\/\\\\;,]/', $cn)) {
            return ['success' => false, 'message' => 'Group Name contains invalid characters'];
        }

        // Generate sAMAccountName from CN
        // Rules: 
        // - Remove spaces and special characters (keep only alphanumeric, underscore, hyphen)
        // - Maximum 20 characters
        // - Must be unique
        $samAccountName = preg_replace('/[^a-zA-Z0-9_-]/', '', $cn); // Remove invalid characters
        $samAccountName = substr($samAccountName, 0, 20); // Truncate to 20 characters
        
        // Validate sAMAccountName is not empty after cleaning
        if (empty($samAccountName)) {
            return ['success' => false, 'message' => 'Group Name must contain at least one alphanumeric character'];
        }

        $ldap = new LdapHelper();
        $conn = $ldap->getConnection();
        if (!$conn) {
            return ['success' => false, 'message' => 'LDAP connection failed'];
        }

        $baseDn = $this->getUserContainerDn();
        $dn = 'CN=' . ldap_escape($cn, '', LDAP_ESCAPE_DN) . ',' . $baseDn;
        
        // Check if group already exists
        $existingSr = @ldap_read($conn, $dn, '(objectClass=group)', ['cn']);
        if ($existingSr) {
            $existingEntries = ldap_get_entries($conn, $existingSr);
            if (isset($existingEntries[0]) && $existingEntries['count'] > 0) {
                return ['success' => false, 'message' => 'Group Name already exists. Please choose a different name.'];
            }
        }
        
        // Also check by searching for groups with the same CN
        $filter = '(&(objectClass=group)(cn=' . ldap_escape($cn, '', LDAP_ESCAPE_FILTER) . '))';
        $searchSr = @ldap_search($conn, $baseDn, $filter, ['cn']);
        if ($searchSr) {
            $searchEntries = ldap_get_entries($conn, $searchSr);
            if (isset($searchEntries['count']) && $searchEntries['count'] > 0) {
                return ['success' => false, 'message' => 'Group Name already exists. Please choose a different name.'];
            }
        }

        // Check if sAMAccountName already exists (must be unique across entire domain)
        // Search in the entire domain base DN, not just CN=Users
        $domainBaseDn = Yii::$app->params['ldap']['base_dn'] ?? 'DC=rpphosp,DC=local';
        $samFilter = '(&(objectClass=group)(sAMAccountName=' . ldap_escape($samAccountName, '', LDAP_ESCAPE_FILTER) . '))';
        $samSearchSr = @ldap_search($conn, $domainBaseDn, $samFilter, ['sAMAccountName', 'cn']);
        if ($samSearchSr) {
            $samSearchEntries = ldap_get_entries($conn, $samSearchSr);
            if (isset($samSearchEntries['count']) && $samSearchEntries['count'] > 0) {
                return ['success' => false, 'message' => 'A group with this name already exists (sAMAccountName conflict). Please choose a different name.'];
            }
        }

        // Create group entry with required attributes
        $entry = [
            'objectClass' => ['top', 'group'], // Required Object Classes for Active Directory Group
            'cn' => [$cn], // Group Name (Common Name)
            'sAMAccountName' => [$samAccountName], // Pre-Windows 2000 Group Name (automatically generated from CN)
            'description' => [$description], // Description
            // Security Group - Global = 0x80000002 (automatically assigned)
            'groupType' => [strval(0x80000002)],
        ];
        
        Yii::debug("Creating group with DN: $dn");
        Yii::debug("Group entry: " . print_r($entry, true));
        
        $ok = @ldap_add($conn, $dn, $entry);
        if (!$ok) {
            $error = ldap_error($conn);
            $errno = ldap_errno($conn);
            Yii::error("Failed to create group: $error (Error code: $errno)");
            
            // Provide more specific error messages
            $errorMessages = [
                68 => 'Group already exists',
                34 => 'Invalid Group Name format',
                50 => 'Insufficient permissions to create group',
                53 => 'Server is unwilling to perform - Check OU permissions',
            ];
            
            $errorMsg = $errorMessages[$errno] ?? $error;
            return ['success' => false, 'message' => 'Create failed: ' . $errorMsg . ' (Error code: ' . $errno . ')'];
        }
        
        Yii::debug("Successfully created group: $dn");
        return ['success' => true, 'message' => 'Group created successfully'];
    }

    public function actionUpdate()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $pm = new PermissionManager();
        if (!$pm->hasPermission(PermissionManager::PERMISSION_GROUP_UPDATE)) {
            return ['success' => false, 'message' => 'No permission'];
        }
        $dn = Yii::$app->request->post('dn', '');
        $description = trim(Yii::$app->request->post('description', ''));
        if ($dn === '') {
            return ['success' => false, 'message' => 'DN is required'];
        }
        $ldap = new LdapHelper();
        $conn = $ldap->getConnection();
        if (!$conn) {
            return ['success' => false, 'message' => 'LDAP connection failed'];
        }
        $mods = [
            'description' => [$description],
        ];
        $ok = @ldap_modify($conn, $dn, $mods);
        if (!$ok) {
            return ['success' => false, 'message' => 'Update failed: ' . ldap_error($conn)];
        }
        return ['success' => true];
    }

    public function actionDelete()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $pm = new PermissionManager();
        if (!$pm->hasPermission(PermissionManager::PERMISSION_GROUP_DELETE)) {
            return ['success' => false, 'message' => 'No permission'];
        }
        $dn = Yii::$app->request->post('dn', '');
        if ($dn === '') {
            return ['success' => false, 'message' => 'DN is required'];
        }
        $ldap = new LdapHelper();
        $conn = $ldap->getConnection();
        if (!$conn) {
            return ['success' => false, 'message' => 'LDAP connection failed'];
        }
        $ok = @ldap_delete($conn, $dn);
        if (!$ok) {
            return ['success' => false, 'message' => 'Delete failed: ' . ldap_error($conn)];
        }
        return ['success' => true];
    }

    public function actionAddMember()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $pm = new PermissionManager();
        if (!$pm->hasPermission(PermissionManager::PERMISSION_GROUP_MANAGE_MEMBERS)) {
            return ['success' => false, 'message' => 'No permission'];
        }
        
        $groupDn = trim(Yii::$app->request->post('groupDn', ''));
        $userDn = trim(Yii::$app->request->post('userDn', ''));
        
        if ($groupDn === '' || $userDn === '') {
            return ['success' => false, 'message' => 'groupDn and userDn are required'];
        }
        
        // Log original DNs for debugging
        $originalGroupDn = $groupDn;
        $originalUserDn = $userDn;
        Yii::debug("Original groupDn (raw POST): " . $originalGroupDn);
        Yii::debug("Original userDn (raw POST): " . $originalUserDn);
        
        // FormData sends data as-is, but check if it's URL encoded
        // Try to decode if it looks encoded (contains %)
        if (strpos($groupDn, '%') !== false) {
            $decoded = urldecode($groupDn);
            // If decoding changed something and result looks like a DN, use it
            if ($decoded !== $groupDn && (strpos($decoded, 'CN=') !== false || strpos($decoded, 'OU=') !== false || strpos($decoded, 'DC=') !== false)) {
                $groupDn = $decoded;
            }
        }
        
        if (strpos($userDn, '%') !== false) {
            $decoded = urldecode($userDn);
            // If decoding changed something and result looks like a DN, use it
            if ($decoded !== $userDn && (strpos($decoded, 'CN=') !== false || strpos($decoded, 'OU=') !== false || strpos($decoded, 'DC=') !== false)) {
                $userDn = $decoded;
            }
        }
        
        // Clean up any extra whitespace
        $groupDn = trim($groupDn);
        $userDn = trim($userDn);
        
        // Log cleaned DNs for debugging
        Yii::debug("Cleaned groupDn: $groupDn");
        Yii::debug("Cleaned userDn: $userDn");
        
        // Basic validation - DN should not be empty
        if (empty($groupDn) || empty($userDn)) {
            Yii::error("Empty DN - groupDn: '$groupDn', userDn: '$userDn'");
            return ['success' => false, 'message' => 'DN cannot be empty'];
        }
        
        // Additional validation - DN should contain at least one of CN=, OU=, or DC=
        if (strpos($groupDn, 'CN=') === false && strpos($groupDn, 'OU=') === false && strpos($groupDn, 'DC=') === false) {
            Yii::error("Group DN does not contain valid DN components: $groupDn");
            return ['success' => false, 'message' => 'Invalid group DN format: DN must contain CN=, OU=, or DC='];
        }
        
        if (strpos($userDn, 'CN=') === false && strpos($userDn, 'OU=') === false && strpos($userDn, 'DC=') === false) {
            Yii::error("User DN does not contain valid DN components: $userDn");
            return ['success' => false, 'message' => 'Invalid user DN format: DN must contain CN=, OU=, or DC='];
        }
        
        Yii::debug("Adding member to group - Group DN: $groupDn, User DN: $userDn");
        
        $ldap = new LdapHelper();
        $conn = $ldap->getConnection();
        if (!$conn) {
            return ['success' => false, 'message' => 'LDAP connection failed'];
        }
        
        try {
            // Get current user for audit logging
            $currentUser = Yii::$app->user->identity;
            $currentUsername = $currentUser ? ($currentUser->username ?? 'unknown') : 'unknown';
            $currentUserDisplayName = $currentUser ? ($currentUser->displayName ?? $currentUsername) : 'unknown';
            
            // Verify that the user exists before adding
            $userAttrs = ['cn', 'displayname', 'samaccountname', 'distinguishedname'];
            $userSr = @ldap_read($conn, $userDn, '(objectClass=*)', $userAttrs);
            if (!$userSr) {
                $error = ldap_error($conn);
                Yii::error("User DN not found: $userDn - Error: $error");
                
                // Audit log: Failed attempt
                Yii::info("AUDIT: Add Member Failed - User not found. Group: $groupDn, User DN: $userDn, Performed by: $currentUserDisplayName ($currentUsername), Error: $error", 'audit');
                
                return ['success' => false, 'message' => 'User not found: ' . $error];
            }
            
            // Get user details for audit logging
            $userEntries = ldap_get_entries($conn, $userSr);
            $userCn = isset($userEntries[0]['cn'][0]) ? $userEntries[0]['cn'][0] : '';
            $userDisplayName = isset($userEntries[0]['displayname'][0]) ? $userEntries[0]['displayname'][0] : $userCn;
            $userSamAccountName = isset($userEntries[0]['samaccountname'][0]) ? $userEntries[0]['samaccountname'][0] : '';
            
            // Verify that the group exists
            $groupAttrs = ['dn', 'member', 'cn', 'description'];
            $groupSr = @ldap_read($conn, $groupDn, '(objectClass=group)', $groupAttrs);
            if (!$groupSr) {
                $error = ldap_error($conn);
                Yii::error("Group DN not found: $groupDn - Error: $error");
                
                // Audit log: Failed attempt
                Yii::info("AUDIT: Add Member Failed - Group not found. Group DN: $groupDn, User: $userDisplayName ($userSamAccountName), Performed by: $currentUserDisplayName ($currentUsername), Error: $error", 'audit');
                
                return ['success' => false, 'message' => 'Group not found: ' . $error];
            }
            
            // Get group details for audit logging
            $groupEntries = ldap_get_entries($conn, $groupSr);
            $groupCn = isset($groupEntries[0]['cn'][0]) ? $groupEntries[0]['cn'][0] : '';
            $groupDescription = isset($groupEntries[0]['description'][0]) ? $groupEntries[0]['description'][0] : '';
            
            // Check if user is already a member (duplicate validation)
            $isDuplicate = false;
            if (isset($groupEntries[0]['member'])) {
                $members = $groupEntries[0]['member'];
                $memberCount = isset($members['count']) ? intval($members['count']) : 0;
                for ($i = 0; $i < $memberCount; $i++) {
                    if (isset($members[$i]) && strcasecmp(trim($members[$i]), trim($userDn)) === 0) {
                        $isDuplicate = true;
                        break;
                    }
                }
            }
            
            if ($isDuplicate) {
                // Audit log: Duplicate attempt
                Yii::info("AUDIT: Add Member Failed - Duplicate member. Group: $groupCn ($groupDn), User: $userDisplayName ($userSamAccountName, $userDn), Performed by: $currentUserDisplayName ($currentUsername)", 'audit');
                
                return ['success' => false, 'message' => 'User is already a member of this group'];
            }
            
            // Add member to group
            $entry = ['member' => [$userDn]];
            Yii::debug("Adding member with entry: " . print_r($entry, true));
            
            $ok = @ldap_mod_add($conn, $groupDn, $entry);
            if (!$ok) {
                $error = ldap_error($conn);
                $errno = ldap_errno($conn);
                Yii::error("Failed to add member - Error: $error (Code: $errno)");
                Yii::error("Group DN: $groupDn");
                Yii::error("User DN: $userDn");
                
                // Audit log: Failed attempt
                Yii::info("AUDIT: Add Member Failed - LDAP Error. Group: $groupCn ($groupDn), User: $userDisplayName ($userSamAccountName, $userDn), Performed by: $currentUserDisplayName ($currentUsername), Error: $error (Code: $errno)", 'audit');
                
                // Provide more specific error messages
                $errorMessages = [
                    68 => 'Object already exists (user may already be a member)',
                    34 => 'Invalid DN syntax',
                    50 => 'Insufficient access rights',
                    53 => 'Server is unwilling to perform',
                ];
                
                $errorMsg = $errorMessages[$errno] ?? $error;
                return ['success' => false, 'message' => 'Add member failed: ' . $errorMsg . ' (Error code: ' . $errno . ')'];
            }
            
            // Audit log: Success
            Yii::info("AUDIT: Add Member Success - Group: $groupCn ($groupDn), User: $userDisplayName ($userSamAccountName, $userDn), Performed by: $currentUserDisplayName ($currentUsername), Timestamp: " . date('Y-m-d H:i:s'), 'audit');
            
            Yii::debug("Successfully added member to group");
            return [
                'success' => true, 
                'message' => 'Member added successfully.',
                'user' => [
                    'cn' => $userCn,
                    'displayName' => $userDisplayName,
                    'samAccountName' => $userSamAccountName,
                ],
                'group' => [
                    'cn' => $groupCn,
                    'description' => $groupDescription,
                ]
            ];
        } catch (\Exception $e) {
            Yii::error("Exception in actionAddMember: " . $e->getMessage());
            Yii::error("Stack trace: " . $e->getTraceAsString());
            
            // Audit log: Exception
            $currentUser = Yii::$app->user->identity;
            $currentUsername = $currentUser ? ($currentUser->username ?? 'unknown') : 'unknown';
            $currentUserDisplayName = $currentUser ? ($currentUser->displayName ?? $currentUsername) : 'unknown';
            Yii::info("AUDIT: Add Member Failed - Exception. Group DN: $groupDn, User DN: $userDn, Performed by: $currentUserDisplayName ($currentUsername), Exception: " . $e->getMessage(), 'audit');
            
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function actionRemoveMember()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $pm = new PermissionManager();
        if (!$pm->hasPermission(PermissionManager::PERMISSION_GROUP_MANAGE_MEMBERS)) {
            return ['success' => false, 'message' => 'No permission'];
        }
        
        $groupDn = trim(Yii::$app->request->post('groupDn', ''));
        $userDn = trim(Yii::$app->request->post('userDn', ''));
        
        if ($groupDn === '' || $userDn === '') {
            return ['success' => false, 'message' => 'groupDn and userDn are required'];
        }
        
        // Log original DNs for debugging
        $originalGroupDn = $groupDn;
        $originalUserDn = $userDn;
        Yii::debug("Original groupDn (raw POST): " . $originalGroupDn);
        Yii::debug("Original userDn (raw POST): " . $originalUserDn);
        
        // FormData sends data as-is, but check if it's URL encoded
        // Try to decode if it looks encoded (contains %)
        if (strpos($groupDn, '%') !== false) {
            $decoded = urldecode($groupDn);
            // If decoding changed something and result looks like a DN, use it
            if ($decoded !== $groupDn && (strpos($decoded, 'CN=') !== false || strpos($decoded, 'OU=') !== false || strpos($decoded, 'DC=') !== false)) {
                $groupDn = $decoded;
            }
        }
        
        if (strpos($userDn, '%') !== false) {
            $decoded = urldecode($userDn);
            // If decoding changed something and result looks like a DN, use it
            if ($decoded !== $userDn && (strpos($decoded, 'CN=') !== false || strpos($decoded, 'OU=') !== false || strpos($decoded, 'DC=') !== false)) {
                $userDn = $decoded;
            }
        }
        
        // Clean up any extra whitespace
        $groupDn = trim($groupDn);
        $userDn = trim($userDn);
        
        // Log cleaned DNs for debugging
        Yii::debug("Cleaned groupDn: $groupDn");
        Yii::debug("Cleaned userDn: $userDn");
        
        // Basic validation - DN should not be empty
        if (empty($groupDn) || empty($userDn)) {
            Yii::error("Empty DN - groupDn: '$groupDn', userDn: '$userDn'");
            return ['success' => false, 'message' => 'DN cannot be empty'];
        }
        
        // Additional validation - DN should contain at least one of CN=, OU=, or DC=
        if (strpos($groupDn, 'CN=') === false && strpos($groupDn, 'OU=') === false && strpos($groupDn, 'DC=') === false) {
            Yii::error("Group DN does not contain valid DN components: $groupDn");
            return ['success' => false, 'message' => 'Invalid group DN format: DN must contain CN=, OU=, or DC='];
        }
        
        if (strpos($userDn, 'CN=') === false && strpos($userDn, 'OU=') === false && strpos($userDn, 'DC=') === false) {
            Yii::error("User DN does not contain valid DN components: $userDn");
            return ['success' => false, 'message' => 'Invalid user DN format: DN must contain CN=, OU=, or DC='];
        }
        
        Yii::debug("Removing member from group - Group DN: $groupDn, User DN: $userDn");
        
        $ldap = new LdapHelper();
        $conn = $ldap->getConnection();
        if (!$conn) {
            return ['success' => false, 'message' => 'LDAP connection failed'];
        }
        
        try {
            // Verify that the group exists
            $groupSr = @ldap_read($conn, $groupDn, '(objectClass=group)', ['dn', 'member']);
            if (!$groupSr) {
                $error = ldap_error($conn);
                Yii::error("Group DN not found: $groupDn - Error: $error");
                return ['success' => false, 'message' => 'Group not found: ' . $error];
            }
            
            // Remove member from group
        $entry = ['member' => [$userDn]];
            Yii::debug("Removing member with entry: " . print_r($entry, true));
            
        $ok = @ldap_mod_del($conn, $groupDn, $entry);
        if (!$ok) {
                $error = ldap_error($conn);
                $errno = ldap_errno($conn);
                Yii::error("Failed to remove member - Error: $error (Code: $errno)");
                Yii::error("Group DN: $groupDn");
                Yii::error("User DN: $userDn");
                
                // Provide more specific error messages
                $errorMessages = [
                    16 => 'No such attribute (user may not be a member)',
                    34 => 'Invalid DN syntax',
                    50 => 'Insufficient access rights',
                    53 => 'Server is unwilling to perform',
                ];
                
                $errorMsg = $errorMessages[$errno] ?? $error;
                return ['success' => false, 'message' => 'Remove member failed: ' . $errorMsg . ' (Error code: ' . $errno . ')'];
            }
            
            Yii::debug("Successfully removed member from group");
            return ['success' => true, 'message' => 'Member removed successfully'];
        } catch (\Exception $e) {
            Yii::error("Exception in actionRemoveMember: " . $e->getMessage());
            Yii::error("Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * ดึงรายชื่อสมาชิกของกลุ่ม
     */
    public function actionGetMembers()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $pm = new PermissionManager();
        if (!$pm->hasPermission(PermissionManager::PERMISSION_GROUP_VIEW)) {
            return ['success' => false, 'message' => 'No permission', 'members' => []];
        }

        $groupDn = Yii::$app->request->get('groupDn', '');
        if ($groupDn === '') {
            return ['success' => false, 'message' => 'groupDn is required', 'members' => []];
        }

        $ldap = new LdapHelper();
        $conn = $ldap->getConnection();
        if (!$conn) {
            return ['success' => false, 'message' => 'LDAP connection failed', 'members' => []];
        }

        $members = [];
        try {
            Yii::debug("Getting members for group: $groupDn");
            
            // ดึงข้อมูลกลุ่มพร้อม member attribute
            $attrs = ['member', 'cn'];
            $sr = @ldap_read($conn, $groupDn, '(objectClass=group)', $attrs);
            
            if (!$sr) {
                $error = ldap_error($conn);
                $errno = ldap_errno($conn);
                Yii::error("Failed to read group: $error (Error code: $errno)");
                return ['success' => false, 'message' => 'Failed to read group: ' . $error, 'members' => []];
            }
            
            $entries = ldap_get_entries($conn, $sr);
            Yii::debug("LDAP entries structure: " . print_r(array_keys($entries), true));
            
            if (!isset($entries[0])) {
                Yii::error("Group entry not found in LDAP result");
                return ['success' => false, 'message' => 'Group not found', 'members' => []];
            }
            
            $groupEntry = $entries[0];
            Yii::debug("Group entry keys: " . print_r(array_keys($groupEntry), true));
            
            // ตรวจสอบ member attribute - LDAP มัก return เป็น lowercase
            $memberDns = null;
            $memberCount = 0;
            
            // ตรวจสอบหลายรูปแบบ
            if (isset($groupEntry['member']) && is_array($groupEntry['member'])) {
                $memberDns = $groupEntry['member'];
                $memberCount = isset($memberDns['count']) ? intval($memberDns['count']) : 0;
                Yii::debug("Found member attribute (lowercase) with count: $memberCount");
            } elseif (isset($groupEntry['Member']) && is_array($groupEntry['Member'])) {
                $memberDns = $groupEntry['Member'];
                $memberCount = isset($memberDns['count']) ? intval($memberDns['count']) : 0;
                Yii::debug("Found Member attribute (mixed case) with count: $memberCount");
            } else {
                // ตรวจสอบทุก key ที่มี 'member' ในชื่อ
                foreach ($groupEntry as $key => $value) {
                    if (stripos($key, 'member') !== false && is_array($value)) {
                        $memberDns = $value;
                        $memberCount = isset($memberDns['count']) ? intval($memberDns['count']) : 0;
                        Yii::debug("Found member attribute with key: $key, count: $memberCount");
                        break;
                    }
                }
            }
            
            if ($memberCount > 0 && $memberDns) {
                Yii::debug("Processing $memberCount members");
                
                // ดึงข้อมูลผู้ใช้แต่ละคน
                for ($i = 0; $i < $memberCount; $i++) {
                    if (!isset($memberDns[$i])) {
                        Yii::warning("Member index $i not found in memberDns array");
                        continue;
                    }
                    
                    $memberDn = $memberDns[$i];
                    if (empty($memberDn) || $memberDn === 'count') {
                        continue;
                    }
                    
                    Yii::debug("Processing member DN: $memberDn");
                    
                    try {
                        // ใช้ ldap_read โดยตรงด้วย DN - เพิ่ม department attribute
                        $userAttrs = ['cn', 'displayname', 'samaccountname', 'mail', 'distinguishedname', 'department'];
                        $userSr = @ldap_read($conn, $memberDn, '(objectClass=*)', $userAttrs);
                        
                        if ($userSr) {
                            $userEntries = ldap_get_entries($conn, $userSr);
                            if (isset($userEntries[0]) && $userEntries['count'] > 0) {
                                $user = $userEntries[0];
                                $members[] = [
                                    'dn' => $memberDn,
                                    'cn' => isset($user['cn'][0]) ? $user['cn'][0] : '',
                                    'displayName' => isset($user['displayname'][0]) ? $user['displayname'][0] : (isset($user['cn'][0]) ? $user['cn'][0] : $memberDn),
                                    'samAccountName' => isset($user['samaccountname'][0]) ? $user['samaccountname'][0] : '',
                                    'mail' => isset($user['mail'][0]) ? $user['mail'][0] : '',
                                    'department' => isset($user['department'][0]) ? $user['department'][0] : '',
                                ];
                                Yii::debug("Successfully loaded member: " . ($user['displayname'][0] ?? $user['cn'][0] ?? $memberDn));
                            } else {
                                // ถ้าไม่พบข้อมูลผู้ใช้ ให้แสดง DN เท่านั้น
                                $members[] = [
                                    'dn' => $memberDn,
                                    'cn' => $memberDn,
                                    'displayName' => $memberDn,
                                    'samAccountName' => '',
                                    'mail' => '',
                                    'department' => '',
                                ];
                                Yii::warning("Could not read user details for DN: $memberDn");
                            }
                        } else {
                            // ถ้า ldap_read ไม่ได้ ลองใช้ search
                            $userFilter = '(distinguishedName=' . ldap_escape($memberDn, '', LDAP_ESCAPE_FILTER) . ')';
                            $baseDn = Yii::$app->params['ldap']['base_dn'];
                            $userSr = @ldap_search($conn, $baseDn, $userFilter, $userAttrs);
                            
                            if ($userSr) {
                                $userEntries = ldap_get_entries($conn, $userSr);
                                if (isset($userEntries[0]) && $userEntries['count'] > 0) {
                                    $user = $userEntries[0];
                                    $members[] = [
                                        'dn' => $memberDn,
                                        'cn' => isset($user['cn'][0]) ? $user['cn'][0] : '',
                                        'displayName' => isset($user['displayname'][0]) ? $user['displayname'][0] : (isset($user['cn'][0]) ? $user['cn'][0] : $memberDn),
                                        'samAccountName' => isset($user['samaccountname'][0]) ? $user['samaccountname'][0] : '',
                                        'mail' => isset($user['mail'][0]) ? $user['mail'][0] : '',
                                        'department' => isset($user['department'][0]) ? $user['department'][0] : '',
                                    ];
                                } else {
                                    $members[] = [
                                        'dn' => $memberDn,
                                        'cn' => $memberDn,
                                        'displayName' => $memberDn,
                                        'samAccountName' => '',
                                        'mail' => '',
                                        'department' => '',
                                    ];
                                }
                            } else {
                                // ถ้าไม่สามารถดึงข้อมูลได้ ให้แสดง DN เท่านั้น
                                $members[] = [
                                    'dn' => $memberDn,
                                    'cn' => $memberDn,
                                    'displayName' => $memberDn,
                                    'samAccountName' => '',
                                    'mail' => '',
                                    'department' => '',
                                ];
                                Yii::warning("Could not find user with DN: $memberDn");
                            }
                        }
                    } catch (\Exception $e) {
                        Yii::error("Error processing member $memberDn: " . $e->getMessage());
                                // เพิ่ม member แม้จะดึงข้อมูลไม่ได้
                                $members[] = [
                                    'dn' => $memberDn,
                                    'cn' => $memberDn,
                                    'displayName' => $memberDn,
                                    'samAccountName' => '',
                                    'mail' => '',
                                    'department' => '',
                                ];
                    }
                }
                
                Yii::debug("Successfully loaded " . count($members) . " members");
            } else {
                // กลุ่มไม่มีสมาชิก
                Yii::debug("Group has no members (memberCount: $memberCount)");
                return ['success' => true, 'members' => []];
            }
        } catch (\Exception $e) {
            Yii::error("Error in actionGetMembers: " . $e->getMessage());
            Yii::error("Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'members' => []];
        }

        return ['success' => true, 'members' => $members];
    }

    /**
     * ค้นหาผู้ใช้เพื่อเลือกเพิ่มเป็นสมาชิก
     */
    public function actionSearchUsers()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $pm = new PermissionManager();
        if (!$pm->hasPermission(PermissionManager::PERMISSION_GROUP_MANAGE_MEMBERS)) {
            return ['success' => false, 'message' => 'No permission', 'users' => []];
        }

        $searchTerm = trim(Yii::$app->request->get('q', ''));
        
        try {
            $ldap = new LdapHelper();
            
            // ใช้ searchUsers จาก LdapHelper
            $users = $ldap->searchUsers('rpp-user', $searchTerm);
            
            // แปลงรูปแบบข้อมูลให้เหมาะสม
            $result = [];
            foreach ($users as $user) {
                // ตรวจสอบว่า user เป็น array และมีข้อมูล
                if (!is_array($user)) {
                    continue;
                }
                
                // ดึง distinguishedname - อาจเป็น array หรือ string
                $dn = '';
                if (isset($user['distinguishedname'])) {
                    if (is_array($user['distinguishedname'])) {
                        $dn = isset($user['distinguishedname'][0]) ? $user['distinguishedname'][0] : '';
                    } else {
                        $dn = $user['distinguishedname'];
                    }
                } elseif (isset($user['distinguishedName'])) {
                    if (is_array($user['distinguishedName'])) {
                        $dn = isset($user['distinguishedName'][0]) ? $user['distinguishedName'][0] : '';
                    } else {
                        $dn = $user['distinguishedName'];
                    }
                }
                
                if (empty($dn)) {
                    continue;
                }
                
                // ดึงข้อมูลอื่นๆ - ตรวจสอบทั้ง lowercase และ mixed case
                $cn = '';
                if (isset($user['cn'])) {
                    $cn = is_array($user['cn']) ? (isset($user['cn'][0]) ? $user['cn'][0] : '') : $user['cn'];
                }
                
                $displayName = '';
                if (isset($user['displayname'])) {
                    $displayName = is_array($user['displayname']) ? (isset($user['displayname'][0]) ? $user['displayname'][0] : '') : $user['displayname'];
                } elseif (isset($user['displayName'])) {
                    $displayName = is_array($user['displayName']) ? (isset($user['displayName'][0]) ? $user['displayName'][0] : '') : $user['displayName'];
                }
                if (empty($displayName)) {
                    $displayName = $cn;
                }
                
                $samAccountName = '';
                if (isset($user['samaccountname'])) {
                    $samAccountName = is_array($user['samaccountname']) ? (isset($user['samaccountname'][0]) ? $user['samaccountname'][0] : '') : $user['samaccountname'];
                } elseif (isset($user['sAMAccountName'])) {
                    $samAccountName = is_array($user['sAMAccountName']) ? (isset($user['sAMAccountName'][0]) ? $user['sAMAccountName'][0] : '') : $user['sAMAccountName'];
                }
                
                $mail = '';
                if (isset($user['mail'])) {
                    $mail = is_array($user['mail']) ? (isset($user['mail'][0]) ? $user['mail'][0] : '') : $user['mail'];
                }
                
                $department = '';
                if (isset($user['department'])) {
                    $department = is_array($user['department']) ? (isset($user['department'][0]) ? $user['department'][0] : '') : $user['department'];
                }
                
                $result[] = [
                    'dn' => $dn,
                    'cn' => $cn,
                    'displayName' => $displayName,
                    'samAccountName' => $samAccountName,
                    'mail' => $mail,
                    'department' => $department,
                ];
            }

            return ['success' => true, 'users' => $result];
        } catch (\Exception $e) {
            Yii::error("Error in actionSearchUsers: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'users' => []];
        }
    }

    /**
     * ดึงรายชื่อ Organizational Units (OU)
     */
    public function actionGetOus()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $pm = new PermissionManager();
        if (!$pm->hasPermission(PermissionManager::PERMISSION_GROUP_MANAGE_MEMBERS)) {
            return ['success' => false, 'message' => 'No permission', 'ous' => []];
        }

        try {
            $ldap = new LdapHelper();
            
            // ใช้ getAllOUs จาก LdapHelper
            $ous = $ldap->getAllOUs();
            
            // แปลงรูปแบบข้อมูลให้เหมาะสม
            $result = [];
            foreach ($ous as $ou) {
                if (!is_array($ou)) {
                    continue;
                }

                // ข้าม OU ที่เป็น sub ของ "ฝ่ายการพยาบาล" (แต่ไม่ข้าม OU หลักเอง)
                // ตัวอย่าง DN ของ sub: OU=xxx,OU=ฝ่ายการพยาบาล,DC=...
                // ตรวจด้วยการมี ",OU=ฝ่ายการพยาบาล," อยู่ภายใน DN
                $dn = $ou['dn'] ?? '';
                if (is_string($dn) && stripos($dn, ',OU=ฝ่ายการพยาบาล,') !== false) {
                    // เป็น descendant ของ ฝ่ายการพยาบาล ให้ข้าม
                    continue;
                }
                
                $ouName = $ou['ou'] ?? '';
                $description = $ou['description'] ?? '';
                $label = $ouName;
                if (!empty($description)) {
                    $label .= ' - ' . $description;
                }

                // นับจำนวนผู้ใช้ใน OU นี้เพื่อแสดงผลใน dropdown
                $userCount = 0;
                try {
                    if (!empty($ou['dn'])) {
                        $usersInOu = $ldap->getUsersByOu($ou['dn']);
                        $userCount = is_array($usersInOu) ? count($usersInOu) : 0;
                    }
                } catch (\Exception $e) {
                    Yii::warning("Count users in OU failed for DN {$ou['dn']}: " . $e->getMessage());
                    $userCount = 0;
                }
                
                $result[] = [
                    'dn' => $ou['dn'] ?? '',
                    'ou' => $ouName,
                    'description' => $description,
                    'label' => $label,
                    'user_count' => $userCount,
                ];
            }

            return ['success' => true, 'ous' => $result];
        } catch (\Exception $e) {
            Yii::error("Error in actionGetOus: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'ous' => []];
        }
    }

    /**
     * ดึงรายชื่อผู้ใช้ใน OU ที่เลือก
     */
    public function actionGetUsersByOu()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $pm = new PermissionManager();
        if (!$pm->hasPermission(PermissionManager::PERMISSION_GROUP_MANAGE_MEMBERS)) {
            return ['success' => false, 'message' => 'No permission', 'users' => []];
        }

        $ouDn = trim(Yii::$app->request->get('ouDn', ''));
        
        if ($ouDn === '') {
            return ['success' => false, 'message' => 'ouDn is required', 'users' => []];
        }

        // Decode URL-encoded DN if needed
        if (strpos($ouDn, '%') !== false) {
            $decoded = urldecode($ouDn);
            if ($decoded !== $ouDn && (strpos($decoded, 'OU=') !== false || strpos($decoded, 'CN=') !== false || strpos($decoded, 'DC=') !== false)) {
                $ouDn = $decoded;
            }
        }
        
        $ouDn = trim($ouDn);
        
        try {
            $ldap = new LdapHelper();
            
            // ใช้ getUsersByOu จาก LdapHelper
            $users = $ldap->getUsersByOu($ouDn);
            
            // แปลงรูปแบบข้อมูลให้เหมาะสม
            $result = [];
            foreach ($users as $user) {
                if (!is_array($user)) {
                    continue;
                }
                
                // ดึง distinguishedname
                $dn = '';
                if (isset($user['distinguishedname'])) {
                    if (is_array($user['distinguishedname'])) {
                        $dn = isset($user['distinguishedname'][0]) ? $user['distinguishedname'][0] : '';
                    } else {
                        $dn = $user['distinguishedname'];
                    }
                } elseif (isset($user['distinguishedName'])) {
                    if (is_array($user['distinguishedName'])) {
                        $dn = isset($user['distinguishedName'][0]) ? $user['distinguishedName'][0] : '';
                    } else {
                        $dn = $user['distinguishedName'];
                    }
                }
                
                if (empty($dn)) {
                    continue;
                }
                
                // ดึงข้อมูลอื่นๆ
                $cn = '';
                if (isset($user['cn'])) {
                    $cn = is_array($user['cn']) ? (isset($user['cn'][0]) ? $user['cn'][0] : '') : $user['cn'];
                }
                
                $displayName = '';
                if (isset($user['displayname'])) {
                    $displayName = is_array($user['displayname']) ? (isset($user['displayname'][0]) ? $user['displayname'][0] : '') : $user['displayname'];
                } elseif (isset($user['displayName'])) {
                    $displayName = is_array($user['displayName']) ? (isset($user['displayName'][0]) ? $user['displayName'][0] : '') : $user['displayName'];
                }
                if (empty($displayName)) {
                    $displayName = $cn;
                }
                
                $samAccountName = '';
                if (isset($user['samaccountname'])) {
                    $samAccountName = is_array($user['samaccountname']) ? (isset($user['samaccountname'][0]) ? $user['samaccountname'][0] : '') : $user['samaccountname'];
                } elseif (isset($user['sAMAccountName'])) {
                    $samAccountName = is_array($user['sAMAccountName']) ? (isset($user['sAMAccountName'][0]) ? $user['sAMAccountName'][0] : '') : $user['sAMAccountName'];
                }
                
                $mail = '';
                if (isset($user['mail'])) {
                    $mail = is_array($user['mail']) ? (isset($user['mail'][0]) ? $user['mail'][0] : '') : $user['mail'];
                }
                
                $department = '';
                if (isset($user['department'])) {
                    $department = is_array($user['department']) ? (isset($user['department'][0]) ? $user['department'][0] : '') : $user['department'];
                }
                
                $result[] = [
                    'dn' => $dn,
                    'cn' => $cn,
                    'displayName' => $displayName,
                    'samAccountName' => $samAccountName,
                    'mail' => $mail,
                    'department' => $department,
                ];
            }

            return ['success' => true, 'users' => $result];
        } catch (\Exception $e) {
            Yii::error("Error in actionGetUsersByOu: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'users' => []];
        }
    }
}


