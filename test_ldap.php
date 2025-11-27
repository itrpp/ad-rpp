<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing LDAP connection...\n";

try {
    $ldap = ldap_connect('ldap://192.168.238.8:389');
    if (!$ldap) {
        echo "Failed to connect to LDAP server\n";
        exit(1);
    }
    echo "Connected to LDAP server\n";

    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    echo "Attempting to bind with admin credentials...\n";
    $bind = ldap_bind($ldap, 'cn=ldaprpp,OU=rpp-user,DC=rpphosp,DC=local', 'rpp14641');
    if (!$bind) {
        echo "Failed to bind: " . ldap_error($ldap) . "\n";
        echo "Error code: " . ldap_errno($ldap) . "\n";
        exit(1);
    }
    echo "Successfully bound to LDAP server\n";

    // Test base DN access
    $baseDn = 'OU=rpp-user,DC=rpphosp,DC=local';
    echo "Testing access to base DN: $baseDn\n";
    $search = ldap_read($ldap, $baseDn, "(objectClass=organizationalUnit)", ['ou']);
    if (!$search) {
        echo "Failed to access base DN: " . ldap_error($ldap) . "\n";
        echo "Error code: " . ldap_errno($ldap) . "\n";
        exit(1);
    }
    echo "Successfully accessed base DN\n";

    // Test registration OU access
    $regOu = 'OU=rpp-register,OU=rpp-user,DC=rpphosp,DC=local';
    echo "Testing access to registration OU: $regOu\n";
    $search = ldap_read($ldap, $regOu, "(objectClass=organizationalUnit)", ['ou']);
    if (!$search) {
        echo "Failed to access registration OU: " . ldap_error($ldap) . "\n";
        echo "Error code: " . ldap_errno($ldap) . "\n";
        exit(1);
    }
    echo "Successfully accessed registration OU\n";

    echo "All LDAP tests passed successfully\n";
} catch (Exception $e) {
    echo "Exception occurred: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
} 