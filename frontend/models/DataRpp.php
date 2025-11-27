<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "yan_db".
 *
 * @property int $id
 * @property string $name
 * @property string $lname
 * @property string $depart
 */
class DataRpp extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'agencies';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {

     return [
            [["id_agen", 'name_agen', 'password', 'active', 'username'], 'required'],
            [['id_agen', 'name_agen', 'password', 'active', 'username'], 'string', 'max' => 255], 
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_agen' => 'ID ',
            'name_agen' => 'ชื่อ',
            'password' => 'Password',
            'active' => 'Active',
            'username' => 'ID ผู้ใช้งาน',
       
        ];
    }
}
