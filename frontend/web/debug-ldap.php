<?php
/**
 * Debug LDAP Connection and Data Retrieval
 * This file helps debug LDAP connection issues
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

$app = new yii\console\Application($config);

try {
    echo "=== LDAP Debug Information ===\n\n";
    
    // Test LDAP connection
    $ldap = new \common\components\LdapHelper();
    echo "✓ LDAP Helper initialized\n";
    
    // Test connection
    if ($ldap->testConnection()) {
        echo "✓ LDAP Connection successful\n";
    } else {
        echo "✗ LDAP Connection failed\n";
        exit(1);
    }
    
    // Test getting all OUs
    echo "\n=== Testing getAllOUs() ===\n";
    $allOUs = $ldap->getAllOUs();
    echo "Found " . count($allOUs) . " OUs:\n";
    foreach ($allOUs as $ou) {
        echo "- {$ou['ou']} ({$ou['type']}) - {$ou['dn']}\n";
    }
    
    // Test getting users from first OU
    if (!empty($allOUs)) {
        $firstOu = $allOUs[0];
        echo "\n=== Testing getUsersByOu() for {$firstOu['ou']} ===\n";
        $users = $ldap->getUsersByOu($firstOu['dn']);
        echo "Found " . count($users) . " users in {$firstOu['ou']}:\n";
        
        foreach (array_slice($users, 0, 3) as $user) {
            echo "- {$user['samaccountname']} ({$user['displayname']}) - Title: " . ($user['title'] ?? 'N/A') . "\n";
        }
        
        if (count($users) > 3) {
            echo "... and " . (count($users) - 3) . " more users\n";
        }
    }
    
    // Test getting a specific user
    if (!empty($users)) {
        $firstUser = $users[0];
        echo "\n=== Testing getUser() for {$firstUser['samaccountname']} ===\n";
        $userData = $ldap->getUser($firstUser['cn']);
        if ($userData) {
            echo "✓ User found:\n";
            echo "- CN: " . ($userData['cn'][0] ?? 'N/A') . "\n";
            echo "- Display Name: " . ($userData['displayname'][0] ?? 'N/A') . "\n";
            echo "- Title: " . ($userData['title'][0] ?? 'N/A') . "\n";
            echo "- Department: " . ($userData['department'][0] ?? 'N/A') . "\n";
            echo "- Email: " . ($userData['mail'][0] ?? 'N/A') . "\n";
        } else {
            echo "✗ User not found\n";
        }
    }
    
    echo "\n=== LDAP Debug Complete ===\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
