<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\models\AdUser;
use common\components\PermissionManager;

class AdUserController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view'],
                        'allow' => true,
                        'roles' => ['?', '@'],
                        'matchCallback' => function ($rule, $action) {
                            // Allow guests and authenticated users to view
                            return true;
                        }
                    ],
                    [
                        'actions' => ['create', 'check-username', 'check-name'],
                        'allow' => true,
                        'roles' => ['?', '@'],
                        'matchCallback' => function ($rule, $action) {
                            // Allow both guests and authenticated users to create users
                            // This is for registration system that doesn't require login
                            return true;
                        }
                    ],
                    [
                        'actions' => ['update'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            $permissionManager = new PermissionManager();
                            return $permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_UPDATE);
                        }
                    ],
                    [
                        'actions' => ['delete'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            $permissionManager = new PermissionManager();
                            return $permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_DELETE);
                        }
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $permissionManager = new PermissionManager();
        
        // Check if user has permission to view AD users
        if (!$permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_VIEW)) {
            Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ในการดูข้อมูลผู้ใช้ AD');
            return $this->redirect(['site/index']);
        }
        
        $ldap = new \common\components\LdapHelper();
        $users = $ldap->getUsersByOu('OU=rpp-register,DC=rpphosp,DC=local');

        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels' => $users,
            'pagination' => [
                'pageSize' => 10,
            ],
            'sort' => [
                'attributes' => ['samaccountname','cn', 'username', 'sername', 'email', 'department', 'telephone'],
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($cn)
    {
        $permissionManager = new PermissionManager();
        
        // Check if user has permission to view AD users
        if (!$permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_VIEW)) {
            Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ในการดูข้อมูลผู้ใช้ AD');
            return $this->redirect(['index']);
        }
        
        $ldap = new \common\components\LdapHelper();
        $user = $ldap->getUser($cn);
        
        if (!$user) {
            throw new NotFoundHttpException('ไม่พบผู้ใช้ที่ระบุ');
        }
        
        return $this->render('view', [
            'model' => $user,
        ]);
    }

    public function actionCreate()
    {
        // No permission check needed - registration is open to everyone
        $model = new AdUser();
        // Fetch OUs and restrict to Register OU only
        $ldap = new \common\components\LdapHelper();
        $allOus = $ldap->getOrganizationalUnits(Yii::$app->params['ldap']['base_dn_user']);
        $registerDn = Yii::$app->params['ldap']['base_dn_reg'] ?? 'OU=rpp-register,DC=rpphosp,DC=local';
        $ous = array_values(array_filter($allOus, function($ou) use ($registerDn) {
            return isset($ou['dn']) && strcasecmp($ou['dn'], $registerDn) === 0;
        }));
        if (empty($ous)) {
            // Fallback create synthetic Register OU option if not found
            $ous = [[
                'dn' => $registerDn,
                'ou' => 'rpp-register',
                'label' => 'Registration OU'
            ]];
        }
        // Preselect Register OU in model
        $model->target_ou = $ous[0]['dn'];

        if ($model->load(Yii::$app->request->post())) {
            // Block if Thai Firstname/Lastname already exist in AD
            $firstName = is_string($model->username ?? '') ? trim($model->username) : '';
            $lastName = is_string($model->sername ?? '') ? trim($model->sername) : '';
            if ($firstName !== '' && $lastName !== '') {
                try {
                    $ldap = new \common\components\LdapHelper();
                    $conn = $ldap->getConnection();
                    $config = Yii::$app->params['ldap'];
                    $bases = [];
                    if (isset($config['search_all_ous']) && $config['search_all_ous']) {
                        $bases = [$config['base_dn']];
                    } else {
                        $allowedOus = $config['allowed_ous'] ?? [];
                        if (empty($allowedOus)) {
                            $bases = [
                                $config['base_dn_user'] ?? $config['base_dn'] ?? null,
                                $config['base_dn_reg'] ?? null,
                            ];
                            $bases = array_filter($bases);
                        } else {
                            $bases = $allowedOus;
                        }
                    }

                    $escape = function ($value) {
                        $search = ['\\\\', '*', '(', ')', "\0"];
                        $replace = ['\\\\5c', '\\2a', '\\28', '\\29', '\\00'];
                        return str_replace($search, $replace, $value);
                    };

                    $nameExists = false;
                    foreach ($bases as $baseDn) {
                        $filter = '(&(givenName=' . $escape($firstName) . ')(sn=' . $escape($lastName) . '))';
                        $search = @ldap_search($conn, $baseDn, $filter, ['givenName','sn']);
                        if ($search) {
                            $entries = ldap_get_entries($conn, $search);
                            if ($entries && isset($entries['count']) && $entries['count'] > 0) {
                                $nameExists = true;
                                break;
                            }
                        }
                    }

                    if ($nameExists) {
                        $message = 'ชื่อ-นามสกุลนี้มีในระบบแล้ว กรุณาติดต่อผู้ดูแลระบบ';
                        $model->addError('username', $message);
                        $model->addError('sername', $message);
                        if (Yii::$app->request->isAjax) {
                            return $this->asJson(['success' => false, 'errors' => $model->errors]);
                        }
                        return $this->render('create', [
                            'model' => $model,
                            'ous' => $ous,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Yii::error('Name pre-check error: ' . $e->getMessage());
                }
            }
            if (!$model->validate()) {
                if (Yii::$app->request->isAjax) {
                    return $this->asJson([
                        'success' => false,
                        'errors' => $model->errors
                    ]);
                }
                return $this->render('create', [
                    'model' => $model,
                    'ous' => $ous,
                ]);
            }

            // ความยาวรหัสผ่านบังคับผ่าน Model rule แล้ว ไม่ต้องตรวจซ้ำที่ Controller

            if ($model->createUser()) {
                if (Yii::$app->request->isAjax) {
                    return $this->asJson(['success' => true]);
                }
                Yii::$app->session->setFlash('success', 'เพิ่มผู้ใช้สำเร็จ รหัสผ่านถูกตั้งค่าเรียบร้อยแล้ว คุณสามารถ login เพื่อตรวจสอบสถานะการลงทะเบียนได้');
                return $this->redirect(['site/index']);
            } else {
                // Log the specific error for debugging
                if (!empty($model->errors)) {
                    Yii::error('User creation failed with errors: ' . print_r($model->errors, true));
                }
            }
        }

        return $this->render('create', [
            'model' => $model,
            'ous' => $ous,
        ]);
    }

    public function actionCheckUsername($username = null)
    {
        // No permission check needed - username checking is open for registration
        Yii::$app->response->format = Response::FORMAT_JSON;
        if ($username === null || trim($username) === '') {
            return ['success' => false, 'available' => false, 'message' => 'Username is required'];
        }

        try {
            $ldap = new \common\components\LdapHelper();
            $conn = $ldap->getConnection();
            $config = Yii::$app->params['ldap'];
            $bases = [];
            
            // Check if we should search all OUs
            if (isset($config['search_all_ous']) && $config['search_all_ous']) {
                $bases = [$config['base_dn']]; // Search the entire domain
            } else {
                // Use specific OUs if configured
                $allowedOus = $config['allowed_ous'] ?? [];
                if (empty($allowedOus)) {
                    // Fallback to default OUs
                    $bases = [
                        $config['base_dn_user'] ?? $config['base_dn'],
                        $config['base_dn_reg'] ?? null,
                    ];
                    $bases = array_filter($bases); // Remove null values
                } else {
                    $bases = $allowedOus;
                }
            }

            $exists = false;
            foreach ($bases as $baseDn) {
                $filter = '(sAMAccountName=' . $username . ')';
                $search = @ldap_search($conn, $baseDn, $filter, ['sAMAccountName']);
                if ($search) {
                    $entries = ldap_get_entries($conn, $search);
                    if ($entries && isset($entries['count']) && $entries['count'] > 0) {
                        $exists = true;
                        break;
                    }
                }
            }

            return [
                'success' => true,
                'available' => !$exists
            ];
        } catch (\Throwable $e) {
            Yii::error('Username check error: ' . $e->getMessage());
            return ['success' => false, 'available' => false, 'message' => 'Error checking username'];
        }
    }

    public function actionCheckName($firstName = null, $lastName = null)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $firstName = is_string($firstName) ? trim($firstName) : '';
        $lastName = is_string($lastName) ? trim($lastName) : '';

        if ($firstName === '' || $lastName === '') {
            return ['success' => false, 'exists' => false, 'message' => 'firstName and lastName are required'];
        }

        try {
            $ldap = new \common\components\LdapHelper();
            $conn = $ldap->getConnection();
            $config = Yii::$app->params['ldap'];
            $bases = [];

            if (isset($config['search_all_ous']) && $config['search_all_ous']) {
                $bases = [$config['base_dn']];
            } else {
                $allowedOus = $config['allowed_ous'] ?? [];
                if (empty($allowedOus)) {
                    $bases = [
                        $config['base_dn_user'] ?? $config['base_dn'] ?? null,
                        $config['base_dn_reg'] ?? null,
                    ];
                    $bases = array_filter($bases);
                } else {
                    $bases = $allowedOus;
                }
            }

            // Basic escape for LDAP filter special chars
            $escape = function ($value) {
                $search = ['\\', '*', '(', ')', "\0"];
                $replace = ['\\5c', '\\2a', '\\28', '\\29', '\\00'];
                return str_replace($search, $replace, $value);
            };

            $exists = false;
            foreach ($bases as $baseDn) {
                $filter = '(&(givenName=' . $escape($firstName) . ')(sn=' . $escape($lastName) . '))';
                $attributes = ['givenName', 'sn', 'displayName'];
                $search = @ldap_search($conn, $baseDn, $filter, $attributes);
                if ($search) {
                    $entries = ldap_get_entries($conn, $search);
                    if ($entries && isset($entries['count']) && $entries['count'] > 0) {
                        $exists = true;
                        break;
                    }
                }
            }

            return [
                'success' => true,
                'exists' => $exists,
            ];
        } catch (\Throwable $e) {
            Yii::error('Name check error: ' . $e->getMessage());
            return ['success' => false, 'exists' => false, 'message' => 'Error checking name'];
        }
    }

    public function actionUpdate($cn)
    {
        $permissionManager = new PermissionManager();
        
        // Check if user has permission to update AD users
        if (!$permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_UPDATE)) {
            Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ในการแก้ไขข้อมูลผู้ใช้ AD');
            return $this->redirect(['index']);
        }
        
        $ldap = new \common\components\LdapHelper();
        $user = $ldap->getUser($cn);
        
        if (!$user) {
            throw new NotFoundHttpException('ไม่พบผู้ใช้ที่ระบุ');
        }
        
        $model = new AdUser();
        $model->loadUserData($user);
        
        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate()) {
                if ($model->updateUser()) {
                    Yii::$app->session->setFlash('success', 'แก้ไขข้อมูลผู้ใช้สำเร็จ');
                    return $this->redirect(['view', 'cn' => $cn]);
                } else {
                    Yii::$app->session->setFlash('error', 'ไม่สามารถแก้ไขข้อมูลผู้ใช้ได้');
                }
            }
        }
        
        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete($cn)
    {
        $permissionManager = new PermissionManager();
        
        // Check if user has permission to delete AD users
        if (!$permissionManager->hasPermission(PermissionManager::PERMISSION_AD_USER_DELETE)) {
            Yii::$app->session->setFlash('error', 'คุณไม่มีสิทธิ์ในการลบผู้ใช้ AD');
            return $this->redirect(['index']);
        }
        
        $ldap = new \common\components\LdapHelper();
        $user = $ldap->getUser($cn);
        
        if (!$user) {
            throw new NotFoundHttpException('ไม่พบผู้ใช้ที่ระบุ');
        }
        
        if ($ldap->deleteUser($cn)) {
            Yii::$app->session->setFlash('success', 'ลบผู้ใช้สำเร็จ');
        } else {
            Yii::$app->session->setFlash('error', 'ไม่สามารถลบผู้ใช้ได้');
        }
        
        return $this->redirect(['index']);
    }
} 