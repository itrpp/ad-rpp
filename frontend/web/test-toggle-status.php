<?php
/**
 * Test Toggle Status Action
 * This file helps test the toggle-status functionality
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
    echo "=== Testing Toggle Status Action ===\n\n";
    
    // Test URL generation
    $url = Yii::$app->urlManager->createUrl(['ldapuser/toggle-status']);
    echo "Generated URL: $url\n";
    
    // Test if controller exists
    $controller = new \frontend\controllers\LdapuserController('ldapuser', Yii::$app);
    echo "✓ LdapuserController exists\n";
    
    // Test if action exists
    if (method_exists($controller, 'actionToggleStatus')) {
        echo "✓ actionToggleStatus method exists\n";
    } else {
        echo "✗ actionToggleStatus method not found\n";
    }
    
    // Test LDAP connection
    $ldap = new \common\components\LdapHelper();
    if ($ldap->testConnection()) {
        echo "✓ LDAP Connection successful\n";
    } else {
        echo "✗ LDAP Connection failed\n";
    }
    
    // Test setAccountStatus method
    if (method_exists($ldap, 'setAccountStatus')) {
        echo "✓ setAccountStatus method exists\n";
    } else {
        echo "✗ setAccountStatus method not found\n";
    }
    
    echo "\n=== Test Complete ===\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
