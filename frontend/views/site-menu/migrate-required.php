<?php
use yii\helpers\Html;

/* @var $this yii\web\View */

$this->title = 'จัดการเมนูระบบงาน';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card border-warning">
    <div class="card-header bg-warning text-dark">
        <h4 class="card-title mb-0"><i class="fas fa-database me-2"></i>ยังไม่ได้สร้างตารางเมนู</h4>
    </div>
    <div class="card-body">
        <p class="mb-3">ตาราง <code>site_service_menu</code> ยังไม่มีในฐานข้อมูล กรุณารัน migration เพื่อสร้างตารางและข้อมูลเริ่มต้น</p>
        <ol class="mb-4">
            <li>เปิด Command Prompt หรือ Terminal</li>
            <li>ไปที่โฟลเดอร์โปรเจกต์: <code>cd C:\xampp\htdocs\adrpp</code></li>
            <li>รันคำสั่ง: <code class="bg-light px-2 py-1 rounded">php yii migrate</code></li>
            <li>กด <kbd>y</kbd> แล้ว Enter เพื่อยืนยันการรัน migration</li>
        </ol>
        <p class="text-muted small mb-0">หลังรัน migration เสร็จ ให้รีเฟรชหน้านี้</p>
        <?= Html::a('<i class="fas fa-home me-1"></i>กลับหน้าแรก', ['/site/index'], ['class' => 'btn btn-secondary mt-3']) ?>
    </div>
</div>
