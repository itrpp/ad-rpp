<?php

use yii\db\Migration;

/**
 * Seeds site_service_menu with default hospital system links (same as previous static list).
 *
 * ข้อมูลที่ seed จะถูกอ่านจาก DB โดย SiteServiceMenu::getVisibleItems() บนหน้าแรก
 * และจัดการผ่านเมนู "จัดการเมนูระบบงาน" (SiteMenuController).
 */
class m260223_100001_seed_site_service_menu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $rows = [
            ['ระบบจัดการความรู้ (KM)', 'RPP e-Learning', 'https://elearning.rpphosp.go.th/', 'fa-book', 'card-border-blue', 'icon-bg-blue', 'e-learning.jpg', 0, 1, 0],
            ['ใช้งาน Internet', 'login-Logout Internet', 'https://authen.rpphosp.go.th:1003/login?0009cd366581f1cb/', 'fa-wifi', 'card-border-green', 'icon-bg-green', 'authen.jpg', 1, 1, 0],
            ['ระบบภายในโรงพยาบาล', 'INTRANET', 'https://intranet.rpphosp.go.th/', 'fa-globe', 'card-border-dark-green', 'icon-bg-dark-green', 'intra.jpg', 2, 1, 0],
            ['เว็บไซต์โรงพยาบาล', 'ไปที่เว็บไซต์', 'https://www.rpphosp.go.th/', 'fa-hospital', 'card-border-red', 'icon-bg-red', 'web.jpg', 3, 1, 0],
            ['ทะเบียนอุปกรณ์ IT', 'Equipment', 'http://equipment.rpphosp.go.th/', 'fa-laptop', 'card-border-orange', 'icon-bg-orange', 'equipment.jpg', 4, 1, 1],
            ['ตรวจสอบสถานะ Server/Switch', 'Monitor', 'http://monitor.rpphosp.go.th/', 'fa-server', 'card-border-blue-light', 'icon-bg-blue-light', 'monitor.jpg', 5, 1, 1],
            ['ระบบ GTW BACKOffice', 'GTW BACKOffice', 'https://14641.gtwoffice.com/login', 'fa-columns', 'card-border-indigo', 'icon-bg-indigo', 'gtw-backoffice.jpg', 6, 1, 0],
            ['ระบบ OneStore', 'INVENTORY', 'https://inventory.rpphosp.go.th/', 'fa-store', 'card-border-lime', 'icon-bg-lime', 'inventory.jpg', 7, 1, 0],
            ['ระบบแจ้งซ่อมช่าง', 'RPP Services', 'https://services.rpphosp.go.th/auth', 'fa-tools', 'card-border-dark-red', 'icon-bg-dark-red', 'services.jpg', 8, 1, 0],
            ['ระบบหอพักโรงพยาบาล', 'RPP Dormitory', 'https://dormrpp.rpphosp.go.th/', 'fa-bed', 'card-border-indigo', 'icon-bg-indigo', 'dorm.jpg', 9, 1, 0],
            ['ระบบจัดซื้อจัดจ้าง', 'RPP Procurement', 'https://procurement.rpphosp.go.th/', 'fa-shopping-cart', 'card-border-orange', 'icon-bg-orange', 'procurement.jpg', 10, 1, 0],
            ['ระบบเวรเปล', 'Porter / Stretcher', 'https://portal.rpphosp.go.th/porter/stat', 'fa-ambulance', 'card-border-teal', 'icon-bg-teal', 'porter-stat.png', 11, 1, 0],
            ['ระบบแจ้งซ่อมคอม', 'IT Jobs / Repair', 'https://services.rpphosp.go.th/jobs/', 'fa-laptop', 'card-border-cyan', 'icon-bg-cyan', 'jobs-repair.png', 12, 1, 0],
            ['ระบบเครื่องมือแพทย์', 'RPH MEMS', 'https://rph-mems.com/', 'fa-toolbox', 'card-border-purple', 'icon-bg-purple', 'mems.png', 13, 1, 0],
            ['จัดการ Screens', 'RPPQ Screens Admin', 'https://rppq.rpphosp.go.th/admin/screens', 'fa-tv', 'card-border-blue', 'icon-bg-blue', 'screens-admin.png', 14, 1, 1],
        ];
        foreach ($rows as $i => $r) {
            $this->insert('{{%site_service_menu}}', [
                'title' => $r[0],
                'description' => $r[1],
                'url' => $r[2],
                'icon' => $r[3],
                'card_color' => $r[4],
                'icon_bg_class' => $r[5],
                'image_path' => $r[6],
                'sort_order' => $r[7],
                'is_visible' => $r[8],
                'admin_only' => $r[9],
                'open_new_tab' => 1,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('{{%site_service_menu}}');
    }
}
