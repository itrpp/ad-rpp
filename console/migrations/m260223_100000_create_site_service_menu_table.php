<?php

use yii\db\Migration;

/**
 * Creates table for managing "ระบบงานที่เกี่ยวข้องในโรงพยาบาล" service cards on site index.
 *
 * ตารางนี้ถูกใช้โดย:
 * - common\models\SiteServiceMenu (ActiveRecord)
 * - frontend\controllers\SiteController::actionIndex() — ดึงรายการเมนูแสดงบนหน้าแรก
 * - frontend\controllers\SiteMenuController — CRUD จัดการเมนูระบบงาน
 */
class m260223_100000_create_site_service_menu_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // SQLite does not support column comments; comments are in the model's attributeLabels()
        $this->createTable('{{%site_service_menu}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(200)->notNull(),
            'description' => $this->string(200)->notNull()->defaultValue(''),
            'url' => $this->string(500)->notNull(),
            'icon' => $this->string(50)->notNull()->defaultValue('fa-link'),
            'card_color' => $this->string(50)->notNull()->defaultValue('card-border-blue'),
            'icon_bg_class' => $this->string(50)->notNull()->defaultValue('icon-bg-blue'),
            'image_path' => $this->string(255)->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_visible' => $this->integer(1)->notNull()->defaultValue(1),
            'admin_only' => $this->integer(1)->notNull()->defaultValue(0),
            'open_new_tab' => $this->integer(1)->notNull()->defaultValue(1),
            'created_at' => $this->integer()->null(),
            'updated_at' => $this->integer()->null(),
        ]);
        $this->createIndex('idx-site_service_menu-sort_visible', '{{%site_service_menu}}', ['sort_order', 'is_visible']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%site_service_menu}}');
    }
}
