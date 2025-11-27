<?php
namespace common\components;

use Yii;

class LdapHelper
{
    private $ldapConn;
    private $host;
    private $adminDn;
    private $adminPassword;
    private $accountEnabled = true;
    private $passwordNeverExpires = false;

    public function __construct()
    {
        $config = Yii::$app->params['ldap'];
        $this->host = $config['server'];
        $this->adminDn = $config['admin_dn'];
        $this->adminPassword = $config['admin_password'];
        
        Yii::debug("Attempting to connect to LDAP server: {$config['server']}");
        
        // Connect to LDAP server
        $this->ldapConn = ldap_connect($config['server'], $config['port']);
        if (!$this->ldapConn) {
            Yii::error("Failed to connect to LDAP server: {$config['server']}");
            throw new \Exception("Failed to connect to LDAP server");
        }

        Yii::debug("Successfully connected to LDAP server");

        // Set LDAP options
        Yii::debug("Setting LDAP options");
        ldap_set_option($this->ldapConn, LDAP_OPT_PROTOCOL_VERSION, $config['version']);
        ldap_set_option($this->ldapConn, LDAP_OPT_REFERRALS, $config['referrals']);
        ldap_set_option($this->ldapConn, LDAP_OPT_NETWORK_TIMEOUT, $config['timeout']);
        ldap_set_option($this->ldapConn, LDAP_OPT_TIMELIMIT, $config['timeout']);
        ldap_set_option($this->ldapConn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        
        // if ($config['use_tls']) {

        //     if (!ldap_start_tls($this->ldapConn)) {
        //         Yii::error("Failed to start TLS");
        //         throw new \Exception("Failed to start TLS");
        //     }
        // }

        // Debug: Log bind attempt
        Yii::debug("Attempting to bind with DN: {$config['admin_dn']}");
        
        // Bind to LDAP server
        $bindResult = ldap_bind($this->ldapConn, $config['admin_dn'], $config['admin_password']);
        if (!$bindResult) {
            $error = ldap_error($this->ldapConn);
            $errno = ldap_errno($this->ldapConn);
            Yii::error("Failed to bind to LDAP server: $error (Error code: $errno)");
            Yii::error("Admin DN: {$config['admin_dn']}");
            Yii::error("Server: {$config['server']}");
            Yii::error("Port: {$config['port']}");
            Yii::error("Version: {$config['version']}");
            throw new \Exception("Failed to bind to LDAP server: $error");
        }
        
        Yii::debug("Successfully bound to LDAP server");

        // Verify base DN exists
        $baseDn = $config['base_dn'];
        Yii::debug("Verifying base DN exists: $baseDn");
        $search = ldap_read($this->ldapConn, $baseDn, "(objectClass=*)", ['dn']);
        if (!$search) {
            $error = ldap_error($this->ldapConn);
            $errno = ldap_errno($this->ldapConn);
            Yii::error("Base DN does not exist: $baseDn");
            Yii::error("LDAP error: $error (Error code: $errno)");
            throw new \Exception("Base DN does not exist: $baseDn");
        }
        Yii::debug("Base DN exists: $baseDn");
    }

    /**
     * Gets the LDAP connection resource
     * @return mixed The LDAP connection resource or object
     */
    public function getConnection()
    {
        return $this->ldapConn;
    }

    public function updateUser($username, $data)
    {
        Yii::debug("Starting updateUser for username: $username");
        Yii::debug("Update data received: " . print_r($data, true));
        
        // Validate input data
        if (!is_array($data)) {
            Yii::error("Invalid data format: expected array, got " . gettype($data));
            return false;
        }

        // First find the user's actual DN by searching all possible locations
        $baseDns = [
            Yii::$app->params['ldap']['base_dn'], // Search the entire domain
        ];

        $userDN = null;
        $userData = null;
        foreach ($baseDns as $baseDn) {
            try {
                // Try both cn and sAMAccountName for searching
                $filters = [
                    "(sAMAccountName=" . $this->escapeLdapValue($username) . ")",
                    "(cn=" . $this->escapeLdapValue($username) . ")"
                ];
                
                foreach ($filters as $filter) {
                    Yii::debug("Searching with filter: $filter in base DN: $baseDn");
                    $search = ldap_search($this->ldapConn, $baseDn, $filter);
                    if ($search) {
                        $entries = ldap_get_entries($this->ldapConn, $search);
                        if ($entries['count'] > 0) {
                            $userDN = $entries[0]['distinguishedname'][0];
                            $userData = $entries[0];
                            Yii::debug("Found user DN: $userDN");
                            break 2;
                        }
                    }
                }
            } catch (\Exception $e) {
                Yii::error("Error searching in base DN $baseDn: " . $e->getMessage());
                continue;
            }
        }

        if (!$userDN) {
            Yii::error("User with username '$username' not found in any organizational unit.");
            return false;
        }

        // Log current user data
        Yii::debug("Current user data: " . print_r($userData, true));
        
        // Format data for LDAP update
        $ldapData = [];
        foreach ($data as $key => $value) {
            // Skip empty values
            if (empty($value) && $value !== '0') {
                continue;
            }
            
            if ($key === 'newPassword' && !empty($value)) {
                // Validate password (minimum 1 character - allow admin to set short passwords like 1234)
                if (strlen($value) < 1) {
                    Yii::error("Password cannot be empty.");
                    throw new \Exception("Password cannot be empty.");
                }

                // Convert password to UTF-16LE format required by Active Directory
                $unicodePassword = mb_convert_encoding('"' . $value . '"', 'UTF-16LE');
                $ldapData['unicodePwd'] = [$unicodePassword];
                Yii::debug("Password update included in LDAP data");
            } elseif ($key === 'telephoneNumber' || $key === 'telephonenumber') {
                // Handle telephone number specifically
                if (!empty($value)) {
                    $ldapData['telephoneNumber'] = [$value];
                    Yii::debug("Adding telephone number: $value");
                } else {
                    // If empty, remove the attribute
                    $ldapData['telephoneNumber'] = [];
                    Yii::debug("Removing telephone number attribute");
                }
            } elseif ($key !== 'newPassword' && $key !== 'confirmPassword') { // Skip empty password and confirm password
                // Keep original case of attribute names
                // Ensure value is a string, not an array
                $stringValue = is_array($value) ? (isset($value[0]) ? $value[0] : '') : $value;
                
                // Special handling for physicalDeliveryOfficeName and mail - always include them
                if ($key === 'physicalDeliveryOfficeName' || $key === 'mail') {
                    // Always include these fields, even if they're "ยังไม่ระบุ"
                    $ldapData[$key] = [$stringValue];
                    Yii::debug("Adding $key with value: $stringValue");
                } else {
                    // For other fields, only add if not empty
                    if (!empty($stringValue)) {
                        $ldapData[$key] = [$stringValue];
                        Yii::debug("Adding field $key with value: $stringValue");
                    }
                }
            }
        }

        if (empty($ldapData)) {
            Yii::debug("No data to update for user: $username");
            return true;
        }

        Yii::debug("Updating LDAP user with DN: $userDN");
        Yii::debug("Final LDAP update data: " . print_r($ldapData, true));

        try {
            // First, verify the user exists and is accessible
            $search = ldap_read($this->ldapConn, $userDN, "(objectClass=*)", ['dn']);
            if (!$search) {
                $error = ldap_error($this->ldapConn);
                $errno = ldap_errno($this->ldapConn);
                Yii::error("Failed to verify user existence: $error (Error code: $errno)");
                return false;
            }

            // If sAMAccountName is being changed, also update userPrincipalName accordingly
            if (isset($ldapData['sAMAccountName'][0]) && !isset($ldapData['userPrincipalName'])) {
                $newSam = $ldapData['sAMAccountName'][0];
                $domain = Yii::$app->params['ldap']['domain'] ?? null;
                if (!empty($domain)) {
                    $ldapData['userPrincipalName'] = [$newSam . '@' . $domain];
                    Yii::debug("Synchronizing userPrincipalName with new sAMAccountName: {$ldapData['userPrincipalName'][0]}");
                }
            }

            // Attempt the update
            $result = ldap_modify($this->ldapConn, $userDN, $ldapData);
            if (!$result) {
                $error = ldap_error($this->ldapConn);
                $errno = ldap_errno($this->ldapConn);
                Yii::error("LDAP update failed: $error (Error code: $errno)");
                Yii::error("User DN: $userDN");
                Yii::error("Update data: " . print_r($ldapData, true));
                return false;
            }

            // Verify the update was successful by reading the updated data
            $verifySearch = ldap_read($this->ldapConn, $userDN, "(objectClass=*)", array_keys($ldapData));
            if ($verifySearch) {
                $verifyEntries = ldap_get_entries($this->ldapConn, $verifySearch);
                Yii::debug("Verification of update - Current data: " . print_r($verifyEntries[0], true));
            }

            Yii::debug("LDAP update successful");
            return true;
        } catch (\Exception $e) {
            Yii::error("LDAP update exception: " . $e->getMessage());
            Yii::error("Stack trace: " . $e->getTraceAsString());
            Yii::error("Update data that caused error: " . print_r($ldapData, true));
            Yii::error("Original data received: " . print_r($data, true));
            return false;
        }
    }

    public function deleteUser($userDn)
    {
        try {
            if (!$this->ldapConn) {
                throw new \Exception('LDAP connection not established');
            }

            // Delete the user
            $result = ldap_delete($this->ldapConn, $userDn);
            
            if (!$result) {
                $error = ldap_error($this->ldapConn);
                Yii::error("LDAP Error deleting user: " . $error);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Yii::error("Exception while deleting user: " . $e->getMessage());
            return false;
        }
    }

    public function getUser($cn)
    {
        $baseDns = [
            Yii::$app->params['ldap']['base_dn'], // Search the entire domain
     ];

        $filter = "(cn=" . $this->escapeLdapValue($cn) . ")";
        $attributes = [
            'cn', 'samaccountname', 'displayname', 'department',
            'mail', 'useraccountcontrol', 'ou', 'distinguishedname',
            'whencreated', 'company', 'telephonenumber', 'title',
            'sn', 'givenname', 'initials', 'description', 'streetaddress',
            'l', 'st', 'postalcode', 'postofficebox', 'countrycode',
            'telephonenumber', 'mobile', 'pager', 'ipphone', 'homephone',
            'userprincipalname', 'accountexpires', 'pwdlastset', 'lastlogon',
            'lastlogoff', 'logoncount', 'primarygroupid', 'samaccounttype',
            'usncreated', 'usnchanged', 'whenchanged', 'objectclass',
            'objectguid', 'objectsid', 'instancetype', 'codepage',
            'msds-supportedencryptiontypes', 'name', 'co', 'physicaldeliveryofficename',
            'wwwhomepage', 'jobtitle', 'personalTitle', 'memberof'  // Added memberof for group membership checking
        ];
        
        // Search in each base DN
        foreach ($baseDns as $baseDn) {
            try {
                $search = ldap_search($this->ldapConn, $baseDn, $filter, $attributes);
                if ($search) {
                    $entries = ldap_get_entries($this->ldapConn, $search);
                    if ($entries['count'] > 0) {
                        return $entries[0];
                    }
                }
            } catch (\Exception $e) {
                Yii::error("Error searching in base DN $baseDn: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    public function getAllUsers()
    {
        $allUsers = [];
        $baseDns = [
            Yii::$app->params['ldap']['base_dn'], // Search the entire domain
        ];
            
        // Set size limit to a higher value
        ldap_set_option($this->ldapConn, LDAP_OPT_SIZELIMIT, 10000);
        
        foreach ($baseDns as $baseDn) {
            try {
                Yii::debug("Searching for users in base DN: $baseDn");
                
                // Try different filters to find users
                $filters = [
                    "(objectClass=user)",
                    "(objectClass=person)",
                    "(&(objectClass=user)(objectCategory=person))"
                ];
                
                foreach ($filters as $filter) {
                    Yii::debug("Trying filter: $filter");
                    $attributes = [
                        'cn', 'samaccountname', 'displayname', 'department', 
                        'mail', 'useraccountcontrol', 'ou', 'distinguishedname',
                        'whencreated', 'whenchanged', 'company', 'telephonenumber', 'title', 'personalTitle',
                        'streetaddress', 'physicaldeliveryofficename', 'postalcode'
                    ];
                    
                    $search = ldap_search($this->ldapConn, $baseDn, $filter, $attributes);
                    if (!$search) {
                        $error = ldap_error($this->ldapConn);
                        Yii::error("LDAP search failed in $baseDn with filter $filter: $error");
                        continue;
                    }
                    
                    $entries = ldap_get_entries($this->ldapConn, $search);
                    Yii::debug("Found {$entries['count']} entries in $baseDn with filter $filter");
                    
                    if ($entries['count'] > 0) {
                        // Remove count element and merge users
                        unset($entries['count']);
                        $allUsers = array_merge($allUsers, $entries);
                    }
                }
            } catch (\Exception $e) {
                Yii::error("Error searching in base DN $baseDn: " . $e->getMessage());
                continue;
            }
        }
        
        Yii::debug("Total users found: " . count($allUsers));
        return $allUsers;
    }

    /**
     * Gets all organizational units under the specified base DN
     * @param string $baseDn The base DN to search under
     * @return array Array of OUs with their metadata
     */
    public function getOrganizationalUnits($baseDn = null)
    {
        if ($baseDn === null) {
            $baseDn = Yii::$app->params['ldap']['base_dn_user'];
        }

        Yii::debug("Getting organizational units for base DN: $baseDn");
        $ous = [];
        try {
            // First verify the base DN exists
            $baseSearch = ldap_read($this->ldapConn, $baseDn, "(objectClass=organizationalUnit)", ['ou']);
            if (!$baseSearch) {
                Yii::error("Base DN does not exist: $baseDn");
                return $ous;
            }

            // Search for organizational units
            $filter = "(objectClass=organizationalUnit)";
            $attributes = ['ou', 'description', 'distinguishedName'];
            
            Yii::debug("Searching with filter: $filter");
            $search = ldap_search($this->ldapConn, $baseDn, $filter, $attributes);
            if (!$search) {
                $error = ldap_error($this->ldapConn);
                Yii::error("LDAP search failed: $error");
                return $ous;
            }
            
            $entries = ldap_get_entries($this->ldapConn, $search);
            Yii::debug("Found {$entries['count']} OUs");
            
            // Add the registration OU if it's not found
            $registrationOu = Yii::$app->params['ldap']['base_dn_reg'];
            $foundRegistrationOu = false;
            
            if ($entries['count'] > 0) {
                for ($i = 0; $i < $entries['count']; $i++) {
                    $entry = $entries[$i];
                    $dn = $entry['distinguishedname'][0];
                    $ou = $entry['ou'][0];
                    
                    // Skip the main OU if it's the base DN
                    if ($dn === $baseDn) {
                        Yii::debug("Skipping main OU: $dn");
                        continue;
                    }

                    // Check if this is the registration OU
                    if ($dn === $registrationOu) {
                        $foundRegistrationOu = true;
                    }

                    // Get icon based on OU name
                    $icon = $this->getOuIcon($ou);
                    
                    $ous[] = [
                        'dn' => $dn,
                        'ou' => $ou,
                        'description' => isset($entry['description'][0]) ? $entry['description'][0] : '',
                        'icon' => $icon,
                        'label' => $this->getOuLabel($ou),
                        'badge' => 'bg-info'
                    ];
                    
                    Yii::debug("Added OU: $ou with DN: $dn");
                }
            }

            // If registration OU wasn't found, verify if it exists before creating
            if (!$foundRegistrationOu) {
                Yii::debug("Registration OU not found in search results, checking if it exists");
                
                // Split the registration OU path
                $ouParts = explode(',', $registrationOu);
                $ouName = str_replace('OU=', '', $ouParts[0]);
                $parentDn = implode(',', array_slice($ouParts, 1));
                
                // Check if the registration OU already exists (might not be in search results due to filter)
                $ouExists = @ldap_read($this->ldapConn, $registrationOu, "(objectClass=organizationalUnit)", ['ou', 'description']);
                if ($ouExists) {
                    // OU exists, add it to the list
                    $existingEntry = ldap_get_entries($this->ldapConn, $ouExists);
                    if ($existingEntry && isset($existingEntry['count']) && $existingEntry['count'] > 0) {
                        $entry = $existingEntry[0];
                        Yii::debug("Registration OU already exists: $registrationOu");
                        $ous[] = [
                            'dn' => $registrationOu,
                            'ou' => $ouName,
                            'description' => isset($entry['description'][0]) ? $entry['description'][0] : 'Registration Organizational Unit',
                            'icon' => 'fas fa-user-plus',
                            'label' => 'Registration',
                            'badge' => 'bg-primary'
                        ];
                    }
                } else {
                    // OU doesn't exist, try to create it
                    Yii::debug("Registration OU does not exist, attempting to create it");
                
                // Verify parent OU exists
                $parentSearch = ldap_read($this->ldapConn, $parentDn, "(objectClass=organizationalUnit)", ['ou']);
                if (!$parentSearch) {
                    Yii::error("Parent OU does not exist: $parentDn");
                    return $ous;
                }
                
                // Create the registration OU
                $ouData = [
                    'objectClass' => ['top', 'organizationalUnit'],
                    'ou' => [$ouName]
                ];
                
                    $createResult = @ldap_add($this->ldapConn, $registrationOu, $ouData);
                    if ($createResult) {
                    Yii::debug("Successfully created registration OU: $registrationOu");
                    $ous[] = [
                        'dn' => $registrationOu,
                        'ou' => $ouName,
                        'description' => 'Registration Organizational Unit',
                        'icon' => 'fas fa-user-plus',
                        'label' => 'Registration',
                        'badge' => 'bg-primary'
                    ];
                } else {
                    $error = ldap_error($this->ldapConn);
                    $errno = ldap_errno($this->ldapConn);
                        // If error is "Already exists", try to read and add it to list
                        if ($errno == 68) {
                            Yii::debug("Registration OU already exists (error code 68), reading it");
                            $readResult = @ldap_read($this->ldapConn, $registrationOu, "(objectClass=organizationalUnit)", ['ou', 'description']);
                            if ($readResult) {
                                $readEntry = ldap_get_entries($this->ldapConn, $readResult);
                                if ($readEntry && isset($readEntry['count']) && $readEntry['count'] > 0) {
                                    $entry = $readEntry[0];
                                    $ous[] = [
                                        'dn' => $registrationOu,
                                        'ou' => $ouName,
                                        'description' => isset($entry['description'][0]) ? $entry['description'][0] : 'Registration Organizational Unit',
                                        'icon' => 'fas fa-user-plus',
                                        'label' => 'Registration',
                                        'badge' => 'bg-primary'
                                    ];
                                }
                            }
                        } else {
                    Yii::error("Failed to create registration OU: $error (Error code: $errno)");
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::error("Error getting organizational units: " . $e->getMessage());
            Yii::error("Stack trace: " . $e->getTraceAsString());
        }

        return $ous;
    }

    /**
     * Gets the display label for an OU
     * @param string $ou The organizational unit name
     * @return string The display label
     */
    private function getOuLabel($ou)
    {
        $labels = [
            'rpp-register' => 'Registration',
            'rpp-user' => 'User',
        ];
        
        return $labels[$ou] ?? $ou;
    }

    /**
     * Gets the appropriate icon for an OU
     * @param string $ou The organizational unit name
     * @return string The Font Awesome icon class
     */
    private function getOuIcon($ou)
    {
        $icons = [
            'rpp-register' => 'fas fa-user-plus',
            'rpp-user' => 'fas fa-users',
            'default' => 'fas fa-folder'
        ];
        
        return $icons[$ou] ?? 'fas fa-folder';
    }

    /**
     * Moves a user to a different organizational unit
     * @param string $cn The common name of the user to move
     * @param string $newOU The new organizational unit to move the user to
     * @return bool Whether the move was successful
     */
    public function moveUser($cn, $newOU)
    {
        try {
            // First, find the user's current DN
            $user = $this->getUser($cn);
            if (!$user) {
                Yii::error("User not found: $cn");
                return false;
            }

            $currentDN = $user['distinguishedname'][0];
            Yii::debug("Current DN: $currentDN");
            
            // Extract the RDN (Relative Distinguished Name) from the current DN
            $rdn = substr($currentDN, 0, strpos($currentDN, ','));
            
            // Set the new DN based on the new OU
            if ($newOU === 'OU=rpp-register,DC=rpphosp,DC=local') {
                $newDN = Yii::$app->params['ldap']['base_dn_reg'];
            } else {
                // Use the full DN path for the new OU
                $newDN = $newOU;
            }
            
            Yii::debug("RDN: $rdn, New DN: $newDN");

            // Move to the final destination
            $result = ldap_rename($this->ldapConn, $currentDN, $rdn, $newDN, true);
            if (!$result) {
                $error = ldap_error($this->ldapConn);
                $errno = ldap_errno($this->ldapConn);
                Yii::error("Failed to move user to final destination: $error (Error code: $errno)");
                return false;
            }

            // Update the OU attribute
            $newFullDN = $rdn . ',' . $newDN;
            // Extract just the OU name from the DN for the attribute
            $ouName = preg_replace('/^OU=([^,]+).*$/', '$1', $newOU);
            $modifyData = ['ou' => [$ouName]];
            $modifyResult = ldap_modify($this->ldapConn, $newFullDN, $modifyData);
            
            if (!$modifyResult) {
                Yii::warning("Failed to update OU attribute after move: " . ldap_error($this->ldapConn));
                // Continue anyway as the move was successful
            }

            Yii::debug("Successfully moved user from $currentDN to $newFullDN");
            return true;
        } catch (\Exception $e) {
            Yii::error("Error moving user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sets password expiration options for a user
     * @param string $cn The common name of the user
     * @param bool $neverExpires Whether the password should never expire
     * @return bool Whether the operation was successful
     */
    public function setPasswordExpiration($cn, $neverExpires = true)
    {
        try {
            // Find the user's DN
            $baseDn = Yii::$app->params['ldap']['base_dn']; // Search the entire domain
            $filter = "(cn=" . $this->escapeLdapValue($cn) . ")";
            
            Yii::debug("Searching for user with filter: $filter in base DN: $baseDn");
            $search = ldap_search($this->ldapConn, $baseDn, $filter);
            
            if (!$search) {
                $error = ldap_error($this->ldapConn);
                $errno = ldap_errno($this->ldapConn);
                Yii::error("LDAP search failed when trying to set password expiration: $error (Error code: $errno)");
                Yii::$app->session->setFlash('error', "Failed to find user. LDAP Error: $error (Error code: $errno)");
                return false;
            }
            
            $entries = ldap_get_entries($this->ldapConn, $search);
            
            if ($entries['count'] === 0) {
                Yii::error("User with CN '$cn' not found in base DN: $baseDn");
                Yii::$app->session->setFlash('error', "User with CN '$cn' not found in base DN: $baseDn");
                return false;
            }
            
            $userDN = $entries[0]['distinguishedname'][0];
            Yii::debug("Found user with DN: $userDN");
            
            // Get current userAccountControl value
            $userAccountControl = 0;
            if (isset($entries[0]['useraccountcontrol'][0])) {
                $userAccountControl = intval($entries[0]['useraccountcontrol'][0]);
                Yii::debug("Current userAccountControl value: $userAccountControl");
            } else {
                Yii::debug("No userAccountControl attribute found, using default value: 0");
            }
            
            // Define constants for userAccountControl flags
            // These are standard Active Directory flags
            $PASSWD_NOTREQD = 0x0020;
            $DONT_EXPIRE_PASSWD = 0x10000;
            $NORMAL_ACCOUNT = 0x0200;
            $ACCOUNTDISABLE = 0x0002;
            
            // Set or clear the DONT_EXPIRE_PASSWD flag
            if ($neverExpires) {
                $userAccountControl |= $DONT_EXPIRE_PASSWD;
                Yii::debug("Setting password to never expire. New userAccountControl value: $userAccountControl");
            } else {
                $userAccountControl &= ~$DONT_EXPIRE_PASSWD;
                Yii::debug("Setting password to expire. New userAccountControl value: $userAccountControl");
            }
            
            // Update the userAccountControl attribute
            $modifyData = ['userAccountControl' => [$userAccountControl]];
            Yii::debug("Modifying user with data: " . print_r($modifyData, true));
            
            $result = ldap_modify($this->ldapConn, $userDN, $modifyData);
            
            if (!$result) {
                $error = ldap_error($this->ldapConn);
                $errno = ldap_errno($this->ldapConn);
                Yii::error("Failed to set password expiration: $error (Error code: $errno)");
                Yii::error("User DN: $userDN, userAccountControl: $userAccountControl");
                Yii::$app->session->setFlash('error', "Failed to set password expiration. LDAP Error: $error (Error code: $errno)");
                return false;
            }
            
            Yii::debug("Successfully set password expiration for user: $cn");
            return true;
        } catch (\Exception $e) {
            Yii::error("LDAP Password Expiration Exception: " . $e->getMessage());
            Yii::$app->session->setFlash('error', 'Failed to set password expiration. Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enables or disables a user account
     * @param string $cn The common name of the user
     * @param bool $enable Whether to enable or disable the account
     * @return bool Whether the operation was successful
     */
    public function setAccountStatus($cn, $enable = true)
    {
        try {
            // Try all possible base DNs when searching for the user
            $baseDns = [
                Yii::$app->params['ldap']['base_dn'], // Search the entire domain
            ];
            
            $userDN = null;
            $entries = null;
            
            // Try searching by sAMAccountName first (more reliable), then by CN
            foreach ($baseDns as $baseDn) {
                Yii::debug("Searching in base DN: $baseDn for identifier: $cn");
                
                // First try sAMAccountName (more accurate)
                $filter = "(sAMAccountName=" . $this->escapeLdapValue($cn) . ")";
                Yii::debug("Searching by sAMAccountName with filter: $filter");
                $search = @ldap_search($this->ldapConn, $baseDn, $filter, ['distinguishedname', 'useraccountcontrol', 'cn', 'samaccountname']);
                
                if ($search !== false) {
                    $entries = ldap_get_entries($this->ldapConn, $search);
                    if ($entries && isset($entries['count']) && $entries['count'] > 0) {
                        $userDN = $entries[0]['distinguishedname'][0];
                        Yii::debug("Found user by sAMAccountName in $baseDn with DN: $userDN");
                        Yii::debug("User CN: " . ($entries[0]['cn'][0] ?? 'N/A'));
                        Yii::debug("User sAMAccountName: " . ($entries[0]['samaccountname'][0] ?? 'N/A'));
                        break;
                    } else {
                        Yii::debug("No users found by sAMAccountName filter: $filter");
                    }
                } else {
                    $error = ldap_error($this->ldapConn);
                    Yii::debug("LDAP search by sAMAccountName failed: $error");
                }
                
                // If not found by sAMAccountName, try CN
                if (!$userDN) {
                $filter = "(cn=" . $this->escapeLdapValue($cn) . ")";
                    Yii::debug("Searching by CN with filter: $filter");
                    $search = @ldap_search($this->ldapConn, $baseDn, $filter, ['distinguishedname', 'useraccountcontrol', 'cn', 'samaccountname']);
                
                    if ($search !== false) {
                    $entries = ldap_get_entries($this->ldapConn, $search);
                        if ($entries && isset($entries['count']) && $entries['count'] > 0) {
                        $userDN = $entries[0]['distinguishedname'][0];
                            Yii::debug("Found user by CN in $baseDn with DN: $userDN");
                            Yii::debug("User CN: " . ($entries[0]['cn'][0] ?? 'N/A'));
                            Yii::debug("User sAMAccountName: " . ($entries[0]['samaccountname'][0] ?? 'N/A'));
                        break;
                        } else {
                            Yii::debug("No users found by CN filter: $filter");
                        }
                    } else {
                        $error = ldap_error($this->ldapConn);
                        Yii::debug("LDAP search by CN failed: $error");
                    }
                }
            }
            
            if (!$userDN) {
                Yii::error("User with identifier '$cn' not found in any organizational unit (searched by sAMAccountName and CN).");
                Yii::$app->session->setFlash('error', "User with identifier '$cn' not found in any organizational unit.");
                return false;
            }
            
            // Get current userAccountControl value
            $currentUAC = isset($entries[0]['useraccountcontrol'][0]) ? 
                intval($entries[0]['useraccountcontrol'][0]) : 
                0x0200; // NORMAL_ACCOUNT
            
            Yii::debug("Current userAccountControl value: $currentUAC");
            
            // Define constants for userAccountControl flags
            $ACCOUNTDISABLE = 0x0002;
            $NORMAL_ACCOUNT = 0x0200;
            
            // Calculate new userAccountControl value
            $userAccountControl = $currentUAC;
            
            // Always ensure NORMAL_ACCOUNT flag is set
            $userAccountControl |= $NORMAL_ACCOUNT;
            
            // Set or clear the ACCOUNTDISABLE flag
            if ($enable) {
                // Enable account by removing the ACCOUNTDISABLE flag
                $userAccountControl &= ~$ACCOUNTDISABLE;
                Yii::debug("Enabling account. Current UAC: $currentUAC, New UAC: $userAccountControl");
            } else {
                // Disable account by adding the ACCOUNTDISABLE flag
                $userAccountControl |= $ACCOUNTDISABLE;
                Yii::debug("Disabling account. Current UAC: $currentUAC, New UAC: $userAccountControl");
            }
            
            // Check if change is needed
            if ($userAccountControl === $currentUAC) {
                Yii::debug("No change needed. Account already in desired state.");
                return true; // Already in the desired state
            }
            
            // Update the userAccountControl attribute
            // LDAP requires array format: ['attribute' => ['value']]
            // Use integer value (not string) for userAccountControl
            $modifyData = ['userAccountControl' => [$userAccountControl]];
            Yii::debug("Modifying user with data: " . print_r($modifyData, true));
            Yii::debug("User DN: $userDN");
            Yii::debug("Enable parameter: " . ($enable ? 'true' : 'false'));
            Yii::debug("Current UAC: $currentUAC (hex: 0x" . dechex($currentUAC) . ")");
            Yii::debug("New UAC: $userAccountControl (hex: 0x" . dechex($userAccountControl) . ")");
            
            // Check connection before modifying
            if (!$this->ldapConn) {
                Yii::error("LDAP connection is not available");
                return false;
            }
            
            // Verify user DN is valid before attempting modification
            $testRead = ldap_read($this->ldapConn, $userDN, "(objectClass=*)", ["cn"]);
            if (!$testRead) {
                $error = ldap_error($this->ldapConn);
                Yii::error("Cannot read user DN before modification: $error");
                Yii::error("User DN: $userDN");
                return false;
            }
            
            // Perform the modification
            Yii::debug("Calling ldap_modify with DN: $userDN");
            $result = ldap_modify($this->ldapConn, $userDN, $modifyData);
            
            if (!$result) {
                $error = ldap_error($this->ldapConn);
                $errno = ldap_errno($this->ldapConn);
                Yii::error("Failed to set account status: $error (Error code: $errno)");
                Yii::error("User DN: $userDN");
                Yii::error("Current UAC: $currentUAC, Attempted UAC: $userAccountControl");
                Yii::error("Enable flag: " . ($enable ? 'true' : 'false'));
                
                // Don't set flash message for AJAX requests
                if (!Yii::$app->request->isAjax) {
                Yii::$app->session->setFlash('error', "Failed to set account status. LDAP Error: $error (Error code: $errno)");
                }
                return false;
            }
            
            // Verify the change by reading back the value (with a small delay to allow replication)
            // Note: In some AD environments, changes may not be immediately visible
            $verifyAttempts = 3;
            $verified = false;
            
            for ($attempt = 1; $attempt <= $verifyAttempts; $attempt++) {
                // Small delay to allow AD replication
                if ($attempt > 1) {
                    usleep(100000); // 0.1 second delay
                }
                
                $verifySearch = ldap_read($this->ldapConn, $userDN, "(objectClass=*)", ["userAccountControl"]);
                if ($verifySearch) {
                    $verifyEntries = ldap_get_entries($this->ldapConn, $verifySearch);
                    if (isset($verifyEntries[0]['useraccountcontrol'][0])) {
                        $verifiedUAC = intval($verifyEntries[0]['useraccountcontrol'][0]);
                        Yii::debug("Verification attempt $attempt: Verified UAC: $verifiedUAC (hex: 0x" . dechex($verifiedUAC) . ", expected: $userAccountControl)");
                        
                        // Check if the account is in the correct state
                        $isActuallyDisabled = (($verifiedUAC & $ACCOUNTDISABLE) !== 0);
                        $shouldBeEnabled = $enable;
                        
                        // If we want enabled and account is not disabled, OR if we want disabled and account is disabled
                        // That means: (shouldBeEnabled && !isActuallyDisabled) || (!shouldBeEnabled && isActuallyDisabled)
                        // Which simplifies to: (shouldBeEnabled === !isActuallyDisabled)
                        if ($shouldBeEnabled === !$isActuallyDisabled) {
                            // State matches expectation
                            $verified = true;
                            Yii::debug("Verification successful: Account is " . ($shouldBeEnabled ? 'enabled' : 'disabled') . " as expected");
                            break;
                        } else {
                            Yii::debug("Verification attempt $attempt: State mismatch. Expected " . ($shouldBeEnabled ? 'enabled' : 'disabled') . " but account is " . ($isActuallyDisabled ? 'disabled' : 'enabled'));
                            Yii::debug("Verification attempt $attempt: Verified UAC=$verifiedUAC (hex=0x" . dechex($verifiedUAC) . "), ACCOUNTDISABLE flag=" . ($isActuallyDisabled ? 'SET' : 'NOT SET'));
                        }
                    }
                }
            }
            
            if (!$verified) {
                Yii::warning("Verification failed after $verifyAttempts attempts. The modification may have succeeded but verification could not confirm. UAC may need time to replicate.");
                // Don't return false here - the modify operation succeeded, verification is just a check
                // Some AD environments have replication delays
            }
            
            Yii::debug("Successfully set account status for user: $cn");
            return true;
        } catch (\Exception $e) {
            Yii::error("LDAP Account Status Exception: " . $e->getMessage());
            Yii::$app->session->setFlash('error', 'Failed to set account status. Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Escapes special characters in LDAP search filter values
     * @param string $value The value to escape
     * @return string The escaped value
     */
    private function escapeLdapValue($value)
    {
        $specialChars = ['\\', '*', '(', ')', '\0'];
        $escaped = $value;
        foreach ($specialChars as $char) {
            $escaped = str_replace($char, '\\' . $char, $escaped);
        }
        return $escaped;
    }

    public function __destruct()
    {
        ldap_unbind($this->ldapConn);
    }

    /**
     * Gets all users in a specific organizational unit
     * @param string $ouDn The DN of the organizational unit
     * @return array Array of users in the OU
     */
    public function getUsersByOu($ouDn)
    {
        Yii::debug("Getting users for OU: $ouDn");
        $users = [];
        
        try {
            // Search for users in the specified OU
            $filter = "(objectClass=user)";
            $attributes = [
                'cn', 'samaccountname', 'displayname', 'department',
                'mail', 'useraccountcontrol', 'ou', 'distinguishedname',
                        'whencreated', 'whenchanged', 'company', 'telephonenumber', 'title', 'personalTitle',
                        'streetaddress', 'physicaldeliveryofficename', 'postalcode'
            ];
            
            Yii::debug("Listing users (one-level) with filter: $filter in OU: $ouDn");
            // Use one-level search to get only users directly under this OU (exclude sub-OUs)
            $search = ldap_list($this->ldapConn, $ouDn, $filter, $attributes);
            
            if (!$search) {
                $error = ldap_error($this->ldapConn);
                Yii::error("LDAP one-level search failed for OU $ouDn: $error");
                return $users;
            }
            
            $entries = ldap_get_entries($this->ldapConn, $search);
            Yii::debug("Found {$entries['count']} users in OU: $ouDn");
            
            if ($entries['count'] > 0) {
                for ($i = 0; $i < $entries['count']; $i++) {
                    $entry = $entries[$i];
                    $users[] = [
                        'cn' => $entry['cn'][0] ?? $entry['displayname'][0] ?? '',
                        'samaccountname' => $entry['samaccountname'][0] ?? '',
                        'displayname' => $entry['displayname'][0] ?? '',
                        'department' => $entry['department'][0] ?? '',
                        'mail' => $entry['mail'][0] ?? '',
                        'useraccountcontrol' => $entry['useraccountcontrol'][0] ?? '',
                        'ou' => $entry['ou'][0] ?? '',
                        'distinguishedname' => $entry['distinguishedname'][0] ?? '',
                        'whencreated' => $entry['whencreated'][0] ?? '',
                        'whenchanged' => $entry['whenchanged'][0] ?? '',
                        'company' => $entry['company'][0] ?? '',
                        'telephonenumber' => $entry['telephonenumber'][0] ?? '',
                        'title' => $entry['title'][0] ?? '',
                        'streetaddress' => $entry['streetaddress'][0] ?? '',
                        'physicaldeliveryofficename' => $entry['physicaldeliveryofficename'][0] ?? '',
                        'postalcode' => $entry['postalcode'][0] ?? ''
                    ];
                }
            }
        } catch (\Exception $e) {
            Yii::error("Error getting users for OU $ouDn: " . $e->getMessage());
        }
        
        return $users;
    }

    /**
     * Authenticates a user against the LDAP server
     * @param string $username The username to authenticate
     * @param string $password The password to verify
     * @return array|false User data if authentication successful, false otherwise
     */
    public function authenticate($username, $password)
    {
        Yii::debug("Attempting to authenticate user: $username");
        
        try {
            // Search for the user in all OUs - use the main domain base DN to search across all OUs
            $config = Yii::$app->params['ldap'];
            $baseDns = [];
            
            // Check if we should search all OUs
            if (isset($config['search_all_ous']) && $config['search_all_ous']) {
                $baseDns = [$config['base_dn']]; // Search the entire domain
            } else {
                // Use specific OUs if configured
                $allowedOus = $config['allowed_ous'] ?? [];
                if (empty($allowedOus)) {
                    // Fallback to default OUs
                    $baseDns = [
                        $config['base_dn_user'] ?? $config['base_dn'],
                        $config['base_dn_reg'] ?? null,
                    ];
                    $baseDns = array_filter($baseDns); // Remove null values
                } else {
                    $baseDns = $allowedOus;
                }
            }
            
            $userDN = null;
            $userData = null;
            
            foreach ($baseDns as $baseDn) {
                $filter = "(sAMAccountName=" . $this->escapeLdapValue($username) . ")";
                $attributes = [
                    'cn', 'samaccountname', 'displayname', 'department',
                    'mail', 'useraccountcontrol', 'ou', 'distinguishedname',
                    'telephonenumber', 'memberof'
                ];
                
                Yii::debug("Searching with filter: $filter in base DN: $baseDn");
                Yii::debug("Requested attributes: " . print_r($attributes, true));
                
                $search = ldap_search($this->ldapConn, $baseDn, $filter, $attributes);
                if ($search) {
                    $entries = ldap_get_entries($this->ldapConn, $search);
                    if ($entries['count'] > 0) {
                        $userDN = $entries[0]['distinguishedname'][0];
                        $userData = $entries[0];
                        Yii::debug("Found user data: " . print_r($userData, true));
                        break;
                    }
                }
            }
            
            if (!$userDN) {
                Yii::error("User not found: $username");
                return false;
            }
            
            // Check if account is disabled
            $userAccountControl = isset($userData['useraccountcontrol'][0]) ? 
                intval($userData['useraccountcontrol'][0]) : 0;
            $ACCOUNTDISABLE = 0x0002;
            
            if ($userAccountControl & $ACCOUNTDISABLE) {
                Yii::error("Account is disabled for user: $username");
                return false;
            }
            
            // Attempt to bind with user credentials
            $bindResult = ldap_bind($this->ldapConn, $userDN, $password);
            if (!$bindResult) {
                Yii::error("Authentication failed for user: $username");
                return false;
            }
            
            // Rebind as admin for subsequent operations
            $config = Yii::$app->params['ldap'];
            ldap_bind($this->ldapConn, $config['admin_dn'], $config['admin_password']);
            
            Yii::debug("Authentication successful for user: $username");
            
            // Return user data
            $returnData = [
                'cn' => $userData['cn'][0] ?? $userData['displayname'][0] ?? '',
                'samaccountname' => $userData['samaccountname'][0] ?? '',
                'displayname' => $userData['displayname'][0] ?? '',
                'department' => $userData['department'][0] ?? '',
                'mail' => $userData['mail'][0] ?? '',
                'ou' => $userData['ou'][0] ?? '',
                'distinguishedname' => $userData['distinguishedname'][0] ?? '',
                'telephonenumber' => $userData['telephonenumber'][0] ?? '',
                'memberof' => isset($userData['memberof']) ? array_slice($userData['memberof'], 1) : []
            ];
            
            Yii::debug("Returning user data: " . print_r($returnData, true));
            return $returnData;
            
        } catch (\Exception $e) {
            Yii::error("LDAP Authentication Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Searches for users in a specific OU with optional search criteria
     * @param string $ou The organizational unit to search in
     * @param string $searchTerm The search term to filter users
     * @return array Array of matching users
     */
    public function searchUsers($ou = 'rpp-user', $searchTerm = '')
    {
        Yii::debug("Searching users in OU: $ou with term: $searchTerm");
        $users = [];
        
        try {
            // Build the search filter
            $filter = "(objectClass=user)";
            if (!empty($searchTerm)) {
                $searchTerm = $this->escapeLdapValue($searchTerm);
                $filter = "(&(objectClass=user)(|(cn=*$searchTerm*)(samaccountname=*$searchTerm*)(displayname=*$searchTerm*)(mail=*$searchTerm*)(department=*$searchTerm*)))";
            }
            
            $attributes = [
                'cn', 'samaccountname', 'displayname', 'department',
                'mail', 'useraccountcontrol', 'ou', 'distinguishedname',
                'whencreated', 'whenchanged', 'company', 'telephonenumber', 'title',
                'streetaddress', 'physicaldeliveryofficename', 'postalcode'
            ];
            
            // Search under the entire domain to find users in all OUs
            $baseDn = Yii::$app->params['ldap']['base_dn'];
            Yii::debug("Searching with filter: $filter in base DN: $baseDn");
            
            $search = ldap_search($this->ldapConn, $baseDn, $filter, $attributes);
            if (!$search) {
                $error = ldap_error($this->ldapConn);
                Yii::error("LDAP search failed: $error");
                return $users;
            }
            
            $entries = ldap_get_entries($this->ldapConn, $search);
            Yii::debug("Found {$entries['count']} users matching search criteria");
            
            if ($entries['count'] > 0) {
                for ($i = 0; $i < $entries['count']; $i++) {
                    $entry = $entries[$i];
                    $users[] = [
                        'cn' => $entry['cn'][0] ?? $entry['displayname'][0] ?? '',
                        'samaccountname' => $entry['samaccountname'][0] ?? '',
                        'displayname' => $entry['displayname'][0] ?? '',
                        'department' => $entry['department'][0] ?? '',
                        'mail' => $entry['mail'][0] ?? '',
                        'useraccountcontrol' => $entry['useraccountcontrol'][0] ?? '',
                        'ou' => $entry['ou'][0] ?? '',
                        'distinguishedname' => $entry['distinguishedname'][0] ?? '',
                        'whencreated' => $entry['whencreated'][0] ?? '',
                        'whenchanged' => $entry['whenchanged'][0] ?? '',
                        'company' => $entry['company'][0] ?? '',
                        'telephonenumber' => $entry['telephonenumber'][0] ?? '',
                        'title' => $entry['title'][0] ?? '',
                        'streetaddress' => $entry['streetaddress'][0] ?? '',
                        'physicaldeliveryofficename' => $entry['physicaldeliveryofficename'][0] ?? '',
                        'postalcode' => $entry['postalcode'][0] ?? ''
                    ];
                }
            }
        } catch (\Exception $e) {
            Yii::error("Error searching users: " . $e->getMessage());
        }
        
        return $users;
    }

    /**
     * Gets all organizational units with their details
     * @return array Array of OUs with their details
     */
    public function getAllOUs()
    {
        $ous = [];
        $baseDns = [
            Yii::$app->params['ldap']['base_dn'], // Search the entire domain
        ];
        
        foreach ($baseDns as $baseDn) {
            try {
                $filter = "(objectClass=organizationalUnit)";
                $attributes = [
                    'ou', 'description', 'distinguishedname', 'whencreated', 'whenchanged'
                ];
                
                Yii::debug("Searching OUs with filter: $filter in base DN: $baseDn");
                $search = ldap_search($this->ldapConn, $baseDn, $filter, $attributes);
                
                if (!$search) {
                    $error = ldap_error($this->ldapConn);
                    Yii::error("LDAP search failed for OUs in $baseDn: $error");
                    continue;
                }
                
                $entries = ldap_get_entries($this->ldapConn, $search);
                Yii::debug("Found {$entries['count']} OUs in $baseDn");
                
                if ($entries['count'] > 0) {
                    for ($i = 0; $i < $entries['count']; $i++) {
                        $entry = $entries[$i];
                        $dn = $entry['distinguishedname'][0];
                        $ouName = $entry['ou'][0];
                        
                        // Get users in this OU
                        $users = $this->getUsersByOu($dn);
                        
                        // Determine OU type and icon
                        $type = 'User OU';
                        $icon = 'fas fa-users';
                        $badge = 'primary';
                        
                        if (strpos($dn, 'OU=rpp-register') !== false) {
                            $type = 'Register OU';
                            $icon = 'fas fa-user-plus';
                            $badge = 'success';
                        }
                        
                        // Get parent OU
                        $parent = null;
                        $dnParts = explode(',', $dn);
                        if (count($dnParts) > 1) {
                            $parent = $dnParts[1];
                        }
                        
                        $ous[] = [
                            'ou' => $ouName,
                            'dn' => $dn,
                            'type' => $type,
                            'parent' => $parent,
                            'description' => $entry['description'][0] ?? '',
                            'users' => $users,
                            'created' => $entry['whencreated'][0] ?? date('YmdHis.0Z'),
                            'modified' => $entry['whenchanged'][0] ?? date('YmdHis.0Z'),
                            'icon' => $icon,
                            'badge' => $badge,
                            'level' => count($dnParts) - 1
                        ];
                    }
                }
            } catch (\Exception $e) {
                Yii::error("Error getting OUs from $baseDn: " . $e->getMessage());
            }
        }
        
        return $ous;
    }

    /**
     * Updates an organizational unit
     * @param string $dn The distinguished name of the OU to update
     * @param array $data The data to update
     * @return bool Whether the update was successful
     */
    // Removed updateOU per request

    /**
     * Gets all users that are not in any OU
     * @param int $page Page number (1-based)
     * @param int $pageSize Number of items per page
     * @return array Array containing users and total count
     */
    public function getUsersOutsideOUs($page = 1, $pageSize = 25)
    {
        Yii::debug("Getting users outside OUs - Page: $page, PageSize: $pageSize");
        
        $users = [];
        $totalCount = 0;
        $baseDNs = [
            Yii::$app->params['ldap']['base_dn'] // Search the entire domain
        ];
        
        // Set size limit
        ldap_set_option($this->ldapConn, LDAP_OPT_SIZELIMIT, $pageSize);
        
        foreach ($baseDNs as $baseDN) {
            // Search for users directly under the base DN
            $filter = "(&(objectClass=user)(objectCategory=person))";
            $attributes = [
                'cn',
                'samaccountname',
                'displayname',
                'department',
                'mail',
                'useraccountcontrol',
                'distinguishedname'
            ];
            
            // Calculate offset for pagination
            $offset = ($page - 1) * $pageSize;
            
            // Set pagination controls
            $controls = [
                [
                    'oid' => LDAP_CONTROL_PAGEDRESULTS,
                    'value' => [
                        'size' => $pageSize,
                        'cookie' => ''
                    ]
                ]
            ];
            
            $result = ldap_search($this->ldapConn, $baseDN, $filter, $attributes, $offset, $pageSize, 0, LDAP_DEREF_NEVER, $controls);
            if ($result) {
                $entries = ldap_get_entries($this->ldapConn, $result);
                if ($entries['count'] > 0) {
                    for ($i = 0; $i < $entries['count']; $i++) {
                        $user = $entries[$i];
                        // Check if user is directly under base DN
                        $dn = $user['distinguishedname'][0];
                        $dnParts = explode(',', $dn);
                        if (count($dnParts) === 2) { // Only base DN and CN
                            $users[] = $user;
                        }
                    }
                }
                
                // Get total count
                $countResult = ldap_search($this->ldapConn, $baseDN, $filter, ['dn'], $offset, 0, 0, LDAP_DEREF_NEVER);
                if ($countResult) {
                    $countEntries = ldap_get_entries($this->ldapConn, $countResult);
                    $totalCount += $countEntries['count'];
                }
            }
        }
        
        return [
            'users' => $users,
            'totalCount' => $totalCount
        ];
    }

    /**
     * Deletes an organizational unit
     * @param string $dn The distinguished name of the OU to delete
     * @return bool
     */
    // Removed deleteOU per request

    /**
     * Gets a user by email address
     * @param string $email The email address to search for
     * @return array|false User data if found, false otherwise
     */
    public function getUserByEmail($email)
    {
        try {
            // Search in all OUs
            $baseDns = [
                Yii::$app->params['ldap']['base_dn'], // Search the entire domain
            ];
            
            foreach ($baseDns as $baseDn) {
                $filter = "(mail=" . $this->escapeLdapValue($email) . ")";
                $attributes = [
                    'cn', 'samaccountname', 'displayname', 'department',
                    'mail', 'useraccountcontrol', 'ou', 'distinguishedname'
                ];
                
                $search = ldap_search($this->ldapConn, $baseDn, $filter, $attributes);
                if ($search) {
                    $entries = ldap_get_entries($this->ldapConn, $search);
                    if ($entries['count'] > 0) {
                        return $entries[0];
                    }
                }
            }
            
            return false;
        } catch (\Exception $e) {
            Yii::error("LDAP Error in getUserByEmail: " . $e->getMessage());
            return false;
        }
    }

    public function testConnection()
    {
        try {
            Yii::debug("Testing LDAP connection to: " . Yii::$app->params['ldap']['server']);
            
            // Connect to LDAP server
            $ldapConn = ldap_connect(Yii::$app->params['ldap']['server']);
            if (!$ldapConn) {
                Yii::error("Failed to connect to LDAP server");
                return false;
            }
            
            // Set LDAP options
            ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
            
            // Try to bind with admin credentials
            $adminDn = Yii::$app->params['ldap']['admin_dn'];
            $adminPassword = Yii::$app->params['ldap']['admin_password'];
            
            Yii::debug("Attempting to bind with admin credentials");
            if (!ldap_bind($ldapConn, $adminDn, $adminPassword)) {
                $error = ldap_error($ldapConn);
                $errno = ldap_errno($ldapConn);
                Yii::error("LDAP admin bind failed: $error (Error code: $errno)");
                Yii::error("Admin DN: $adminDn");
                return false;
            }
            
            // Test base DN access - now using the main domain base DN
            $baseDn = Yii::$app->params['ldap']['base_dn'];
            Yii::debug("Testing access to base DN: $baseDn");
            
            $search = ldap_read($ldapConn, $baseDn, "(objectClass=organizationalUnit)", ['ou']);
            if (!$search) {
                $error = ldap_error($ldapConn);
                $errno = ldap_errno($ldapConn);
                Yii::error("Failed to access base DN: $error (Error code: $errno)");
                return false;
            }
            
            Yii::debug("LDAP connection test successful");
            return true;
        } catch (\Exception $e) {
            Yii::error("Exception during LDAP connection test: " . $e->getMessage());
            Yii::error("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Tests authentication across all OUs
     * @param string $username The username to test
     * @param string $password The password to test
     * @return array Test results
     */
    public function testAuthenticationAcrossOUs($username, $password)
    {
        Yii::debug("Testing authentication across all OUs for user: $username");
        
        $results = [
            'success' => false,
            'user_found' => false,
            'authentication_successful' => false,
            'user_data' => null,
            'searched_ous' => [],
            'errors' => []
        ];
        
        try {
            $config = Yii::$app->params['ldap'];
            $baseDns = [];
            
            // Check if we should search all OUs
            if (isset($config['search_all_ous']) && $config['search_all_ous']) {
                $baseDns = [$config['base_dn']]; // Search the entire domain
                Yii::debug("Searching across entire domain: " . $config['base_dn']);
            } else {
                // Use specific OUs if configured
                $allowedOus = $config['allowed_ous'] ?? [];
                if (empty($allowedOus)) {
                    // Fallback to default OUs
                    $baseDns = [
                        $config['base_dn_user'] ?? $config['base_dn'],
                        $config['base_dn_reg'] ?? null,
                    ];
                    $baseDns = array_filter($baseDns); // Remove null values
                } else {
                    $baseDns = $allowedOus;
                }
                Yii::debug("Searching in specific OUs: " . implode(', ', $baseDns));
            }
            
            $results['searched_ous'] = $baseDns;
            
            $userDN = null;
            $userData = null;
            
            foreach ($baseDns as $baseDn) {
                $filter = "(sAMAccountName=" . $this->escapeLdapValue($username) . ")";
                $attributes = [
                    'cn', 'samaccountname', 'displayname', 'department',
                    'mail', 'useraccountcontrol', 'ou', 'distinguishedname',
                    'telephonenumber', 'memberof'
                ];
                
                Yii::debug("Searching with filter: $filter in base DN: $baseDn");
                
                $search = ldap_search($this->ldapConn, $baseDn, $filter, $attributes);
                if ($search) {
                    $entries = ldap_get_entries($this->ldapConn, $search);
                    if ($entries['count'] > 0) {
                        $userDN = $entries[0]['distinguishedname'][0];
                        $userData = $entries[0];
                        $results['user_found'] = true;
                        Yii::debug("Found user in OU: $baseDn");
                        break;
                    }
                }
            }
            
            if (!$userDN) {
                $results['errors'][] = "User not found in any OU";
                return $results;
            }
            
            // Check if account is disabled
            $userAccountControl = isset($userData['useraccountcontrol'][0]) ? 
                intval($userData['useraccountcontrol'][0]) : 0;
            $ACCOUNTDISABLE = 0x0002;
            
            if ($userAccountControl & $ACCOUNTDISABLE) {
                $results['errors'][] = "Account is disabled";
                return $results;
            }
            
            // Attempt to bind with user credentials
            $bindResult = ldap_bind($this->ldapConn, $userDN, $password);
            if (!$bindResult) {
                $results['errors'][] = "Authentication failed - invalid password";
                return $results;
            }
            
            // Rebind as admin for subsequent operations
            $config = Yii::$app->params['ldap'];
            ldap_bind($this->ldapConn, $config['admin_dn'], $config['admin_password']);
            
            $results['authentication_successful'] = true;
            $results['success'] = true;
            $results['user_data'] = [
                'cn' => $userData['cn'][0] ?? $userData['displayname'][0] ?? '',
                'samaccountname' => $userData['samaccountname'][0] ?? '',
                'displayname' => $userData['displayname'][0] ?? '',
                'department' => $userData['department'][0] ?? '',
                'mail' => $userData['mail'][0] ?? '',
                'ou' => $userData['ou'][0] ?? '',
                'distinguishedname' => $userData['distinguishedname'][0] ?? '',
                'telephonenumber' => $userData['telephonenumber'][0] ?? ''
            ];
            
            Yii::debug("Authentication test successful for user: $username");
            return $results;
            
        } catch (\Exception $e) {
            $results['errors'][] = "Exception: " . $e->getMessage();
            Yii::error("LDAP Authentication Test Exception: " . $e->getMessage());
            return $results;
        }
    }

    /**
     * Creates a new user in LDAP
     * @param string $userDn The DN of the user to create
     * @param array $data User data
     * @param string $password User password
     * @return bool|string True on success, error message on failure
     */
    public function createUser($userDn, $data, $password)
    {
        // Ensure required attributes are set
        if (empty($data['sAMAccountName'][0])) {
            $data['sAMAccountName'] = $data['cn'];
        }
        if (empty($data['displayName'][0])) {
            $data['displayName'] = $data['cn'];
        }
        if (empty($data['sn'][0])) {
            $data['sn'] = $data['cn'];
        }

        // Optionally, add your own validation here if needed
        if (empty($data['cn'][0]) || empty($data['sAMAccountName'][0]) || empty($password)) {
            Yii::error("Missing required user attributes: " . print_r($data, true));
            return false;
        }

        try {
            // Verify LDAP connection
            if (!$this->ldapConn) {
                Yii::error("LDAP connection failed");
                return false;
            }

            // Test LDAP connection with admin credentials
            $adminDn = Yii::$app->params['ldap']['admin_dn'];
            $adminPassword = Yii::$app->params['ldap']['admin_password'];
            
            Yii::debug("Attempting to bind with admin credentials");
            if (!ldap_bind($this->ldapConn, $adminDn, $adminPassword)) {
                $error = ldap_error($this->ldapConn);
                $errno = ldap_errno($this->ldapConn);
                Yii::error("LDAP admin bind failed: $error (Error code: $errno)");
                Yii::error("Admin DN: $adminDn");
                return false;
            }
            Yii::debug("Successfully bound as admin user");

            // Check if user already exists
            Yii::debug("Checking if user already exists: {$data['cn'][0]}");
            $existingUser = $this->getUser($data['cn'][0]);
            if ($existingUser) {
                Yii::error("User already exists: {$data['cn'][0]}");
                return false;
            }

            // Calculate userAccountControl value
            $uac = 512; // NORMAL_ACCOUNT
            if ($this->passwordNeverExpires) {
                $uac |= 65536; // DONT_EXPIRE_PASSWORD
            }

            // Prepare user data WITHOUT password
            $userData = [
                'objectClass' => ['top', 'person', 'organizationalPerson', 'user'],
                'cn' => [$this->escapeLdapValue($data['cn'][0])],
                'sn' => [$this->escapeLdapValue($data['sn'][0])],
                'sAMAccountName' => [$this->escapeLdapValue($data['sAMAccountName'][0])],
                'givenName' => [$this->escapeLdapValue($data['cn'][0])], // Required for AD users
                'displayName' => [$this->escapeLdapValue($data['displayName'][0])],
                'userPrincipalName' => [$this->escapeLdapValue($data['sAMAccountName'][0]) . '@' . Yii::$app->params['ldap']['domain']],
                'mail' => [isset($data['mail'][0]) && !empty($data['mail'][0]) ? $this->escapeLdapValue($data['mail'][0]) : ''],
                'department' => [isset($data['department'][0]) && !empty($data['department'][0]) ? $this->escapeLdapValue($data['department'][0]) : ''],
                'telephoneNumber' => [isset($data['telephoneNumber'][0]) && !empty($data['telephoneNumber'][0]) ? $this->escapeLdapValue($data['telephoneNumber'][0]) : ''],
                'userAccountControl' => [(string)$uac],
                'objectCategory' => ['CN=Person,CN=Schema,CN=Configuration,DC=rpphosp,DC=local']
            ];

            // Remove empty values
            foreach ($userData as $key => $value) {
                if (empty($value[0])) {
                    unset($userData[$key]);
                }
            }
            
            Yii::debug("Creating user with DN: $userDn");
            Yii::debug("User data: " . print_r($userData, true));

            // 1. Create user without password
            $result = ldap_add($this->ldapConn, $userDn, $userData);
            if (!$result) {
                $error = ldap_error($this->ldapConn);
                $errno = ldap_errno($this->ldapConn);
                Yii::error("Failed to create user: $error (Error code: $errno)");
                Yii::error("User DN: $userDn");
                Yii::error("User data: " . print_r($userData, true));
                
                // Map common LDAP error codes to user-friendly messages
                $errorMessages = [
                    68 => "User already exists",
                    34 => "Invalid user name format",
                    50 => "Insufficient permissions to create user",
                    19 => "Password does not meet complexity requirements",
                    65 => "Invalid user attributes",
                    53 => "Server is unwilling to perform - Check OU permissions",
                ];
                
                $errorMessage = $errorMessages[$errno] ?? "Failed to create user: $error (Error code: $errno)";
                Yii::error("Detailed error: $errorMessage");
                return false;
            }

            // 2. Set password (must be over LDAPS)
            $unicodePwd = mb_convert_encoding('"' . $password . '"', 'UTF-16LE');
            $modifyData = ['unicodePwd' => [$unicodePwd]];
            $modifyResult = ldap_modify($this->ldapConn, $userDn, $modifyData);
            if (!$modifyResult) {
                $error = ldap_error($this->ldapConn);
                $errno = ldap_errno($this->ldapConn);
                Yii::error("Failed to set password: $error (Error code: $errno)");
                Yii::error("User DN: $userDn");
                Yii::error("Modify data: " . print_r($modifyData, true));
                return false;
            }

            Yii::debug("Successfully created user: $userDn");
            return true;
        } catch (\Exception $e) {
            Yii::error("Exception while creating user: " . $e->getMessage());
            Yii::error("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    // Removed changePassword method; password changes should be handled via updateUser with 'newPassword'

    /**
     * Creates a new organizational unit
     * @param string $ouName The name of the OU
     * @param string $type The type of OU (User OU, Register OU, Other OU)
     * @param string $parentOu The parent OU DN (optional)
     * @param string $description The description of the OU (optional)
     * @param bool $protected Whether the OU should be protected from accidental deletion
     * @return bool True if successful, false otherwise
     */
    // Removed createOU per request

}