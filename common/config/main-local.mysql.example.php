<?php

/**
 * ตัวอย่าง config สำหรับเชื่อมต่อ MySQL (adrpp)
 * ใช้เมื่อต้องการใช้ MySQL แทน SQLite:
 * คัดลอกไฟล์นี้เป็น main-local.php หรือนำส่วน 'db' ไปใส่ใน main-local.php
 *
 * ข้อมูลการเชื่อมต่อ (ตรงกับ setup-mysql-database.php):
 * - host: 192.168.238.211
 * - user: root
 * - password: rpp14641
 * - database: adrpp
 */

$dbConfig = [
    'host' => '192.168.238.211',
    'user' => 'root',
    'password' => 'rpp14641',
    'database' => 'adrpp',
    'charset' => 'utf8mb4',
];

$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";

return [
    'components' => [
        'db' => [
            'class' => \yii\db\Connection::class,
            'dsn' => $dsn,
            'username' => $dbConfig['user'],
            'password' => $dbConfig['password'],
            'charset' => $dbConfig['charset'],
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@common/mail',
            'useFileTransport' => true,
        ],
    ],
];
