<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use common\models\LdapUser;
use yii\web\NotFoundHttpException;
use common\components\LdapHelper;
use common\components\PermissionManager;

class LdapuserController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view'], // Allow guests to view and index
                        'allow' => true,
                        'roles' => ['?', '@'],
                    ],
                    [
                        'actions' => ['ou-register'], // Admin only page
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            $permissionManager = new PermissionManager();
                            return $permissionManager->isLdapAdmin();
                        }
                    ],
                    [
                        'actions' => ['ou-user'], // View-only page for admin or superuser
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            $permissionManager = new PermissionManager();
                            return $permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_VIEW);
                        }
                    ],
                    [
                        'actions' => ['update', 'move', 'toggle-status', 'create', 'ou-outs', 'move-to-user', 'delete', 'get-user-data'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['get-user-groups', 'get-available-groups', 'add-user-to-group', 'remove-user-from-group'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            $permissionManager = new PermissionManager();
                            // อนุญาตให้ user ที่มีสิทธิ์ดู LDAP users หรือจัดการ group members
                            return $permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_VIEW) || 
                                   $permissionManager->hasPermission(PermissionManager::PERMISSION_GROUP_MANAGE_MEMBERS);
                        }
                    ],
                ],
            ],
        ];
    }

    /**
     * Get the current logged-in user's LDAP data
     * @return array|null
     */
    protected function getCurrentUserLdapData()
    {
        return Yii::$app->session->get('ldapUserData');
    }

    /**
     * Check if current user has permission to perform action
     * @param string $action
     * @return bool
     */
    protected function hasPermission($action)
    {
        $userData = $this->getCurrentUserLdapData();
        if (!$userData) {
            return false;
        }

        // Check if user is in admin group or has specific permissions
        $userGroups = isset($userData['memberof']) ? $userData['memberof'] : [];
        $isAdmin = false;
        
        // Check for IT OU membership
        $isITUser = false;
        if (isset($userData['distinguishedname'])) {
            $dn = $userData['distinguishedname'];
            if (stripos($dn, 'OU=IT,OU=rpp-user,DC=rpphosp,DC=local') !== false) {
                $isITUser = true;
            }
        }

        foreach ($userGroups as $group) {
            if (stripos($group, 'CN=Administrators') !== false || 
                stripos($group, 'CN=Domain Admins') !== false) {
                $isAdmin = true;
                break;
            }
        }

        // IT OU users are considered admins
        if ($isITUser) {
            $isAdmin = true;
        }

        return $isAdmin;
    }

    public function actionIndex()
    {
        try {
            $ldap = new LdapHelper();
            $department = Yii::$app->request->get('department');
            $selectedOu = Yii::$app->request->get('ou');
            
            // Get current user's data
            $currentUser = $this->getCurrentUserLdapData();
            
            // Get all users
            $users = $ldap->getAllUsers();
            
            // Filter by department if specified
            if (!empty($department)) {
                $users = array_filter($users, function($user) use ($department) {
                    return isset($user['department'][0]) && 
                           strtolower($user['department'][0]) === strtolower($department);
                });
            }

            // Filter by OU if specified
            if (!empty($selectedOu)) {
                $users = array_filter($users, function($user) use ($selectedOu) {
                    return isset($user['ou'][0]) && 
                           strtolower($user['ou'][0]) === strtolower($selectedOu);
                });
            }
            
            // Sort users by display name by default
            usort($users, function($a, $b) {
                $nameA = isset($a['displayname'][0]) ? $a['displayname'][0] : '';
                $nameB = isset($b['displayname'][0]) ? $b['displayname'][0] : '';
                return strcasecmp($nameA, $nameB);
            });
            
            // Get unique departments for filter dropdown
            $departments = [];
            foreach ($users as $user) {
                if (isset($user['department'][0])) {
                    $dept = $user['department'][0];
                    $departments[$dept] = $dept;
                }
            }
            sort($departments);

            // Get organizational units for filter dropdown
            $ous = [];
            $ouList = $ldap->getOrganizationalUnits();
            foreach ($ouList as $ou) {
                $ous[$ou['ou']] = $ou['label'];
            }

            // Create pagination object
            $pagination = new \yii\data\Pagination([
                'totalCount' => count($users),
                'pageSize' => 10,
                'pageSizeParam' => false,
                'pageParam' => 'page',
            ]);

            // Apply pagination to users array
            $users = array_slice($users, $pagination->offset, $pagination->limit);
            
            return $this->render('index', [
                'users' => $users,
                'departments' => $departments,
                'selectedDepartment' => $department,
                'ous' => $ous,
                'selectedOu' => $selectedOu,
                'pagination' => $pagination,
                'currentUser' => $currentUser,
                'isAdmin' => $this->hasPermission('index')
            ]);
        } catch (\Exception $e) {
            Yii::error("LDAP Error: " . $e->getMessage());
            Yii::$app->session->setFlash('error', "LDAP Error: " . $e->getMessage());
            return $this->render('index', [
                'users' => [],
                'departments' => [],
                'selectedDepartment' => null,
                'ous' => [],
                'selectedOu' => null,
                'pagination' => new \yii\data\Pagination([
                    'totalCount' => 0,
                    'pageSize' => 10,
                    'pageSizeParam' => false,
                    'pageParam' => 'page',
                ]),
                'currentUser' => null,
                'isAdmin' => false
            ]);
        }
    }

    public function actionCreate()
    {
        $model = new LdapUser();
        $model->scenario = 'create'; // Set scenario before loading data
        
        if ($model->load(Yii::$app->request->post())) {
            Yii::debug("Form data received: " . print_r($model->attributes, true));
            
            // Set CN from sAMAccountName if not set
            if (empty($model->cn) && !empty($model->sAMAccountName)) {
                $model->cn = $model->sAMAccountName;
            }
            
            // Set password from newPassword if not set
            if (empty($model->password) && !empty($model->newPassword)) {
                $model->password = $model->newPassword;
            }
            
            if ($model->validate()) {
                try {
                    $ldap = new LdapHelper();
                    
                    // Prepare user data for LDAP
                    $userData = [
                        'cn' => [$model->cn],
                        'sAMAccountName' => [$model->sAMAccountName],
                        'displayName' => [$model->cn],
                        'department' => [$model->department],
                        'mail' => [$model->mail],
                        'telephoneNumber' => [$model->telephoneNumber],
                        'userAccountControl' => [512], // Normal account
                        'objectClass' => ['top', 'person', 'organizationalPerson', 'user'],
                    ];

                    // Create user DN with fixed OU
                    $userDn = "CN={$model->cn},OU=rpp-register,DC=rpphosp,DC=local";

                    // Create user in LDAP
                    if ($ldap->createUser($userDn, $userData, $model->password)) {
                        Yii::$app->session->setFlash('success', 'สร้างผู้ใช้งานสำเร็จ');
                        return $this->redirect(['view', 'cn' => $model->cn]);
                    } else {
                        Yii::error("Failed to create user in LDAP. User data: " . print_r($userData, true));
                        Yii::$app->session->setFlash('error', 'ไม่สามารถสร้างผู้ใช้งานได้ กรุณาตรวจสอบข้อมูล');
                    }
                } catch (\Exception $e) {
                    Yii::error("Exception while creating user: " . $e->getMessage());
                    Yii::error("Stack trace: " . $e->getTraceAsString());
                    Yii::$app->session->setFlash('error', 'ไม่สามารถสร้างผู้ใช้งานได้: ' . $e->getMessage());
                }
            } else {
                Yii::error("Model validation failed: " . print_r($model->errors, true));
                Yii::$app->session->setFlash('error', 'กรุณาตรวจสอบข้อมูลให้ถูกต้อง');
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    } 

    public function actionUpdate($cn)
    {
        // Check if user has permission to update users
        if (!$this->hasPermission('update')) {
            Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ในการแก้ไขข้อมูลผู้ใช้');
            return $this->redirect(['ou-user']);
        }

        $model = new LdapUser();
        $model->cn = $cn;
        $model->scenario = 'update'; // Set scenario before loading data
        
        // Load the current user data
        if (!$model->loadFromLdap()) {
            Yii::error("Failed to load user data for CN: $cn");
            if (Yii::$app->request->isAjax) {
                return $this->asJson(['success' => false, 'message' => 'User not found']);
            }
            Yii::$app->session->setFlash('error', 'User not found');
            return $this->redirect(['ou-user']);
        }

        if ($model->load(Yii::$app->request->post())) {
            Yii::debug("Form data received: " . print_r($model->attributes, true));
            
            // Validate the model first
            if (!$model->validate()) {
                $errors = [];
                foreach ($model->errors as $field => $fieldErrors) {
                    $errors = array_merge($errors, $fieldErrors);
                }
                
                if (Yii::$app->request->isAjax) {
                    return $this->asJson([
                        'success' => false,
                        'message' => implode(', ', $errors)
                    ]);
                }
                
                Yii::$app->session->setFlash('error', implode(', ', $errors));
                return $this->render('update', [ 'model' => $model ]);
            }
            
            // Additional server-side validation checks
            $validationErrors = [];
            
            // Check for duplicate username (if changed)
            if (!empty($model->sAMAccountName) && $model->sAMAccountName !== $cn) {
                $ldap = new LdapHelper();
                $existingUser = $ldap->getUser($model->sAMAccountName);
                if ($existingUser) {
                    $validationErrors[] = 'Username นี้มีอยู่แล้วในระบบ';
                }
            }
            
            if (!empty($validationErrors)) {
                if (Yii::$app->request->isAjax) {
                    return $this->asJson([
                        'success' => false,
                        'message' => implode(', ', $validationErrors)
                    ]);
                }
                Yii::$app->session->setFlash('error', implode(', ', $validationErrors));
                return $this->render('update', [ 'model' => $model ]);
            }

            try {
                // Only include fields that are actually in the form
                $updateData = [];
                $fields = ['sAMAccountName', 'displayName', 'department', 'title', 'mail', 'physicalDeliveryOfficeName', 'telephoneNumber'];
                foreach ($fields as $field) {
                    if (isset($model->$field)) {
                        $updateData[$field] = $model->$field;
                        Yii::debug("Including field $field with value: {$model->$field}");
                    }
                }

                // Handle reset password checkbox: if checked, set default newPassword = 1234
                $resetPassword = Yii::$app->request->post('resetPassword', null);
                if ($resetPassword !== null) {
                    $updateData['newPassword'] = '1234';
                    // Ensure confirmPassword matches to satisfy model validation flow where relevant
                    $model->newPassword = '1234';
                    $model->confirmPassword = '1234';
                    Yii::debug("Reset password checkbox selected. Defaulting password to 1234");
                }

                if (!empty($updateData)) {
                    Yii::debug("Updating user with data (via LdapHelper): " . print_r($updateData, true));
                    $ldap = new LdapHelper();
                    // Use original identifier to locate entry when renaming sAMAccountName
                    $originalSam = method_exists($model, 'getOldAttribute') ? $model->getOldAttribute('sAMAccountName') : null;
                    $principal = !empty($originalSam) ? $originalSam : $model->cn;
                    
                    // Ensure all values are strings, not arrays
                    $cleanUpdateData = [];
                    foreach ($updateData as $key => $value) {
                        if (is_array($value)) {
                            $cleanUpdateData[$key] = isset($value[0]) ? $value[0] : '';
                        } else {
                            $cleanUpdateData[$key] = $value;
                        }
                    }
                    
                    $result = $ldap->updateUser($principal, $cleanUpdateData);
                    if ($result) {
                        // Log the update action
                        $currentUser = $this->getCurrentUserLdapData();
                        $currentUsername = $currentUser['samaccountname'] ?? 'Unknown';
                        Yii::info("User {$currentUsername} updated user {$model->cn} with data: " . json_encode($updateData), 'ldap');
                        
                        // Reload model from LDAP to reflect latest data
                        $model->loadFromLdap();

                        if (Yii::$app->request->isAjax) {
                            // Build response user payload
                            $user = $ldap->getUser($model->cn);
                            
                            // Handle LDAP array format
                            $userDn = '';
                            if (isset($user['distinguishedname'])) {
                                $userDn = is_array($user['distinguishedname']) ? $user['distinguishedname'][0] : $user['distinguishedname'];
                            }
                            
                            // Build OU display
                            $ouDisplay = '';
                            if (!empty($userDn)) {
                                $dnParts = array_map('trim', explode(',', (string)$userDn));
                                $ouPath = [];
                                foreach ($dnParts as $part) {
                                    if (stripos($part, 'OU=') === 0) { $ouPath[] = substr($part, 3); }
                                }
                                if (!empty($ouPath)) {
                                    $ouPath = array_reverse($ouPath);
                                    $ouDisplay = count($ouPath) > 1 ? ($ouPath[0] . ' / ' . $ouPath[1]) : $ouPath[0];
                                }
                            }
                            
                            // Extract user data safely
                            $username = '';
                            $displayname = '';
                            $department = '';
                            $title = '';
                            $email = '';
                            
                            if (isset($user['samaccountname'])) {
                                $username = is_array($user['samaccountname']) ? $user['samaccountname'][0] : $user['samaccountname'];
                            }
                            if (isset($user['displayname'])) {
                                $displayname = is_array($user['displayname']) ? $user['displayname'][0] : $user['displayname'];
                            }
                            if (isset($user['department'])) {
                                $department = is_array($user['department']) ? $user['department'][0] : $user['department'];
                            }
                            if (isset($user['title'])) {
                                $title = is_array($user['title']) ? $user['title'][0] : $user['title'];
                            }
                            if (isset($user['mail'])) {
                                $email = is_array($user['mail']) ? $user['mail'][0] : $user['mail'];
                            }
                            
                            return $this->asJson([
                                'success' => true,
                                'message' => 'อัปเดตข้อมูลผู้ใช้สำเร็จ',
                                'showModal' => true,
                                'user' => [
                                    'username' => $username ?: $model->cn,
                                    'displayname' => $displayname,
                                    'department' => $department,
                                    'title' => $title,
                                    'email' => $email,
                                    'ou' => $userDn,
                                    'ouDisplay' => $ouDisplay,
                                ]
                            ]);
                        }

                        Yii::$app->session->setFlash('success', 'อัปเดตข้อมูลผู้ใช้สำเร็จ');
                        return $this->redirect(['update', 'cn' => $model->cn]);
                    } else {
                        if (Yii::$app->request->isAjax) {
                            return $this->asJson([
                                'success' => false,
                                'message' => 'Failed to update user. Please check the logs for details.'
                            ]);
                        }
                        Yii::error("Failed to update user via LdapHelper. Update data: " . print_r($updateData, true));
                        Yii::$app->session->setFlash('error', 'Failed to update user. Please check the logs for details.');
                    }
                } else {
                    Yii::debug("No changes detected in form data");
                    if (Yii::$app->request->isAjax) {
                        return $this->asJson(['success' => true, 'message' => 'No changes were made.']);
                    }
                    Yii::$app->session->setFlash('info', 'No changes were made to the user.');
                    return $this->redirect(['ou-user']);
                }
            } catch (\Exception $e) {
                Yii::error("Exception while updating user: " . $e->getMessage());
                Yii::error("Stack trace: " . $e->getTraceAsString());
                if (Yii::$app->request->isAjax) {
                    return $this->asJson(['success' => false, 'message' => 'Failed to update user: ' . $e->getMessage()]);
                }
                Yii::$app->session->setFlash('error', 'Failed to update user: ' . $e->getMessage());
            }
        }

        return $this->render('update', [ 'model' => $model ]);
    }

    public function actionDelete($cn)
    {
        try {
            // Check if user has permission
            if (!$this->hasPermission('delete')) {
                Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ในการลบผู้ใช้');
                return $this->redirect(['ou-register']);
            }

            // Validate CN parameter
            if (empty($cn)) {
                Yii::$app->session->setFlash('error', 'ไม่พบข้อมูลผู้ใช้ที่ต้องการลบ');
                return $this->redirect(['ou-register']);
            }

            $ldap = new LdapHelper();
            
            // Check if user exists first
            $user = $ldap->getUser($cn);
            if (!$user) {
                Yii::$app->session->setFlash('error', 'ไม่พบผู้ใช้ที่ต้องการลบ');
                return $this->redirect(['ou-register']);
            }

            // Get user's current DN from the actual user data
            $userDn = isset($user['distinguishedname'][0]) ? $user['distinguishedname'][0] : "CN={$cn},OU=rpp-register,DC=rpphosp,DC=local";
            
            // Delete the user
            if ($ldap->deleteUser($userDn)) {
                Yii::$app->session->setFlash('success', 'ลบผู้ใช้ ' . $cn . ' สำเร็จ');
                Yii::info("User {$cn} deleted successfully", 'ldap');
            } else {
                throw new \Exception('ไม่สามารถลบผู้ใช้ได้ กรุณาตรวจสอบสิทธิ์การเข้าถึง');
            }

            return $this->redirect(['ou-register']);

        } catch (\Exception $e) {
            Yii::error("LDAP Error in delete: " . $e->getMessage());
            Yii::$app->session->setFlash('error', 'เกิดข้อผิดพลาดในการลบผู้ใช้: ' . $e->getMessage());
            return $this->redirect(['ou-register']);
        }
    }

    public function actionView($cn)
    {
        try {
            $ldap = new \common\components\LdapHelper();
            $user = $ldap->getUser($cn);

            if (!$user) {
                throw new NotFoundHttpException("User not found.");
            }

            return $this->render('view', [
                'user' => $user,
                'model' => new LdapUser()
            ]);
        } catch (\Exception $e) {
            Yii::error("LDAP Error in view: " . $e->getMessage());
            Yii::$app->session->setFlash('error', "Error viewing user: " . $e->getMessage());
            return $this->redirect(['index']);
        }
    }
    
    /**
     * Moves a user to a different organizational unit
     * @param string $cn The common name of the user to move
     * @return \yii\web\Response
     */
    public function actionMove($cn)
    {
        $model = new LdapUser();
        $ldap = new LdapHelper();
        $user = $ldap->getUser($cn);

        if (!$user) {
            Yii::$app->session->setFlash('error', "User not found: $cn");
            return $this->redirect(['ou-user']);
        }

        // Get all available OUs from the entire domain
        $allOUs = $ldap->getAllOUs();
        
        // Format OUs for the dropdown with real-time user count
        $subOus = [];
        foreach ($allOUs as $ou) {
            // Get real-time user count for this OU
            $userCount = 0;
            try {
                $users = $ldap->getUsersByOu($ou['dn']);
                $userCount = is_array($users) ? count($users) : 0;
            } catch (\Exception $e) {
                Yii::error("Error getting user count for OU {$ou['dn']}: " . $e->getMessage());
                $userCount = 0;
            }
            
            // Create hierarchical path display
            $dnParts = explode(',', $ou['dn']);
            $hierarchicalPath = '';
            foreach ($dnParts as $part) {
                if (strpos($part, 'OU=') === 0) {
                    $ouName = substr($part, 3);
                    $hierarchicalPath = $ouName . ($hierarchicalPath ? ' → ' . $hierarchicalPath : '');
                }
            }
            
            $subOus[] = [
                'ou' => $ou['ou'],
                'dn' => $ou['dn'],
                'label' => $ou['ou'] . ' (' . $ou['type'] . ')',
                'full_label' => $hierarchicalPath . ' (' . $ou['type'] . ')',
                'type' => $ou['type'],
                'icon' => $ou['icon'],
                'badge' => $ou['badge'],
                'user_count' => $userCount,
                'hierarchical_path' => $hierarchicalPath
            ];
        }

        if ($model->load(Yii::$app->request->post())) {
            try {
                // Validate that a target OU was selected
                if (empty($model->organizationalUnit)) {
                    Yii::$app->session->setFlash('error', 'กรุณาเลือก Organizational Unit ที่ต้องการย้าย');
                    return $this->render('move', [
                        'model' => $model,
                        'user' => $user,
                        'subOus' => $subOus,
                    ]);
                }
                
                // Use LdapHelper's moveUser method
                if ($ldap->moveUser($cn, $model->organizationalUnit)) {
                    Yii::$app->session->setFlash('success', "ผู้ใช้ $cn ถูกย้ายเรียบร้อยแล้ว");
                    return $this->redirect(['ou-user']);
                } else {
                    $error = ldap_error($ldap->getConnection());
                    Yii::$app->session->setFlash('error', "ไม่สามารถย้ายผู้ใช้ได้: $error");
                }
            } catch (\Exception $e) {
                Yii::error("Error moving user: " . $e->getMessage());
                Yii::$app->session->setFlash('error', "เกิดข้อผิดพลาดในการย้ายผู้ใช้: " . $e->getMessage());
            }
        }

        return $this->render('move', [
            'model' => $model,
            'user' => $user,
            'subOus' => $subOus,
        ]);
    }

    /**
     * Toggles the account status (enabled/disabled) for a user
     * @param string $cn The common name of the user
     * @param bool $enable Whether to enable or disable the account
     * @return \yii\web\Response
     */
    public function actionToggleStatus($cn = null, $enable = null)
    {
        try {
            // Get parameters from POST request for AJAX calls
            if (Yii::$app->request->isPost) {
                $cn = Yii::$app->request->post('cn', $cn);
                $samaccountname = Yii::$app->request->post('samaccountname', null);
                $enable = Yii::$app->request->post('enable', $enable);
                
                // Prefer sAMAccountName over CN for better search accuracy
                if (!empty($samaccountname)) {
                    $cn = $samaccountname;
                }
            }
            
            // Validate CN parameter
            if (empty($cn)) {
                if (Yii::$app->request->isAjax) {
                    return $this->asJson([
                        'success' => false,
                        'message' => 'User CN is required.'
                    ]);
                }
                throw new \yii\web\BadRequestHttpException("User CN is required.");
            }
            
            // Convert enable parameter to boolean
            // Handle both string ('0', '1') and numeric (0, 1) values
            if ($enable !== null) {
                // If it's a string '0' or '1', convert properly
                if (is_string($enable)) {
                    $enable = ($enable === '1' || $enable === 'true');
                } else {
                    $enable = (bool) $enable;
                }
            } else {
                // Default to false if not provided
                $enable = false;
            }
            
            Yii::debug("Toggle status: CN=$cn, Enable (raw)=" . var_export($enable, true) . ", Enable (bool)=" . ($enable ? 'true' : 'false'));
            Yii::debug("Toggle status: samaccountname=" . var_export($samaccountname, true));
            
            // Use LdapHelper directly instead of model for better performance
            $ldap = new LdapHelper();
            
            // For AJAX requests, skip user lookup and go directly to status change
            if (Yii::$app->request->isAjax) {
                Yii::debug("Toggle status AJAX request: CN=$cn, Enable=" . ($enable ? 'true' : 'false'));
                
                // Direct status change without user lookup
                // Use identifier (sAMAccountName preferred) for better accuracy
                $identifier = !empty($samaccountname) ? $samaccountname : $cn;
                Yii::debug("Using identifier for status change: $identifier");
                $result = $ldap->setAccountStatus($identifier, $enable);
                Yii::debug("setAccountStatus result: " . ($result ? 'true' : 'false'));
                
                if ($result) {
                    $newStatus = $enable ? 'enabled' : 'disabled';
                    $newStatusText = $enable ? 'Enabled' : 'Disabled';
                    $message = "User $cn has been successfully " . ($enable ? "enabled" : "disabled") . ".";
                    
                    Yii::debug("Toggle status success: $message");
                    
                    return $this->asJson([
                        'success' => true,
                        'message' => $message,
                        'newStatus' => $newStatus,
                        'newStatusText' => $newStatusText,
                        'debug' => [
                            'cn' => $cn,
                            'enable' => $enable,
                            'samaccountname' => $samaccountname ?? 'not provided'
                        ]
                    ]);
                } else {
                    // Get more detailed error information
                    $ldapError = ldap_error($ldap->getConnection());
                    $ldapErrno = ldap_errno($ldap->getConnection());
                    $message = "Failed to " . ($enable ? "enable" : "disable") . " user $cn.";
                    if ($ldapError) {
                        $message .= " LDAP Error: $ldapError (Code: $ldapErrno)";
                    }
                    
                    Yii::error("Toggle status failed: $message");
                    
                    return $this->asJson([
                        'success' => false,
                        'message' => $message,
                        'error' => $ldapError,
                        'errno' => $ldapErrno,
                        'debug' => [
                            'cn' => $cn,
                            'enable' => $enable,
                            'samaccountname' => $samaccountname ?? 'not provided'
                        ]
                    ]);
                }
            }
            
            // For non-AJAX requests, use the original logic
            $model = new LdapUser();
            $user = $ldap->getUser($cn);
            
            if (!$user) {
                throw new NotFoundHttpException("User not found.");
            }
            
            // If enable parameter is not provided, toggle the current state
            if ($enable === null) {
                $userAccountControl = isset($user['useraccountcontrol'][0]) ? intval($user['useraccountcontrol'][0]) : 0;
                $ACCOUNTDISABLE = 0x0002;
                $enable = !($userAccountControl & $ACCOUNTDISABLE);
            }
            
            if ($model->setAccountStatus($cn, $enable)) {
                $message = "User $cn has been successfully " . ($enable ? "enabled" : "disabled") . ".";
                Yii::$app->session->setFlash('success', $message);
            } else {
                $message = "Failed to " . ($enable ? "enable" : "disable") . " user $cn.";
                Yii::$app->session->setFlash('error', $message);
            }
            
        } catch (\Exception $e) {
            Yii::error("Error in toggle status: " . $e->getMessage());
            
            if (Yii::$app->request->isAjax) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]);
            }
            
            Yii::$app->session->setFlash('error', 'An error occurred: ' . $e->getMessage());
        }
        
        // For non-AJAX requests, redirect back to the same page (ou-user)
        return $this->redirect(['ou-user']);
    }

    /**
     * Get user data for AJAX refresh
     * @param string $cn The common name of the user
     * @return \yii\web\Response
     */
    public function actionGetUserData($cn = null)
    {
        try {
            // Get CN from POST request for AJAX calls
            if (Yii::$app->request->isPost) {
                $cn = Yii::$app->request->post('cn', $cn);
            }
            
            if (empty($cn)) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'User CN is required.'
                ]);
            }
            
            $ldap = new LdapHelper();
            $user = $ldap->getUser($cn);
            
            if (!$user) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'User not found.'
                ]);
            }
            
            // Extract OU display information
            $userDn = $user['distinguishedname'] ?? '';
            $ouDisplay = '';
            $ouPath = [];
            $dnParts = array_map('trim', explode(',', (string)$userDn));
            
            // Collect all OUs in the DN
            foreach ($dnParts as $part) {
                if (stripos($part, 'OU=') === 0) {
                    $ouName = substr($part, 3);
                    $ouPath[] = $ouName;
                }
            }
            
            // Create hierarchical path
            if (!empty($ouPath)) {
                $ouPath = array_reverse($ouPath); // Reverse to get parent -> child order
                if (count($ouPath) > 1) {
                    $ouDisplay = $ouPath[0] . ' / ' . $ouPath[1];
                } else {
                    $ouDisplay = $ouPath[0];
                }
            }
            
            // Fallback to user's ou attribute
            if ($ouDisplay === '' && !empty($user['ou'])) {
                $ouDisplay = $user['ou'];
            }
            
            // Check account status
            $userAccountControl = isset($user['useraccountcontrol'][0]) ? intval($user['useraccountcontrol'][0]) : 0;
            $ACCOUNTDISABLE = 0x0002;
            $isDisabled = ($userAccountControl & $ACCOUNTDISABLE) ? true : false;
            
            return $this->asJson([
                'success' => true,
                'user' => [
                    'username' => $user['samaccountname'] ?? '',
                    'displayname' => $user['displayname'] ?? '',
                    'department' => $user['department'] ?? '',
                    'title' => $user['title'] ?? '',
                    'email' => $user['mail'] ?? '',
                    'ou' => $userDn,
                    'ouDisplay' => $ouDisplay,
                    'status' => $isDisabled ? 'disabled' : 'enabled'
                ]
            ]);
            
        } catch (\Exception $e) {
            Yii::error("Error getting user data: " . $e->getMessage());
            return $this->asJson([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Displays the LDAP Organizational Unit structure
     * @return string
     */
    public function actionOuUser()
    {
        // Check if user has view permission (admin or superuser with view)
        $permissionManager = new PermissionManager();
        if (!$permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_VIEW)) {
            Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้ (ต้องมีสิทธิ์ดูข้อมูลผู้ใช้ LDAP)');
            return $this->redirect(['index']);
        }
        
        $ldap = new \common\components\LdapHelper();
        
        // Get all OUs from the entire domain
        $allOUs = $ldap->getAllOUs();
        
        // Get users from all OUs in the domain
        $ouUsers = [];
        $allDomainUsers = [];
        $ouStats = [];
        
        foreach ($allOUs as $ou) {
            try {
                // Skip rpp-register OU - users should not be displayed in this page
                if (stripos($ou['dn'], 'OU=rpp-register') !== false) {
                    Yii::debug("Skipping OU: {$ou['dn']} (rpp-register OU excluded from ou-user page)");
                    continue;
                }
                
                $users = $ldap->getUsersByOu($ou['dn']);
                if (!empty($users)) {
                    // Sort users by display name
                    usort($users, function($a, $b) {
                        $nameA = isset($a['displayname'][0]) ? $a['displayname'][0] : '';
                        $nameB = isset($b['displayname'][0]) ? $b['displayname'][0] : '';
                        return strcasecmp($nameA, $nameB);
                    });
                    
                    $ouUsers[$ou['dn']] = $users;
                    $allDomainUsers = array_merge($allDomainUsers, $users);
                    
                    // Collect OU statistics
                    $ouStats[] = [
                        'ou' => $ou['ou'],
                        'dn' => $ou['dn'],
                        'type' => $ou['type'],
                        'user_count' => count($users),
                        'icon' => $ou['icon'],
                        'badge' => $ou['badge']
                    ];
                }
            } catch (\Exception $e) {
                Yii::error("Error getting users from OU {$ou['dn']}: " . $e->getMessage());
                continue;
            }
        }
        
        // Create main OU info for display
        $mainOu = [
            'dn' => 'All OUs',
            'ou' => 'All Users',
            'description' => 'All Users from Domain rpphosp.local',
            'icon' => 'fas fa-globe',
            'label' => 'Domain Users',
            'badge' => 'bg-success'
        ];
        
        // Get sub OUs (all OUs for display purposes)
        $subOus = $ouStats;
        
        return $this->render('ou-user', [
            'mainOu' => $mainOu,
            'subOus' => $subOus,
            'ouUsers' => $ouUsers,
            'allDomainUsers' => $allDomainUsers,
            'ouStats' => $ouStats,
            'pagination' => [],
            'currentUser' => $this->getCurrentUserLdapData(),
            'isAdmin' => $this->hasPermission('ou-user')
        ]);
    }
    public function actionOuRegister()
    {
        // Check if user has admin permissions
        $permissionManager = new PermissionManager();
        if (!$permissionManager->isLdapAdmin()) {
            Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้ เฉพาะผู้ดูแลระบบเท่านั้น');
            return $this->redirect(['index']);
        }
        
        $ldap = new \common\components\LdapHelper();
        
        // Get main OU info for Register OU under rpp-user
        $mainOu = [
            'dn' => 'OU=rpp-register,DC=rpphosp,DC=local',
            'ou' => 'Register',
            'description' => 'Registration Organizational Unit',
            'icon' => 'fas fa-user-plus',
            'label' => 'Registration OU',
            'badge' => 'bg-primary'
        ];
        
        // Get users for the Register OU
        $ouUsers = [];
        $mainOuUsers = $ldap->getUsersByOu($mainOu['dn']);
        if (!empty($mainOuUsers)) {
            // Sort users by display name
            usort($mainOuUsers, function($a, $b) {
                $nameA = isset($a['displayname'][0]) ? $a['displayname'][0] : '';
                $nameB = isset($b['displayname'][0]) ? $b['displayname'][0] : '';
                return strcasecmp($nameA, $nameB);
            });
            $ouUsers[$mainOu['dn']] = $mainOuUsers;
        }
        
        return $this->render('ou-register', [
            'mainOu' => $mainOu,
            'subOus' => [], // No sub OUs needed
            'ouUsers' => $ouUsers,
            'currentUser' => $this->getCurrentUserLdapData(),
            'isAdmin' => $this->hasPermission('ou-register')
        ]);
    }

    // Removed manage-ou feature per request

    // Removed update-ou feature per request

    /**
     * Displays users outside of any OU
     * @return string
     */
    public function actionOuOuts()
    {
        try {
            $ldap = new LdapHelper();
            
            // Get current page from request
            $page = Yii::$app->request->get('page', 1);
            $pageSize = 25;
            
            // Get users with pagination
            $result = $ldap->getUsersOutsideOUs($page, $pageSize);
            $users = $result['users'];
            
            // Sort users by display name
            if (!empty($users)) {
                usort($users, function($a, $b) {
                    $nameA = isset($a['displayname'][0]) ? $a['displayname'][0] : '';
                    $nameB = isset($b['displayname'][0]) ? $b['displayname'][0] : '';
                    return strcasecmp($nameA, $nameB);
                });
            }
            
            // Create pagination
            $pagination = new \yii\data\Pagination([
                'totalCount' => $result['totalCount'],
                'pageSize' => $pageSize,
                'pageSizeParam' => false,
            ]);
            
            return $this->render('ou-outs', [
                'users' => $users,
                'pagination' => $pagination,
                'currentUser' => $this->getCurrentUserLdapData(),
                'isAdmin' => $this->hasPermission('ou-outs')
            ]);
        } catch (\Exception $e) {
            Yii::error("LDAP Error in ou-outs: " . $e->getMessage());
            Yii::$app->session->setFlash('error', "Error viewing users outside OUs: " . $e->getMessage());
            return $this->redirect(['index']);
        }
    }

    // Removed ou-delete feature per request

    /**
     * Action to move user from rpp-register to rpp-user OU
     */
    public function actionMoveToUser($cn = null)
    {
        if (Yii::$app->request->isPost) {
            $cn = Yii::$app->request->post('cn');
        }

        if (!$cn) {
            Yii::$app->session->setFlash('error', 'User CN is required');
            return $this->redirect(['ou-register']);
        }

        try {
            // Get LDAP connection
            $ldap = new LdapHelper();
            
            // Get user's current DN
            $userDn = "CN={$cn},OU=rpp-register,DC=rpphosp,DC=local";
            
            // New DN in rpp-user OU
            $newDn = "CN={$cn},OU=rpp-user,DC=rpphosp,DC=local";
            
            // Check if user exists in rpp-register
            $user = $ldap->getUser($cn);
            if (!$user) {
                throw new \Exception('User not found in rpp-register OU');
            }

            // Check if user is already in rpp-user OU
            if (strpos($user['distinguishedname'], 'OU=rpp-user') !== false) {
                throw new \Exception('User is already in rpp-user OU');
            }

            if (Yii::$app->request->isPost) {
                // Move user to rpp-user OU
                $result = $ldap->moveUser($userDn, $newDn);
                
                if ($result) {
                    Yii::$app->session->setFlash('success', 'User moved successfully to rpp-user OU');
                    
                    // Log the move operation
                    Yii::info("User {$cn} moved from rpp-register to rpp-user OU", 'ldap');
                } else {
                    throw new \Exception('Failed to move user');
                }

                if (Yii::$app->request->isAjax) {
                    return $this->asJson(['success' => true]);
                }
                
                return $this->redirect(['ou-register']);
            }

            // If not POST, render the move form
            return $this->render('move-to-user', [
                'user' => $user
            ]);

        } catch (\Exception $e) {
            Yii::error("LDAP Error in move-to-user: " . $e->getMessage());
            Yii::$app->session->setFlash('error', $e->getMessage());
            
            if (Yii::$app->request->isAjax) {
                return $this->asJson([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            
            return $this->redirect(['ou-register']);
        }
    }

    // Removed create-ou feature per request

    /**
     * Get groups that a user is currently a member of
     * @param string $userDn The distinguished name of the user
     * @return \yii\web\Response
     */
    public function actionGetUserGroups()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        // Check permission
        $permissionManager = new PermissionManager();
        if (!$permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_VIEW)) {
            Yii::warning("Permission denied for actionGetUserGroups - User: " . (Yii::$app->user->identity->username ?? 'unknown'));
            return ['success' => false, 'message' => 'คุณไม่มีสิทธิ์ในการดูข้อมูลกลุ่ม', 'groups' => []];
        }
        
        $userDn = trim(Yii::$app->request->get('userDn', ''));
        if (empty($userDn)) {
            return ['success' => false, 'message' => 'userDn is required', 'groups' => []];
        }
        
        // Decode URL-encoded DN if needed
        if (strpos($userDn, '%') !== false) {
            $decoded = urldecode($userDn);
            // If decoding changed something and result looks like a DN, use it
            if ($decoded !== $userDn && (strpos($decoded, 'CN=') !== false || strpos($decoded, 'OU=') !== false || strpos($decoded, 'DC=') !== false)) {
                $userDn = $decoded;
            }
        }
        $userDn = trim($userDn);
        
        // Basic validation - DN should not be empty
        if (empty($userDn)) {
            return ['success' => false, 'message' => 'Invalid user DN', 'groups' => []];
        }
        
        // Additional validation - DN should contain at least one of CN=, OU=, or DC=
        if (strpos($userDn, 'CN=') === false && strpos($userDn, 'OU=') === false && strpos($userDn, 'DC=') === false) {
            Yii::error("Invalid user DN format: $userDn");
            return ['success' => false, 'message' => 'Invalid user DN format: DN must contain CN=, OU=, or DC=', 'groups' => []];
        }
        
        try {
            $ldap = new LdapHelper();
            $conn = $ldap->getConnection();
            if (!$conn) {
                return ['success' => false, 'message' => 'LDAP connection failed', 'groups' => []];
            }
            
            // Get user's memberOf attribute
            $userAttrs = ['memberof', 'distinguishedname'];
            $userSr = @ldap_read($conn, $userDn, '(objectClass=*)', $userAttrs);
            
            if (!$userSr) {
                $error = ldap_error($conn);
                $errno = ldap_errno($conn);
                Yii::error("User DN not found: $userDn - Error: $error (Code: $errno)");
                return ['success' => false, 'message' => 'User not found: ' . $error, 'groups' => []];
            }
            
            $userEntries = ldap_get_entries($conn, $userSr);
            if (!isset($userEntries[0])) {
                return ['success' => false, 'message' => 'User not found', 'groups' => []];
            }
            
            $userEntry = $userEntries[0];
            $groups = [];
            
            // Get memberOf attribute
            $memberOf = null;
            if (isset($userEntry['memberof']) && is_array($userEntry['memberof'])) {
                $memberOf = $userEntry['memberof'];
            } elseif (isset($userEntry['memberOf']) && is_array($userEntry['memberOf'])) {
                $memberOf = $userEntry['memberOf'];
            }
            
            if ($memberOf) {
                $memberCount = isset($memberOf['count']) ? intval($memberOf['count']) : 0;
                $baseDn = 'OU=Users-RPP,' . (Yii::$app->params['ldap']['base_dn'] ?? 'DC=rpphosp,DC=local');
                
                for ($i = 0; $i < $memberCount; $i++) {
                    if (!isset($memberOf[$i]) || $memberOf[$i] === 'count') {
                        continue;
                    }
                    
                    $groupDn = trim($memberOf[$i]);
                    
                    // Only include groups from OU=Users-RPP
                    if (stripos($groupDn, $baseDn) === false) {
                        continue;
                    }
                    
                    // Get group details
                    $groupAttrs = ['cn', 'description'];
                    $groupSr = @ldap_read($conn, $groupDn, '(objectClass=group)', $groupAttrs);
                    
                    if ($groupSr) {
                        $groupEntries = ldap_get_entries($conn, $groupSr);
                        if (isset($groupEntries[0])) {
                            $groupEntry = $groupEntries[0];
                            $groups[] = [
                                'dn' => $groupDn,
                                'cn' => isset($groupEntry['cn'][0]) ? $groupEntry['cn'][0] : '',
                                'description' => isset($groupEntry['description'][0]) ? $groupEntry['description'][0] : '',
                            ];
                        }
                    }
                }
            }
            
            return ['success' => true, 'groups' => $groups];
        } catch (\Exception $e) {
            Yii::error("Error getting user groups: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'groups' => []];
        }
    }
    
    /**
     * Get available groups from OU=Users-RPP that user can be added to
     * @return \yii\web\Response
     */
    public function actionGetAvailableGroups()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        // Check permission - ใช้ permission เดียวกับ actionGetUserGroups หรือ PERMISSION_GROUP_MANAGE_MEMBERS
        $permissionManager = new PermissionManager();
        // อนุญาตให้ user ที่มีสิทธิ์ดู LDAP users หรือจัดการ group members
        if (!$permissionManager->hasPermission(PermissionManager::PERMISSION_LDAP_USER_VIEW) && 
            !$permissionManager->hasPermission(PermissionManager::PERMISSION_GROUP_MANAGE_MEMBERS)) {
            Yii::warning("Permission denied for actionGetAvailableGroups - User: " . (Yii::$app->user->identity->username ?? 'unknown'));
            return ['success' => false, 'message' => 'คุณไม่มีสิทธิ์ในการดูรายการกลุ่ม', 'groups' => []];
        }
        
        try {
            $ldap = new LdapHelper();
            $conn = $ldap->getConnection();
            if (!$conn) {
                return ['success' => false, 'message' => 'LDAP connection failed', 'groups' => []];
            }
            
            $baseDn = 'OU=Users-RPP,' . (Yii::$app->params['ldap']['base_dn'] ?? 'DC=rpphosp,DC=local');
            $filter = '(objectClass=group)';
            $attrs = ['cn', 'description', 'groupType'];
            
            $sr = @ldap_list($conn, $baseDn, $filter, $attrs);
            if (!$sr) {
                $error = ldap_error($conn);
                Yii::error("Failed to search groups in $baseDn: $error");
                return ['success' => false, 'message' => 'Failed to search groups: ' . $error, 'groups' => []];
            }
            
            $entries = ldap_get_entries($conn, $sr);
            $groups = [];
            $securityGroupGlobalType = -2147483646; // Security Group - Global
            
            for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
                $e = $entries[$i];
                
                // Check groupType - only Security Group - Global
                $groupType = null;
                if (isset($e['grouptype']) && is_array($e['grouptype'])) {
                    $groupType = isset($e['grouptype'][0]) ? intval($e['grouptype'][0]) : null;
                } elseif (isset($e['groupType']) && is_array($e['groupType'])) {
                    $groupType = isset($e['groupType'][0]) ? intval($e['groupType'][0]) : null;
                }
                
                if ($groupType !== null && $groupType === $securityGroupGlobalType) {
                    $cn = $e['cn'][0] ?? '';
                    
                    // Skip manage Ad_admin group
                    if (strcasecmp(trim($cn), 'manage Ad_admin') === 0) {
                        continue;
                    }
                    
                    $groups[] = [
                        'dn' => $e['dn'] ?? '',
                        'cn' => $cn,
                        'description' => $e['description'][0] ?? '',
                    ];
                }
            }
            
            // Sort by CN
            usort($groups, function($a, $b) {
                return strcasecmp($a['cn'], $b['cn']);
            });
            
            return ['success' => true, 'groups' => $groups];
        } catch (\Exception $e) {
            Yii::error("Error getting available groups: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'groups' => []];
        }
    }
    
    /**
     * Add user to a group
     * @return \yii\web\Response
     */
    public function actionAddUserToGroup()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        // Check permission
        $permissionManager = new PermissionManager();
        if (!$permissionManager->hasPermission(PermissionManager::PERMISSION_GROUP_MANAGE_MEMBERS)) {
            return ['success' => false, 'message' => 'No permission'];
        }
        
        $userDn = trim(Yii::$app->request->post('userDn', ''));
        $groupDn = trim(Yii::$app->request->post('groupDn', ''));
        
        if (empty($userDn) || empty($groupDn)) {
            return ['success' => false, 'message' => 'userDn and groupDn are required'];
        }
        
        // Decode URL-encoded DNs if needed
        if (strpos($userDn, '%') !== false) {
            $decoded = urldecode($userDn);
            if ($decoded !== $userDn && (strpos($decoded, 'CN=') !== false || strpos($decoded, 'OU=') !== false || strpos($decoded, 'DC=') !== false)) {
                $userDn = $decoded;
            }
        }
        
        if (strpos($groupDn, '%') !== false) {
            $decoded = urldecode($groupDn);
            if ($decoded !== $groupDn && (strpos($decoded, 'CN=') !== false || strpos($decoded, 'OU=') !== false || strpos($decoded, 'DC=') !== false)) {
                $groupDn = $decoded;
            }
        }
        
        $userDn = trim($userDn);
        $groupDn = trim($groupDn);
        
        try {
            $ldap = new LdapHelper();
            $conn = $ldap->getConnection();
            if (!$conn) {
                return ['success' => false, 'message' => 'LDAP connection failed'];
            }
            
            // Get current user for audit logging
            $currentUser = Yii::$app->user->identity;
            $currentUsername = $currentUser ? ($currentUser->username ?? 'unknown') : 'unknown';
            $currentUserDisplayName = $currentUser ? ($currentUser->displayName ?? $currentUsername) : 'unknown';
            
            // Verify user exists
            $userSr = @ldap_read($conn, $userDn, '(objectClass=*)', ['cn', 'displayname', 'samaccountname']);
            if (!$userSr) {
                $error = ldap_error($conn);
                Yii::info("AUDIT: Add User to Group Failed - User not found. Group: $groupDn, User DN: $userDn, Performed by: $currentUserDisplayName ($currentUsername), Error: $error", 'audit');
                return ['success' => false, 'message' => 'User not found: ' . $error];
            }
            
            $userEntries = ldap_get_entries($conn, $userSr);
            $userCn = isset($userEntries[0]['cn'][0]) ? $userEntries[0]['cn'][0] : '';
            $userDisplayName = isset($userEntries[0]['displayname'][0]) ? $userEntries[0]['displayname'][0] : $userCn;
            $userSamAccountName = isset($userEntries[0]['samaccountname'][0]) ? $userEntries[0]['samaccountname'][0] : '';
            
            // Verify group exists
            $groupSr = @ldap_read($conn, $groupDn, '(objectClass=group)', ['cn', 'description', 'member']);
            if (!$groupSr) {
                $error = ldap_error($conn);
                Yii::info("AUDIT: Add User to Group Failed - Group not found. Group DN: $groupDn, User: $userDisplayName ($userSamAccountName), Performed by: $currentUserDisplayName ($currentUsername), Error: $error", 'audit');
                return ['success' => false, 'message' => 'Group not found: ' . $error];
            }
            
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
                Yii::info("AUDIT: Add User to Group Failed - Duplicate member. Group: $groupCn ($groupDn), User: $userDisplayName ($userSamAccountName, $userDn), Performed by: $currentUserDisplayName ($currentUsername)", 'audit');
                return ['success' => false, 'message' => 'User is already a member of this group'];
            }
            
            // Add user to group
            $entry = ['member' => [$userDn]];
            $ok = @ldap_mod_add($conn, $groupDn, $entry);
            
            if (!$ok) {
                $error = ldap_error($conn);
                $errno = ldap_errno($conn);
                Yii::info("AUDIT: Add User to Group Failed - LDAP Error. Group: $groupCn ($groupDn), User: $userDisplayName ($userSamAccountName, $userDn), Performed by: $currentUserDisplayName ($currentUsername), Error: $error (Code: $errno)", 'audit');
                
                $errorMessages = [
                    68 => 'Object already exists (user may already be a member)',
                    34 => 'Invalid DN syntax',
                    50 => 'Insufficient access rights',
                    53 => 'Server is unwilling to perform',
                ];
                
                $errorMsg = $errorMessages[$errno] ?? $error;
                return ['success' => false, 'message' => 'Add failed: ' . $errorMsg];
            }
            
            // Audit log: Success
            Yii::info("AUDIT: Add User to Group Success - Group: $groupCn ($groupDn), User: $userDisplayName ($userSamAccountName, $userDn), Performed by: $currentUserDisplayName ($currentUsername), Timestamp: " . date('Y-m-d H:i:s'), 'audit');
            
            return [
                'success' => true,
                'message' => 'เพิ่มผู้ใช้เข้าไปในกลุ่มสำเร็จ',
                'group' => [
                    'cn' => $groupCn,
                    'description' => $groupDescription,
                ],
                'user' => [
                    'cn' => $userCn,
                    'displayName' => $userDisplayName,
                    'samAccountName' => $userSamAccountName,
                ]
            ];
        } catch (\Exception $e) {
            Yii::error("Exception in actionAddUserToGroup: " . $e->getMessage());
            
            $currentUser = Yii::$app->user->identity;
            $currentUsername = $currentUser ? ($currentUser->username ?? 'unknown') : 'unknown';
            $currentUserDisplayName = $currentUser ? ($currentUser->displayName ?? $currentUsername) : 'unknown';
            Yii::info("AUDIT: Add User to Group Failed - Exception. Group DN: $groupDn, User DN: $userDn, Performed by: $currentUserDisplayName ($currentUsername), Exception: " . $e->getMessage(), 'audit');
            
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Remove user from a group
     * @return \yii\web\Response
     */
    public function actionRemoveUserFromGroup()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        // Check permission
        $permissionManager = new PermissionManager();
        if (!$permissionManager->hasPermission(PermissionManager::PERMISSION_GROUP_MANAGE_MEMBERS)) {
            return ['success' => false, 'message' => 'No permission'];
        }
        
        $userDn = trim(Yii::$app->request->post('userDn', ''));
        $groupDn = trim(Yii::$app->request->post('groupDn', ''));
        
        if (empty($userDn) || empty($groupDn)) {
            return ['success' => false, 'message' => 'userDn and groupDn are required'];
        }
        
        // Decode URL-encoded DNs if needed
        if (strpos($userDn, '%') !== false) {
            $decoded = urldecode($userDn);
            if ($decoded !== $userDn && (strpos($decoded, 'CN=') !== false || strpos($decoded, 'OU=') !== false || strpos($decoded, 'DC=') !== false)) {
                $userDn = $decoded;
            }
        }
        
        if (strpos($groupDn, '%') !== false) {
            $decoded = urldecode($groupDn);
            if ($decoded !== $groupDn && (strpos($decoded, 'CN=') !== false || strpos($decoded, 'OU=') !== false || strpos($decoded, 'DC=') !== false)) {
                $groupDn = $decoded;
            }
        }
        
        $userDn = trim($userDn);
        $groupDn = trim($groupDn);
        
        try {
            $ldap = new LdapHelper();
            $conn = $ldap->getConnection();
            if (!$conn) {
                return ['success' => false, 'message' => 'LDAP connection failed'];
            }
            
            // Get current user for audit logging
            $currentUser = Yii::$app->user->identity;
            $currentUsername = $currentUser ? ($currentUser->username ?? 'unknown') : 'unknown';
            $currentUserDisplayName = $currentUser ? ($currentUser->displayName ?? $currentUsername) : 'unknown';
            
            // Get user and group details for audit
            $userSr = @ldap_read($conn, $userDn, '(objectClass=*)', ['cn', 'displayname', 'samaccountname']);
            $userCn = '';
            $userDisplayName = '';
            $userSamAccountName = '';
            if ($userSr) {
                $userEntries = ldap_get_entries($conn, $userSr);
                if (isset($userEntries[0])) {
                    $userCn = isset($userEntries[0]['cn'][0]) ? $userEntries[0]['cn'][0] : '';
                    $userDisplayName = isset($userEntries[0]['displayname'][0]) ? $userEntries[0]['displayname'][0] : $userCn;
                    $userSamAccountName = isset($userEntries[0]['samaccountname'][0]) ? $userEntries[0]['samaccountname'][0] : '';
                }
            }
            
            $groupSr = @ldap_read($conn, $groupDn, '(objectClass=group)', ['cn', 'description']);
            $groupCn = '';
            $groupDescription = '';
            if ($groupSr) {
                $groupEntries = ldap_get_entries($conn, $groupSr);
                if (isset($groupEntries[0])) {
                    $groupCn = isset($groupEntries[0]['cn'][0]) ? $groupEntries[0]['cn'][0] : '';
                    $groupDescription = isset($groupEntries[0]['description'][0]) ? $groupEntries[0]['description'][0] : '';
                }
            }
            
            // Remove user from group
            $entry = ['member' => [$userDn]];
            $ok = @ldap_mod_del($conn, $groupDn, $entry);
            
            if (!$ok) {
                $error = ldap_error($conn);
                $errno = ldap_errno($conn);
                Yii::info("AUDIT: Remove User from Group Failed - LDAP Error. Group: $groupCn ($groupDn), User: $userDisplayName ($userSamAccountName, $userDn), Performed by: $currentUserDisplayName ($currentUsername), Error: $error (Code: $errno)", 'audit');
                
                $errorMessages = [
                    16 => 'No such attribute (user may not be a member)',
                    34 => 'Invalid DN syntax',
                    50 => 'Insufficient access rights',
                    53 => 'Server is unwilling to perform',
                ];
                
                $errorMsg = $errorMessages[$errno] ?? $error;
                return ['success' => false, 'message' => 'Remove failed: ' . $errorMsg];
            }
            
            // Audit log: Success
            Yii::info("AUDIT: Remove User from Group Success - Group: $groupCn ($groupDn), User: $userDisplayName ($userSamAccountName, $userDn), Performed by: $currentUserDisplayName ($currentUsername), Timestamp: " . date('Y-m-d H:i:s'), 'audit');
            
            return [
                'success' => true,
                'message' => 'ลบผู้ใช้ออกจากกลุ่มสำเร็จ',
                'group' => [
                    'cn' => $groupCn,
                    'description' => $groupDescription,
                ],
                'user' => [
                    'cn' => $userCn,
                    'displayName' => $userDisplayName,
                    'samAccountName' => $userSamAccountName,
                ]
            ];
        } catch (\Exception $e) {
            Yii::error("Exception in actionRemoveUserFromGroup: " . $e->getMessage());
            
            $currentUser = Yii::$app->user->identity;
            $currentUsername = $currentUser ? ($currentUser->username ?? 'unknown') : 'unknown';
            $currentUserDisplayName = $currentUser ? ($currentUser->displayName ?? $currentUsername) : 'unknown';
            Yii::info("AUDIT: Remove User from Group Failed - Exception. Group DN: $groupDn, User DN: $userDn, Performed by: $currentUserDisplayName ($currentUsername), Exception: " . $e->getMessage(), 'audit');
            
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
