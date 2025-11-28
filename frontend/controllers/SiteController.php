<?php

namespace frontend\controllers;

use frontend\models\ResendVerificationEmailForm;
use frontend\models\VerifyEmailForm;
use Yii;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\models\LoginForm;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResetPasswordForm;
use frontend\models\SignupForm;
use frontend\models\ContactForm;
use common\components\LdapHelper;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        // Skip account status check for login, logout, auto-logout, and check-account-status actions
        $skipActions = ['login', 'logout', 'auto-logout', 'check-account-status', 'error'];
        if (!in_array($action->id, $skipActions) && !\Yii::$app->user->isGuest) {
            // Check account status and OU from AD
            try {
                $current = \Yii::$app->user->identity;
                $username = method_exists($current, 'getId') ? $current->getId() : null;
                
                if ($username) {
                    $ldap = new LdapHelper();
                    $ldapUser = $ldap->getUser($username);
                    
                    if ($ldapUser) {
                        $getLdapValue = function($key, $default = '') use ($ldapUser) {
                            if (!isset($ldapUser[$key])) {
                                return $default;
                            }
                            if (is_array($ldapUser[$key])) {
                                return $ldapUser[$key][0] ?? $default;
                            }
                            return $ldapUser[$key] ?? $default;
                        };
                        
                        // Check account status
                        $userAccountControl = intval($getLdapValue('useraccountcontrol', 0));
                        $ACCOUNTDISABLE = 0x0002;
                        $isDisabled = ($userAccountControl & $ACCOUNTDISABLE) !== 0;
                        
                        if ($isDisabled) {
                            // Account is disabled - force logout and redirect
                            \Yii::warning("User $username account is disabled in AD - blocking action and forcing logout");
                            \Yii::$app->user->logout(false);
                            \Yii::$app->session->destroy();
                            
                            // If AJAX request, return JSON response
                            if (\Yii::$app->request->isAjax) {
                                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                                \Yii::$app->response->data = [
                                    'success' => false,
                                    'accountDisabled' => true,
                                    'message' => 'บัญชีของคุณถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ'
                                ];
                                \Yii::$app->response->send();
                                return false;
                            }
                            
                            // For regular requests, redirect to login
                            \Yii::$app->session->setFlash('error', 'บัญชีของคุณถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
                            \Yii::$app->response->redirect(\Yii::$app->urlManager->createUrl(['site/login']));
                            return false;
                        }
                        
                        // Check if OU has changed
                        $currentDistinguishedName = $getLdapValue('distinguishedname', '');
                        $sessionData = \Yii::$app->session->get('ldapUserData');
                        $sessionDistinguishedName = $sessionData['distinguishedname'] ?? '';
                        
                        // Compare DNs to detect OU change
                        if (!empty($currentDistinguishedName) && $currentDistinguishedName !== $sessionDistinguishedName) {
                            \Yii::info("User $username OU changed detected - checking access permissions");
                            
                            // Update session with fresh AD data
                            $updatedSessionData = [
                                'cn' => $getLdapValue('cn', $getLdapValue('displayname', '')),
                                'samaccountname' => $getLdapValue('samaccountname', $username),
                                'displayname' => $getLdapValue('displayname', ''),
                                'department' => $getLdapValue('department', ''),
                                'mail' => $getLdapValue('mail', ''),
                                'telephonenumber' => $getLdapValue('telephonenumber', ''),
                                'distinguishedname' => $currentDistinguishedName,
                                'memberof' => isset($ldapUser['memberof']) ? (is_array($ldapUser['memberof']) ? array_slice($ldapUser['memberof'], 1) : []) : [],
                                'useraccountcontrol' => $userAccountControl,
                                'whenchanged' => $getLdapValue('whenchanged', ''),
                                'whencreated' => $getLdapValue('whencreated', ''),
                                'ou' => '',
                            ];
                            
                            // Extract OU from distinguishedName
                            if (!empty($updatedSessionData['distinguishedname']) && preg_match('/OU=([^,]+)/i', $updatedSessionData['distinguishedname'], $m)) {
                                $updatedSessionData['ou'] = $m[1];
                            } elseif (isset($ldapUser['ou'])) {
                                $updatedSessionData['ou'] = is_array($ldapUser['ou']) ? ($ldapUser['ou'][0] ?? '') : $ldapUser['ou'];
                            }
                            
                            // Check if new OU allows access
                            $permissionManager = new \common\components\PermissionManager();
                            $hasAccess = $permissionManager->hasAccessByOu($updatedSessionData);
                            
                            if (!$hasAccess) {
                                // User moved to restricted OU - force logout
                                \Yii::warning("User $username moved to restricted OU - forcing logout");
                                \Yii::$app->user->logout(false);
                                \Yii::$app->session->destroy();
                                
                                // If AJAX request, return JSON response
                                if (\Yii::$app->request->isAjax) {
                                    \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                                    \Yii::$app->response->data = [
                                        'success' => false,
                                        'accessDenied' => true,
                                        'ouChanged' => true,
                                        'message' => 'บัญชีของคุณถูกย้ายไป OU ที่ไม่มีสิทธิ์เข้าถึงระบบ กรุณาติดต่อผู้ดูแลระบบ'
                                    ];
                                    \Yii::$app->response->send();
                                    return false;
                                }
                                
                                // For regular requests, redirect to login
                                \Yii::$app->session->setFlash('error', 'บัญชีของคุณถูกย้ายไป OU ที่ไม่มีสิทธิ์เข้าถึงระบบ กรุณาติดต่อผู้ดูแลระบบ');
                                \Yii::$app->response->redirect(\Yii::$app->urlManager->createUrl(['site/login']));
                                return false;
                            }
                            
                            // User has access - update session and reassign role
                            \Yii::$app->session->set('ldapUserData', $updatedSessionData);
                            $permissionManager->assignRoleToUser($username, $updatedSessionData);
                            
                            \Yii::info("User $username session and permissions updated after OU change");
                            
                            // If AJAX request, return JSON indicating session was refreshed
                            if (\Yii::$app->request->isAjax) {
                                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                                \Yii::$app->response->data = [
                                    'success' => true,
                                    'sessionRefreshed' => true,
                                    'message' => 'Session updated due to OU change'
                                ];
                                // Don't block the request, just inform
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // If check fails, log but allow action to continue
                \Yii::warning("Failed to check account status/OU in beforeAction: " . $e->getMessage());
            }
        }
        
        return parent::beforeAction($action);
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout', 'signup', 'auto-logout'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout', 'auto-logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                    'auto-logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => \yii\web\ErrorAction::class,
            ],
            'captcha' => [
                'class' => \yii\captcha\CaptchaAction::class,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Auto logout endpoint for page close/unload beacons
     * Logs out user immediately when browser is closed
     */
    public function actionAutoLogout()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        if (\Yii::$app->user->isGuest) {
            return ['success' => true, 'message' => 'Already logged out'];
        }
        
        try {
            $username = \Yii::$app->user->identity->username ?? 'Unknown';
            
            // Log the auto-logout event
            Yii::info("Auto-logout triggered for user: $username (browser closed)", 'app');
            
            // Logout user (false = don't destroy session immediately, but we'll do it next)
            \Yii::$app->user->logout(false);
            
            // Destroy session completely to ensure no residual data
            \Yii::$app->session->destroy();
            
            // Clear any LDAP session data
            \Yii::$app->session->remove('ldapUserData');
            
            Yii::debug("Auto-logout completed successfully for user: $username");
            
            return ['success' => true, 'message' => 'Logged out successfully'];
        } catch (\Throwable $e) {
            \Yii::error('AutoLogout failed: ' . $e->getMessage());
            \Yii::error('Stack trace: ' . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Logout failed'];
        }
    }

    /**
     * Check account status endpoint
     * Returns JSON indicating if account is still enabled
     */
    public function actionCheckAccountStatus()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        if (\Yii::$app->user->isGuest) {
            return ['authenticated' => false, 'enabled' => false];
        }

        try {
            $current = \Yii::$app->user->identity;
            $username = method_exists($current, 'getId') ? $current->getId() : null;
            if (!$username) {
                return ['authenticated' => true, 'enabled' => false];
            }

            $ldap = new LdapHelper();
            $ldapUser = $ldap->getUser($username);
            if (!$ldapUser) {
                return ['authenticated' => true, 'enabled' => false];
            }

            // Get userAccountControl
            $getLdapValue = function($key, $default = 0) use ($ldapUser) {
                if (!isset($ldapUser[$key])) {
                    return $default;
                }
                if (is_array($ldapUser[$key])) {
                    return intval($ldapUser[$key][0] ?? $default);
                }
                return intval($ldapUser[$key] ?? $default);
            };
            
            $userAccountControl = $getLdapValue('useraccountcontrol', 0);
            $ACCOUNTDISABLE = 0x0002;
            $isDisabled = ($userAccountControl & $ACCOUNTDISABLE) !== 0;
            
            // Check previous status from session
            $sessionData = \Yii::$app->session->get('ldapUserData');
            $previousUserAccountControl = intval($sessionData['useraccountcontrol'] ?? 0);
            $wasDisabled = ($previousUserAccountControl & $ACCOUNTDISABLE) !== 0;
            $accountReEnabled = $wasDisabled && !$isDisabled;

            if ($isDisabled) {
                // Account is disabled - force logout
                \Yii::warning("User $username account is disabled during status check - forcing logout");
                \Yii::$app->user->logout(false);
                \Yii::$app->session->destroy();
                return ['authenticated' => false, 'enabled' => false, 'accountDisabled' => true];
            }
            
            // If account was re-enabled, update session with fresh AD data
            if ($accountReEnabled) {
                \Yii::info("User $username account was re-enabled in AD - updating session");
                
                $sessionData = [
                    'cn' => $getLdapValue('cn', $getLdapValue('displayname', '')),
                    'samaccountname' => $getLdapValue('samaccountname', $username),
                    'displayname' => $getLdapValue('displayname', ''),
                    'department' => $getLdapValue('department', ''),
                    'mail' => $getLdapValue('mail', ''),
                    'telephonenumber' => $getLdapValue('telephonenumber', ''),
                    'distinguishedname' => $getLdapValue('distinguishedname', ''),
                    'memberof' => isset($ldapUser['memberof']) ? (is_array($ldapUser['memberof']) ? array_slice($ldapUser['memberof'], 1) : []) : [],
                    'useraccountcontrol' => $userAccountControl,
                    'whenchanged' => $getLdapValue('whenchanged', ''),
                    'whencreated' => $getLdapValue('whencreated', ''),
                    'ou' => '',
                ];
                
                // Extract OU from distinguishedName
                if (!empty($sessionData['distinguishedname']) && preg_match('/OU=([^,]+)/i', $sessionData['distinguishedname'], $m)) {
                    $sessionData['ou'] = $m[1];
                } elseif (isset($ldapUser['ou'])) {
                    $sessionData['ou'] = is_array($ldapUser['ou']) ? ($ldapUser['ou'][0] ?? '') : $ldapUser['ou'];
                }
                
                \Yii::$app->session->set('ldapUserData', $sessionData);
                
                return ['authenticated' => true, 'enabled' => true, 'accountReEnabled' => true];
            }
            
            // Check if OU or userAccountControl has changed
            $currentDistinguishedName = $getLdapValue('distinguishedname', '');
            $sessionDistinguishedName = $sessionData['distinguishedname'] ?? '';
            $ouChanged = !empty($currentDistinguishedName) && ($currentDistinguishedName !== $sessionDistinguishedName);
            
            // Update session if userAccountControl or OU changed
            if ($userAccountControl !== $previousUserAccountControl || $ouChanged) {
                $sessionData = [
                    'cn' => $getLdapValue('cn', $getLdapValue('displayname', '')),
                    'samaccountname' => $getLdapValue('samaccountname', $username),
                    'displayname' => $getLdapValue('displayname', ''),
                    'department' => $getLdapValue('department', ''),
                    'mail' => $getLdapValue('mail', ''),
                    'telephonenumber' => $getLdapValue('telephonenumber', ''),
                    'distinguishedname' => $currentDistinguishedName,
                    'memberof' => isset($ldapUser['memberof']) ? (is_array($ldapUser['memberof']) ? array_slice($ldapUser['memberof'], 1) : []) : [],
                    'useraccountcontrol' => $userAccountControl,
                    'whenchanged' => $getLdapValue('whenchanged', ''),
                    'whencreated' => $getLdapValue('whencreated', ''),
                    'ou' => '',
                ];
                
                // Extract OU from distinguishedName
                if (!empty($sessionData['distinguishedname']) && preg_match('/OU=([^,]+)/i', $sessionData['distinguishedname'], $m)) {
                    $sessionData['ou'] = $m[1];
                } elseif (isset($ldapUser['ou'])) {
                    $sessionData['ou'] = is_array($ldapUser['ou']) ? ($ldapUser['ou'][0] ?? '') : $ldapUser['ou'];
                }
                
                // If OU changed, check if new OU allows access
                if ($ouChanged) {
                    $permissionManager = new \common\components\PermissionManager();
                    $hasAccess = $permissionManager->hasAccessByOu($sessionData);
                    
                    if (!$hasAccess) {
                        // User moved to restricted OU - force logout
                        \Yii::warning("User $username moved to restricted OU during status check - forcing logout");
                        \Yii::$app->user->logout(false);
                        \Yii::$app->session->destroy();
                        return ['authenticated' => false, 'enabled' => false, 'accessDenied' => true, 'ouChanged' => true];
                    }
                    
                    // User has access - reassign role
                    \Yii::info("User $username OU changed - reassigning role");
                    $permissionManager->assignRoleToUser($username, $sessionData);
                }
                
                \Yii::$app->session->set('ldapUserData', $sessionData);
                
                return ['authenticated' => true, 'enabled' => true, 'sessionRefreshed' => true, 'ouChanged' => $ouChanged];
            }

            return ['authenticated' => true, 'enabled' => true];
        } catch (\Throwable $e) {
            \Yii::error('CheckAccountStatus failed: ' . $e->getMessage());
            return ['authenticated' => true, 'enabled' => true, 'error' => true];
        }
    }

    /**
     * Lightweight endpoint to refresh current user's LDAP session data.
     * Returns JSON with updated OU and a flag if access should be active.
     */
    public function actionRefreshSession()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        if (\Yii::$app->user->isGuest) {
            return ['authenticated' => false];
        }

        try {
            $current = \Yii::$app->user->identity;
            $username = method_exists($current, 'getId') ? $current->getId() : null;
            if (!$username) {
                return ['authenticated' => true, 'updated' => false];
            }

            $ldap = new LdapHelper();
            // Re-fetch LDAP data by sAMAccountName (login name)
            $ldapUser = $ldap->getUser($username);
            if (!$ldapUser) {
                return ['authenticated' => true, 'updated' => false];
            }

            // Update session cache with complete AD data
            // Handle both array format (LDAP result) and direct format
            $getLdapValue = function($key, $default = '') use ($ldapUser) {
                if (!isset($ldapUser[$key])) {
                    return $default;
                }
                if (is_array($ldapUser[$key])) {
                    return $ldapUser[$key][0] ?? $default;
                }
                return $ldapUser[$key];
            };
            
            $sessionData = [
                'cn' => $getLdapValue('cn', $getLdapValue('displayname', '')),
                'samaccountname' => $getLdapValue('samaccountname', $username),
                'displayname' => $getLdapValue('displayname', ''),
                'department' => $getLdapValue('department', ''),
                'mail' => $getLdapValue('mail', ''),
                'telephonenumber' => $getLdapValue('telephonenumber', ''),
                'distinguishedname' => $getLdapValue('distinguishedname', ''),
                'memberof' => isset($ldapUser['memberof']) ? (is_array($ldapUser['memberof']) ? array_slice($ldapUser['memberof'], 1) : []) : [],
                'useraccountcontrol' => intval($getLdapValue('useraccountcontrol', 0)),
                'whenchanged' => $getLdapValue('whenchanged', ''),
                'whencreated' => $getLdapValue('whencreated', ''),
                'ou' => '',
            ];
            
            // Extract OU from distinguishedName
            if (!empty($sessionData['distinguishedname']) && preg_match('/OU=([^,]+)/i', $sessionData['distinguishedname'], $m)) {
                $sessionData['ou'] = $m[1];
            } elseif (isset($ldapUser['ou'])) {
                $sessionData['ou'] = is_array($ldapUser['ou']) ? ($ldapUser['ou'][0] ?? '') : $ldapUser['ou'];
            }
            
            // Check if OU changed by comparing with existing session data
            $existingSessionData = \Yii::$app->session->get('ldapUserData');
            $existingDistinguishedName = $existingSessionData['distinguishedname'] ?? '';
            $ouChanged = !empty($sessionData['distinguishedname']) && ($sessionData['distinguishedname'] !== $existingDistinguishedName);
            
            // If OU changed, check if new OU allows access
            if ($ouChanged) {
                $permissionManager = new \common\components\PermissionManager();
                $hasAccess = $permissionManager->hasAccessByOu($sessionData);
                
                if (!$hasAccess) {
                    // User moved to restricted OU - force logout
                    \Yii::warning("User $username moved to restricted OU during session refresh - forcing logout");
                    \Yii::$app->user->logout(false);
                    \Yii::$app->session->destroy();
                    return ['authenticated' => false, 'updated' => false, 'accessDenied' => true, 'ouChanged' => true];
                }
                
                // User has access - reassign role
                \Yii::info("User $username OU changed during session refresh - reassigning role");
                $permissionManager->assignRoleToUser($username, $sessionData);
            }
            
            \Yii::$app->session->set('ldapUserData', $sessionData);
            
            // Check if account is still enabled during refresh
            $ACCOUNTDISABLE = 0x0002;
            $isDisabled = ($sessionData['useraccountcontrol'] & $ACCOUNTDISABLE) !== 0;
            if ($isDisabled) {
                \Yii::warning("User $username account is disabled during session refresh - forcing logout");
                \Yii::$app->user->logout(false);
                \Yii::$app->session->destroy();
                return ['authenticated' => false, 'accountDisabled' => true];
            }

            // Derive currentUserOu flag
            $currentUserOu = '';
            if (!empty($sessionData['distinguishedname'])) {
                if (stripos($sessionData['distinguishedname'], 'OU=rpp-register') !== false) {
                    $currentUserOu = 'rpp-register';
                } elseif (stripos($sessionData['distinguishedname'], 'OU=rpp-user') !== false) {
                    $currentUserOu = 'rpp-user';
                } else {
                    $currentUserOu = 'other';
                }
            }

            return [
                'authenticated' => true,
                'updated' => true,
                'currentUserOu' => $currentUserOu,
                'distinguishedName' => $sessionData['distinguishedname'],
                'activeAccess' => $currentUserOu !== 'rpp-register',
                'ouChanged' => $ouChanged,
                'sessionRefreshed' => true,
            ];
        } catch (\Throwable $e) {
            \Yii::error('RefreshSession failed: ' . $e->getMessage());
            return ['authenticated' => true, 'updated' => false, 'error' => true];
        }
    }

    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $ldap = new LdapHelper();
        $totalUsers = 0;
        $totalOus = 0;
        $pendingUsers = [];
        $pendingCount = 0;

        try {
            $allUsers = $ldap->getAllUsers();
            $totalUsers = is_array($allUsers) ? count($allUsers) : 0;
        } catch (\Exception $e) {
            \Yii::error('Failed to fetch all users for dashboard: ' . $e->getMessage());
        }

        try {
            $allOus = $ldap->getAllOUs();
            $totalOus = is_array($allOus) ? count($allOus) : 0;
        } catch (\Exception $e) {
            \Yii::error('Failed to fetch OUs for dashboard: ' . $e->getMessage());
        }

        try {
            $pendingUsers = $ldap->getUsersByOu('OU=rpp-register,DC=rpphosp,DC=local');
            $pendingCount = is_array($pendingUsers) ? count($pendingUsers) : 0;
        } catch (\Exception $e) {
            \Yii::error('Failed to fetch pending users for dashboard: ' . $e->getMessage());
        }

        // Get current user's OU information (and allow on-load session refresh if requested)
        $currentUserOu = '';
        if (!Yii::$app->user->isGuest) {
            $currentUser = Yii::$app->user->identity;
            if (isset($currentUser->distinguishedName)) {
                if (stripos($currentUser->distinguishedName, 'OU=rpp-register') !== false) {
                    $currentUserOu = 'rpp-register';
                } elseif (stripos($currentUser->distinguishedName, 'OU=rpp-user') !== false) {
                    $currentUserOu = 'rpp-user';
                }
            }

            // Optional immediate refresh when query param present
            if (Yii::$app->request->get('refreshSession') === '1') {
                try {
                    $this->actionRefreshSession();
                } catch (\Throwable $e) {
                    \Yii::error('Immediate refreshSession failed: ' . $e->getMessage());
                }
            }
        }

        return $this->render('index', [
            'totalUsers' => $totalUsers,
            'totalOus' => $totalOus,
            'pendingUsers' => $pendingUsers,
            'pendingCount' => $pendingCount,
            'currentUserOu' => $currentUserOu,
        ]);
    }

    /**
     * Logs in a user.
     *
     * @return mixed
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logs out the current user.
     *
     * @return mixed
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return mixed
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail(Yii::$app->params['adminEmail'])) {
                Yii::$app->session->setFlash('success', 'Thank you for contacting us. We will respond to you as soon as possible.');
            } else {
                Yii::$app->session->setFlash('error', 'There was an error sending your message.');
            }

            return $this->refresh();
        }

        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return mixed
     */
    public function actionAbout()
    {
        return $this->render('about');
    }

    /**
     * Signs user up.
     *
     * @return mixed
     */
    public function actionSignup()
    {
        $model = new SignupForm();

        if ($model->load(Yii::$app->request->post()) && $model->signup()) {
            Yii::$app->session->setFlash('success', 'User created successfully.');
            return $this->redirect(['login']);
        }

        if ($model->hasErrors()) {
            Yii::$app->session->setFlash('error', 'Failed to create user. Please check the form for errors.');
        }

        return $this->render('signup', [
            'model' => $model,
        ]);
    }

    /**
     * Requests password reset.
     *
     * @return mixed
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->session->setFlash('success', 'Check your email for further instructions.');

                return $this->goHome();
            }

            Yii::$app->session->setFlash('error', 'Sorry, we are unable to reset password for the provided email address.');
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    /**
     * Resets password.
     *
     * @param string $token
     * @return mixed
     * @throws BadRequestHttpException
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->session->setFlash('success', 'New password saved.');

            return $this->goHome();
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }

    /**
     * Verify email address
     *
     * @param string $token
     * @throws BadRequestHttpException
     * @return yii\web\Response
     */
    public function actionVerifyEmail($token)
    {
        try {
            $model = new VerifyEmailForm($token);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
        if (($user = $model->verifyEmail()) && Yii::$app->user->login($user)) {
            Yii::$app->session->setFlash('success', 'Your email has been confirmed!');
            return $this->goHome();
        }

        Yii::$app->session->setFlash('error', 'Sorry, we are unable to verify your account with provided token.');
        return $this->goHome();
    }

    /**
     * Resend verification email
     *
     * @return mixed
     */
    public function actionResendVerificationEmail()
    {
        $model = new ResendVerificationEmailForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->session->setFlash('success', 'Check your email for further instructions.');
                return $this->goHome();
            }
            Yii::$app->session->setFlash('error', 'Sorry, we are unable to resend verification email for the provided email address.');
        }

        return $this->render('resendVerificationEmail', [
            'model' => $model
        ]);
    }

    /**
     * Displays user profile.
     *
     * @return mixed
     */
    public function actionProfile()
    {
        if (Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $user = Yii::$app->user->identity;
        $model = new \common\models\ProfileForm($user);

        if ($model->load(Yii::$app->request->post())) {
            // Server-side validation for required fields
            $requiredFields = ['displayName', 'department', 'email', 'telephoneNumber'];
            $emptyFields = [];
            
            foreach ($requiredFields as $field) {
                if (empty($model->$field) || trim($model->$field) === '') {
                    $emptyFields[] = $field;
                }
            }
            
            if (!empty($emptyFields)) {
                $fieldNames = [
                    'displayName' => 'Display Name',
                    'department' => 'Department', 
                    'email' => 'Email',
                    'telephoneNumber' => 'Telephone Number'
                ];
                
                $missingFields = array_map(function($field) use ($fieldNames) {
                    return $fieldNames[$field] ?? $field;
                }, $emptyFields);
                
                Yii::$app->session->setFlash('error', 'กรุณากรอกข้อมูลให้ครบถ้วน: ' . implode(', ', $missingFields));
                return $this->render('profile', [
                    'user' => $user,
                    'model' => $model,
                ]);
            }
            
            if ($model->updateProfile()) {
                // Update session data (ProfileForm->updateProfile() already updates session, but we ensure consistency here)
                $sessionData = Yii::$app->session->get('ldapUserData');
                $sessionData['displayname'] = $model->displayName;
                $sessionData['department'] = $model->department ?? '';
                $sessionData['mail'] = $model->email;
                $sessionData['telephonenumber'] = [$model->telephoneNumber] ?? '';
                if (!empty($model->streetaddress)) {
                    $sessionData['streetaddress'] = $model->streetaddress;
                }
                if (!empty($model->title)) {
                    $sessionData['title'] = $model->title;
                }
                if (!empty($model->givenname)) {
                    $sessionData['givenname'] = $model->givenname;
                }
                Yii::$app->session->set('ldapUserData', $sessionData);
                
                Yii::$app->session->setFlash('success', 'Profile updated successfully..');
                return $this->redirect(['/site/profile']);
            } else {
                Yii::$app->session->setFlash('error', 'Failed to update profile. Please check the form for errors.');
            }
        }

        return $this->render('profile', [
            'user' => $user,
            'model' => $model,
        ]);
    }

    /**
     * Change password action.
     *
     * @return mixed
     */
    public function actionChangePassword()
    {
        if (Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new \common\models\ProfileForm(Yii::$app->user->identity);

        if ($model->load(Yii::$app->request->post())) {
            // Validate model (validation rules handle password requirements)
            if ($model->validate()) {
				if ($model->updateProfile()) {
					Yii::$app->user->logout();
					Yii::$app->session->remove('ldapUserData');
					Yii::$app->session->setFlash('success', 'เปลี่ยนรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบใหม่');
					return $this->redirect(['site/login']);
                } else {
                    // Display validation errors if updateProfile failed
                    if ($model->hasErrors()) {
                        foreach ($model->errors as $field => $errors) {
                            foreach ($errors as $error) {
                                Yii::$app->session->setFlash('error', $error);
                            }
                        }
                    }
				}
            }
        }

        return $this->render('change-password', [
            'model' => $model
        ]);
    }
}
