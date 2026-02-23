<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use yii\db\Exception as DbException;
use yii\data\Pagination;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use common\models\SiteServiceMenu;
use common\components\PermissionManager;

/**
 * Admin CRUD for "ระบบงานที่เกี่ยวข้องในโรงพยาบาล" service menu (cards on site index).
 */
class SiteMenuController extends Controller
{
    /**
     * {@inheritdoc}
     */
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
                            return $pm->isLdapAdmin();
                        },
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'toggle' => ['post'],
                ],
            ],
        ];
    }

    /**
     * List all menu items.
     */
    public function actionIndex()
    {
        try {
            $query = SiteServiceMenu::find()->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
            $pagination = new Pagination([
                'totalCount' => $query->count(),
                'defaultPageSize' => 20,
                'pageSizeParam' => 'per-page',
            ]);
            $models = $query->offset($pagination->offset)->limit($pagination->limit)->all();
        } catch (DbException $e) {
            if (strpos($e->getMessage(), 'no such table') !== false || strpos($e->getMessage(), 'site_service_menu') !== false) {
                return $this->render('migrate-required');
            }
            throw $e;
        }
        return $this->render('index', ['models' => $models, 'pagination' => $pagination]);
    }

    /**
     * Create new menu item.
     */
    public function actionCreate()
    {
        try {
            $model = new SiteServiceMenu();
            $model->sort_order = (int) SiteServiceMenu::find()->max('sort_order') + 1;
        } catch (DbException $e) {
            return $this->redirectToIndexIfTableMissing($e);
        }
        $model->is_visible = 1;
        $model->open_new_tab = 1;

        if ($model->load(Yii::$app->request->post())) {
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            if ($model->imageFile) {
                $dir = Yii::getAlias('@frontend/web/img');
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $ext = strtolower($model->imageFile->extension);
                $name = 'menu-' . substr(uniqid('', true), -8) . '.' . $ext;
                if ($model->imageFile->saveAs($dir . '/' . $name)) {
                    $model->image_path = $name;
                }
                $model->imageFile = null; // เคลียร์เพื่อไม่ให้ validator อ่าน temp file ที่ย้ายแล้ว
            }
            try {
                if ($model->save()) {
                    Yii::$app->session->setFlash('success', 'เพิ่มรายการเมนูเรียบร้อยแล้ว');
                    return $this->redirect(['index']);
                }
            } catch (DbException $e) {
                return $this->redirectToIndexIfTableMissing($e);
            }
        }
        return $this->render('form', ['model' => $model, 'title' => 'เพิ่มรายการเมนู']);
    }

    /**
     * Update menu item.
     */
    public function actionUpdate($id)
    {
        try {
            $model = $this->findModel($id);
        } catch (DbException $e) {
            return $this->redirectToIndexIfTableMissing($e);
        }
        $oldImagePath = $model->image_path;
        if ($model->load(Yii::$app->request->post())) {
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
            if ($model->imageFile) {
                $dir = Yii::getAlias('@frontend/web/img');
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $ext = strtolower($model->imageFile->extension);
                $name = 'menu-' . substr(uniqid('', true), -8) . '.' . $ext;
                if ($model->imageFile->saveAs($dir . '/' . $name)) {
                    $model->image_path = $name;
                    if ($oldImagePath && file_exists($dir . '/' . $oldImagePath)) {
                        @unlink($dir . '/' . $oldImagePath);
                    }
                }
                $model->imageFile = null; // เคลียร์เพื่อไม่ให้ validator อ่าน temp file ที่ย้ายแล้ว
            } else {
                $model->image_path = $oldImagePath;
            }
            try {
                if ($model->save()) {
                    Yii::$app->session->setFlash('success', 'บันทึกการแก้ไขเรียบร้อยแล้ว');
                    return $this->redirect(['index']);
                }
            } catch (DbException $e) {
                return $this->redirectToIndexIfTableMissing($e);
            }
        }
        return $this->render('form', ['model' => $model, 'title' => 'แก้ไขรายการเมนู']);
    }

    /**
     * Delete menu item.
     */
    public function actionDelete($id)
    {
        try {
            $model = $this->findModel($id);
            $imagePath = $model->image_path;
            $model->delete();
            if ($imagePath) {
                $file = Yii::getAlias('@frontend/web/img/' . $imagePath);
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        } catch (DbException $e) {
            return $this->redirectToIndexIfTableMissing($e);
        }
        Yii::$app->session->setFlash('success', 'ลบรายการเมนูเรียบร้อยแล้ว');
        return $this->redirect(['index']);
    }

    /**
     * Toggle visibility (AJAX).
     */
    public function actionToggle($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        try {
            $model = $this->findModel($id);
            $model->is_visible = $model->is_visible ? 0 : 1;
            $model->save(false);
            return ['success' => true, 'is_visible' => (bool) $model->is_visible];
        } catch (DbException $e) {
            if (strpos($e->getMessage(), 'no such table') !== false) {
                Yii::$app->response->statusCode = 503;
                return ['success' => false, 'message' => 'กรุณารัน migration: php yii migrate'];
            }
            throw $e;
        }
    }

    /**
     * Move order up.
     */
    public function actionMoveUp($id)
    {
        try {
            $model = $this->findModel($id);
            $prev = SiteServiceMenu::find()->where(['<', 'sort_order', $model->sort_order])->orderBy(['sort_order' => SORT_DESC])->one();
            if ($prev) {
                $swap = $prev->sort_order;
                $prev->sort_order = $model->sort_order;
                $prev->save(false);
                $model->sort_order = $swap;
                $model->save(false);
            }
        } catch (DbException $e) {
            return $this->redirectToIndexIfTableMissing($e);
        }
        Yii::$app->session->setFlash('success', 'เปลี่ยนลำดับเรียบร้อยแล้ว');
        return $this->redirect(['index']);
    }

    /**
     * Move order down.
     */
    public function actionMoveDown($id)
    {
        try {
            $model = $this->findModel($id);
            $next = SiteServiceMenu::find()->where(['>', 'sort_order', $model->sort_order])->orderBy(['sort_order' => SORT_ASC])->one();
            if ($next) {
                $swap = $next->sort_order;
                $next->sort_order = $model->sort_order;
                $next->save(false);
                $model->sort_order = $swap;
                $model->save(false);
            }
        } catch (DbException $e) {
            return $this->redirectToIndexIfTableMissing($e);
        }
        Yii::$app->session->setFlash('success', 'เปลี่ยนลำดับเรียบร้อยแล้ว');
        return $this->redirect(['index']);
    }

    /**
     * If exception is "no such table", redirect to index (shows migrate-required). Otherwise rethrow.
     */
    private function redirectToIndexIfTableMissing(DbException $e)
    {
        if (strpos($e->getMessage(), 'no such table') !== false || strpos($e->getMessage(), 'site_service_menu') !== false) {
            return $this->redirect(['index']);
        }
        throw $e;
    }

    /**
     * @param int $id
     * @return SiteServiceMenu
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        $model = SiteServiceMenu::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('ไม่พบรายการที่ต้องการ');
        }
        return $model;
    }
}
