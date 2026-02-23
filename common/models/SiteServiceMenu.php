<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\web\UploadedFile;

/**
 * Model for "ระบบงานที่เกี่ยวข้องในโรงพยาบาล" service menu items (cards on site index).
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $url
 * @property string $icon
 * @property string $card_color
 * @property string $icon_bg_class
 * @property string|null $image_path
 * @property int $sort_order
 * @property int $is_visible
 * @property int $admin_only
 * @property int $open_new_tab
 * @property int|null $created_at
 * @property int|null $updated_at
 */
class SiteServiceMenu extends ActiveRecord
{
    /** @var UploadedFile|null อัปโหลดรูป (ไม่เก็บใน DB) */
    public $imageFile;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%site_service_menu}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => function () {
                    return time();
                },
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['title', 'url'], 'required'],
            [['sort_order', 'is_visible', 'admin_only', 'open_new_tab'], 'integer'],
            [['title'], 'string', 'max' => 200],
            [['description'], 'string', 'max' => 200],
            [['url'], 'string', 'max' => 500],
            [['icon', 'card_color', 'icon_bg_class'], 'string', 'max' => 50],
            [['image_path'], 'string', 'max' => 255],
            [['imageFile'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png, jpg, jpeg, gif, webp', 'maxSize' => 2 * 1024 * 1024, 'checkExtensionByMimeType' => true],
            [['description', 'icon', 'card_color', 'icon_bg_class', 'image_path'], 'default', 'value' => ''],
            [['sort_order'], 'default', 'value' => 0],
            [['is_visible', 'open_new_tab'], 'default', 'value' => 1],
            [['admin_only'], 'default', 'value' => 0],
            [['is_visible', 'admin_only', 'open_new_tab'], 'in', 'range' => [0, 1]],
            [['imageFile'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'ชื่อรายการ',
            'description' => 'คำอธิบายสั้น',
            'url' => 'ลิงก์ URL',
            'icon' => 'ไอคอน (FontAwesome)',
            'card_color' => 'สีการ์ด',
            'icon_bg_class' => 'สีไอคอน',
            'image_path' => 'รูปภาพ',
            'imageFile' => 'อัปโหลดรูปภาพ',
            'sort_order' => 'ลำดับ',
            'is_visible' => 'แสดงผล',
            'admin_only' => 'เฉพาะ Admin',
            'open_new_tab' => 'เปิดแท็บใหม่',
            'created_at' => 'สร้างเมื่อ',
            'updated_at' => 'แก้ไขเมื่อ',
        ];
    }

    /**
     * Get visible menu items for site index.
     * @param bool $isAdmin Whether current user is admin (to include admin_only items).
     * @return static[]
     */
    public static function getVisibleItems($isAdmin = false)
    {
        $query = static::find()
            ->where(['is_visible' => 1])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
        if (!$isAdmin) {
            $query->andWhere(['admin_only' => 0]);
        }
        return $query->all();
    }

    /** ตัวเลือกไอคอน FontAwesome สำหรับ dropdown (ค่า => คำอธิบาย) */
    public static function iconOptions()
    {
        return [
            'fa-book' => 'fa-book (หนังสือ)',
            'fa-wifi' => 'fa-wifi (WiFi)',
            'fa-globe' => 'fa-globe (โลก)',
            'fa-hospital' => 'fa-hospital (โรงพยาบาล)',
            'fa-laptop' => 'fa-laptop (แล็ปท็อป)',
            'fa-server' => 'fa-server (เซิร์ฟเวอร์)',
            'fa-columns' => 'fa-columns (คอลัมน์)',
            'fa-store' => 'fa-store (ร้าน)',
            'fa-tools' => 'fa-tools (เครื่องมือ)',
            'fa-bed' => 'fa-bed (เตียง)',
            'fa-shopping-cart' => 'fa-shopping-cart (ตะกร้า)',
            'fa-ambulance' => 'fa-ambulance (รถพยาบาล)',
            'fa-tv' => 'fa-tv (จอ)',
            'fa-toolbox' => 'fa-toolbox (กล่องเครื่องมือ)',
            'fa-link' => 'fa-link (ลิงก์)',
            'fa-user' => 'fa-user (ผู้ใช้)',
            'fa-cog' => 'fa-cog (ฟันเฟือง)',
            'fa-file-alt' => 'fa-file-alt (ไฟล์)',
            'fa-chart-line' => 'fa-chart-line (กราฟ)',
            'fa-envelope' => 'fa-envelope (จดหมาย)',
        ];
    }

    /** Available card/icon color options for dropdown */
    public static function colorOptions()
    {
        return [
            'blue' => 'น้ำเงิน (Blue)',
            'green' => 'เขียว (Green)',
            'dark-green' => 'เขียวเข้ม (Dark Green)',
            'red' => 'แดง (Red)',
            'orange' => 'ส้ม (Orange)',
            'blue-light' => 'ฟ้าอ่อน (Blue Light)',
            'indigo' => 'คราม (Indigo)',
            'lime' => 'เหลืองมะนาว (Lime)',
            'dark-red' => 'แดงเข้ม (Dark Red)',
            'teal' => 'เขียวฟ้า (Teal)',
            'cyan' => 'ฟ้า (Cyan)',
            'purple' => 'ม่วง (Purple)',
        ];
    }
}
