<?php
/**
 * Debug Toggle Status Issues
 * This file helps debug toggle status problems
 */

// Include Yii framework
require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../../common/config/bootstrap.php');

// Create Yii application
$config = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/../../common/config/main.php'),
    require(__DIR__ . '/../../common/config/main-local.php'),
    require(__DIR__ . '/../../frontend/config/main.php'),
    require(__DIR__ . '/../../frontend/config/main-local.php')
);

$app = new yii\web\Application($config);

try {
    echo "=== Debug Toggle Status Issues ===\n\n";
    
    // Test LDAP connection
    $ldap = new \common\components\LdapHelper();
    if ($ldap->testConnection()) {
        echo "✓ LDAP Connection successful\n";
    } else {
        echo "✗ LDAP Connection failed\n";
        exit(1);
    }
    
    // Test setAccountStatus method
    if (method_exists($ldap, 'setAccountStatus')) {
        echo "✓ setAccountStatus method exists\n";
    } else {
        echo "✗ setAccountStatus method not found\n";
        exit(1);
    }
    
    // Test with a sample user (replace with actual user CN)
    $testCn = 'testuser'; // Replace with actual user CN
    echo "\nTesting with user: $testCn\n";
    
    // Check if user exists
    $user = $ldap->getUser($testCn);
    if ($user) {
        echo "✓ User found: " . ($user['displayname'] ?? $user['samaccountname'] ?? $testCn) . "\n";
        
        // Get current status
        $userAccountControl = isset($user['useraccountcontrol'][0]) ? intval($user['useraccountcontrol'][0]) : 0;
        $ACCOUNTDISABLE = 0x0002;
        $isDisabled = ($userAccountControl & $ACCOUNTDISABLE) ? true : false;
        echo "Current status: " . ($isDisabled ? 'Disabled' : 'Enabled') . "\n";
        
        // Test toggle (disable if enabled, enable if disabled)
        $newStatus = !$isDisabled;
        echo "Testing toggle to: " . ($newStatus ? 'Enabled' : 'Disabled') . "\n";
        
        $result = $ldap->setAccountStatus($testCn, $newStatus);
        if ($result) {
            echo "✓ Toggle successful\n";
            
            // Verify the change
            $updatedUser = $ldap->getUser($testCn);
            if ($updatedUser) {
                $updatedUserAccountControl = isset($updatedUser['useraccountcontrol'][0]) ? intval($updatedUser['useraccountcontrol'][0]) : 0;
                $updatedIsDisabled = ($updatedUserAccountControl & $ACCOUNTDISABLE) ? true : false;
                echo "Updated status: " . ($updatedIsDisabled ? 'Disabled' : 'Enabled') . "\n";
                
                if ($updatedIsDisabled === !$newStatus) {
                    echo "✓ Status change verified\n";
                } else {
                    echo "✗ Status change not reflected\n";
                }
            } else {
                echo "✗ Could not retrieve updated user data\n";
            }
        } else {
            echo "✗ Toggle failed\n";
        }
    } else {
        echo "✗ User not found: $testCn\n";
        echo "Available users (first 5):\n";
        
        // Get some users to test with
        $allUsers = $ldap->getAllUsers();
        $count = 0;
        foreach ($allUsers as $user) {
            if ($count >= 5) break;
            echo "- " . ($user['displayname'] ?? $user['samaccountname'] ?? 'Unknown') . " (CN: " . ($user['cn'] ?? 'N/A') . ")\n";
            $count++;
        }
    }
    
    echo "\n=== Debug Complete ===\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
