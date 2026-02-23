#!/usr/bin/env php
<?php
/**
 * สคริปต์สร้างฐานข้อมูล MySQL (adrpp) และตารางที่ใช้ในระบบ
 * รัน: php console/scripts/setup-mysql-database.php
 * หรือจากโฟลเดอร์โปรเจกต์: php console/scripts/setup-mysql-database.php
 */

$dbConfig = [
    'host' => '192.168.238.211',
    'user' => 'root',
    'password' => 'rpp14641',
    'database' => 'adrpp',
    'charset' => 'utf8mb4',
];

echo "MySQL Setup Script - สร้างฐานข้อมูล adrpp\n";
echo str_repeat('=', 50) . "\n";

try {
    // เชื่อมต่อโดยยังไม่เลือก database (เพื่อสร้าง database ก่อน)
    $dsn = "mysql:host={$dbConfig['host']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "[OK] เชื่อมต่อ MySQL ที่ {$dbConfig['host']} สำเร็จ\n";

    // สร้าง database ถ้ายังไม่มี
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[OK] ฐานข้อมูล '{$dbConfig['database']}' พร้อมใช้\n";

    $pdo->exec("USE `{$dbConfig['database']}`");

    // สร้างตาราง site_service_menu (MySQL syntax)
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_service_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` varchar(200) NOT NULL DEFAULT '',
  `url` varchar(500) NOT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'fa-link',
  `card_color` varchar(50) NOT NULL DEFAULT 'card-border-blue',
  `icon_bg_class` varchar(50) NOT NULL DEFAULT 'icon-bg-blue',
  `image_path` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `admin_only` tinyint(1) NOT NULL DEFAULT 0,
  `open_new_tab` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx-site_service_menu-sort_visible` (`sort_order`,`is_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    $pdo->exec($sql);
    echo "[OK] ตาราง site_service_menu สร้างหรือมีอยู่แล้ว\n";

    // สร้างตาราง migration (สำหรับ yii migrate history) ถ้ายังไม่มี
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `migration` (
          `version` varchar(180) NOT NULL,
          `apply_time` int(11) DEFAULT NULL,
          PRIMARY KEY (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[OK] ตาราง migration พร้อมใช้\n";

    echo "\n" . str_repeat('=', 50) . "\n";
    echo "เสร็จสิ้น — ฐานข้อมูล {$dbConfig['database']} พร้อมใช้งาน\n";
    echo "\nขั้นตอนถัดไป:\n";
    echo "1. ใช้ config MySQL: คัดลอก common/config/main-local.mysql.example.php เป็น common/config/main-local.php\n";
    echo "2. รัน migration เพิ่มเติม (RBAC, seed): php yii migrate\n";
    echo "\n";

} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
