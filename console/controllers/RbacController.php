<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use common\components\PermissionManager;

/**
 * RBAC Management Console Controller
 * 
 * This controller provides commands to manage RBAC permissions and roles
 */
class RbacController extends Controller
{
    /**
     * Initialize RBAC system with default permissions and roles
     */
    public function actionInit()
    {
        $this->stdout("Initializing RBAC system with SQLite...\n");
        
        try {
            $permissionManager = new PermissionManager();
            $permissionManager->initializeRbac();
            
            $this->stdout("RBAC system initialized successfully!\n");
            $this->stdout("Using SQLite database: runtime/rbac.db\n");
            
            $this->stdout("\nCreated permissions:\n");
            $this->stdout("- " . PermissionManager::PERMISSION_AD_USER_VIEW . "\n");
            $this->stdout("- " . PermissionManager::PERMISSION_AD_USER_CREATE . "\n");
            $this->stdout("- " . PermissionManager::PERMISSION_AD_USER_UPDATE . "\n");
            $this->stdout("- " . PermissionManager::PERMISSION_AD_USER_DELETE . "\n");
            $this->stdout("- " . PermissionManager::PERMISSION_LDAP_USER_VIEW . "\n");
            $this->stdout("- " . PermissionManager::PERMISSION_LDAP_USER_CREATE . "\n");
            $this->stdout("- " . PermissionManager::PERMISSION_LDAP_USER_UPDATE . "\n");
            $this->stdout("- " . PermissionManager::PERMISSION_LDAP_USER_DELETE . "\n");
            
            $this->stdout("\nCreated roles:\n");
            $this->stdout("- " . PermissionManager::ROLE_ADMIN . " (with all permissions)\n");
            $this->stdout("- " . PermissionManager::ROLE_USER . " (with view permissions only)\n");
            $this->stdout("- " . PermissionManager::ROLE_GUEST . "\n");
            
        } catch (\Exception $e) {
            $this->stderr("Error initializing RBAC: " . $e->getMessage() . "\n");
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Assign admin role to a specific user
     * 
     * @param string $username The username to assign admin role to
     */
    public function actionAssignAdmin($username)
    {
        $this->stdout("Assigning admin role to user: $username\n");
        
        try {
            $authManager = Yii::$app->authManager;
            $adminRole = $authManager->getRole(PermissionManager::ROLE_ADMIN);
            
            if (!$adminRole) {
                $this->stderr("Admin role not found. Please run 'php yii rbac/init' first.\n");
                return 1;
            }
            
            // Remove existing assignments
            $authManager->revokeAll($username);
            
            // Assign admin role
            $authManager->assign($adminRole, $username);
            
            $this->stdout("Admin role assigned successfully to: $username\n");
            
        } catch (\Exception $e) {
            $this->stderr("Error assigning admin role: " . $e->getMessage() . "\n");
            return 1;
        }
        
        return 0;
    }
    
    /**
     * List all roles and permissions
     */
    public function actionList()
    {
        $this->stdout("RBAC Roles and Permissions (SQLite):\n\n");
        
        try {
            $authManager = Yii::$app->authManager;
            
            // List roles
            $this->stdout("ROLES:\n");
            $roles = $authManager->getRoles();
            foreach ($roles as $role) {
                $this->stdout("- {$role->name}: {$role->description}\n");
            }
            
            $this->stdout("\nPERMISSIONS:\n");
            $permissions = $authManager->getPermissions();
            foreach ($permissions as $permission) {
                $this->stdout("- {$permission->name}: {$permission->description}\n");
            }
            
            $this->stdout("\nROLE-PERMISSION ASSIGNMENTS:\n");
            foreach ($roles as $role) {
                $this->stdout("\nRole: {$role->name}\n");
                $permissions = $authManager->getPermissionsByRole($role->name);
                foreach ($permissions as $permission) {
                    $this->stdout("  - {$permission->name}\n");
                }
            }
            
            $this->stdout("\nDATABASE LOCATION:\n");
            $this->stdout("- SQLite: runtime/rbac.db\n");
            
        } catch (\Exception $e) {
            $this->stderr("Error listing RBAC data: " . $e->getMessage() . "\n");
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Clear all RBAC data
     */
    public function actionClear()
    {
        $this->stdout("Clearing all RBAC data (SQLite)...\n");
        
        if (!$this->confirm("Are you sure you want to clear all RBAC data?")) {
            $this->stdout("Operation cancelled.\n");
            return 0;
        }
        
        try {
            $authManager = Yii::$app->authManager;
            
            // Remove all assignments
            $assignments = $authManager->getAssignments(null);
            foreach ($assignments as $userId => $userAssignments) {
                $authManager->revokeAll($userId);
            }
            
            // Remove all roles
            $roles = $authManager->getRoles();
            foreach ($roles as $role) {
                $authManager->remove($role);
            }
            
            // Remove all permissions
            $permissions = $authManager->getPermissions();
            foreach ($permissions as $permission) {
                $authManager->remove($permission);
            }
            
            $this->stdout("All RBAC data cleared successfully.\n");
            
        } catch (\Exception $e) {
            $this->stderr("Error clearing RBAC data: " . $e->getMessage() . "\n");
            return 1;
        }
        
        return 0;
    }
}
