<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\models\AdUser;

class AdUserController extends Controller
{
    public function behaviors()
    {
        return [
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
        return $this->render('index');
    }

    public function actionCreate()
    {
        $model = new AdUser();

        if ($model->load(Yii::$app->request->post())) {
            if ($model->createUser()) {
                Yii::$app->session->setFlash('success', 'เพิ่มผู้ใช้สำเร็จ');
                return $this->redirect(['index']);
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }
} 