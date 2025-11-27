# การเพิ่มฟิลด์ใหม่ในหน้าลงทะเบียนสำหรับ Active Directory

## ฟิลด์ใหม่ที่เพิ่มเข้าไป

### 1. ชื่อ-นามสกุล (อังกฤษ) → Given Name
- **ฟิลด์**: `given_name_en`
- **Active Directory Attribute**: `givenName`
- **ความยาว**: สูงสุด 100 ตัวอักษร
- **การใช้งาน**: ใช้แทนชื่อภาษาไทยใน Active Directory

### 2. ตำแหน่ง → Title
- **ฟิลด์**: `title`
- **Active Directory Attribute**: `title`
- **ความยาว**: สูงสุด 50 ตัวอักษร
- **การใช้งาน**: บันทึกตำแหน่งงานของผู้ใช้

### 3. เลขบัตรประชาชน → Description
- **ฟิลด์**: `id_card`
- **Active Directory Attribute**: `description`
- **ความยาว**: 13 หลัก (ตัวเลขเท่านั้น)
- **การใช้งาน**: บันทึกเลขบัตรประชาชนในฟิลด์ description

### 4. บริษัทที่ติดต่อ → Company
- **ฟิลด์**: `company`
- **Active Directory Attribute**: `company`
- **ความยาว**: สูงสุด 100 ตัวอักษร
- **การใช้งาน**: บันทึกชื่อบริษัทที่ติดต่อ

### 5. เลขรหัสจาก Ephis → Office Name
- **ฟิลด์**: `ephis_code`
- **Active Directory Attribute**: `physicalDeliveryOfficeName`
- **ความยาว**: สูงสุด 20 ตัวอักษร
- **การใช้งาน**: บันทึกรหัสจากระบบ Ephis

## การเปลี่ยนแปลงในโค้ด

### 1. `common/models/AdUser.php`

#### เพิ่มฟิลด์ใหม่
```php
// New fields for Active Directory
public $given_name_en; // ชื่อ-นามสกุล(อังกฤษ) --> Given Name
public $title; // ตำแหน่ง --> Title
public $id_card; // เลขบัตรประชาชน --> Description
public $company; // บริษัทที่ติดต่อ --> Company
public $ephis_code; // เลขรหัสจาก Ephis --> Office Name
```

#### เพิ่มการตรวจสอบข้อมูล
```php
[['given_name_en'], 'string', 'max' => 100],
[['title'], 'string', 'max' => 50],
[['id_card'], 'string', 'max' => 13],
[['company'], 'string', 'max' => 100],
[['ephis_code'], 'string', 'max' => 20],
[['id_card'], 'match', 'pattern' => '/^[0-9]{13}$/', 'message' => 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก'],
```

#### เพิ่มป้ายกำกับ
```php
'given_name_en' => 'ชื่อ-นามสกุล (อังกฤษ)',
'title' => 'ตำแหน่ง',
'id_card' => 'เลขบัตรประชาชน',
'company' => 'บริษัทที่ติดต่อ',
'ephis_code' => 'เลขรหัสจาก Ephis',
```

#### ปรับปรุงการบันทึกข้อมูลใน Active Directory
```php
// Add new Active Directory fields
if (!empty($this->given_name_en)) {
    $entry["givenName"] = $this->given_name_en; // Override givenName with English name
}
if (!empty($this->title)) {
    $entry["title"] = $this->title;
}
if (!empty($this->id_card)) {
    $entry["description"] = $this->id_card;
}
if (!empty($this->company)) {
    $entry["company"] = $this->company;
}
if (!empty($this->ephis_code)) {
    $entry["physicalDeliveryOfficeName"] = $this->ephis_code;
}
```

### 2. `frontend/views/ad-user/create.php`

#### เพิ่มฟิลด์ใหม่ในฟอร์ม
```php
<!-- New Active Directory fields -->
<div class="row">
    <div class="col-md-6">
        <?= $form->field($model, 'given_name_en', [
            'options' => ['class' => 'form-group'],
        ])->textInput([
            'maxlength' => true,
            'class' => 'form-control',
            'placeholder' => 'Enter English name'
        ]) ?>
    </div>
    <div class="col-md-6">
        <?= $form->field($model, 'title', [
            'options' => ['class' => 'form-group'],
        ])->textInput([
            'maxlength' => true,
            'class' => 'form-control',
            'placeholder' => 'Enter position/title'
        ]) ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <?= $form->field($model, 'id_card', [
            'options' => ['class' => 'form-group'],
        ])->textInput([
            'maxlength' => 13,
            'class' => 'form-control',
            'placeholder' => 'Enter 13-digit ID card number'
        ]) ?>
    </div>
    <div class="col-md-6">
        <?= $form->field($model, 'company', [
            'options' => ['class' => 'form-group'],
        ])->textInput([
            'maxlength' => true,
            'class' => 'form-control',
            'placeholder' => 'Enter company name'
        ]) ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <?= $form->field($model, 'ephis_code', [
            'options' => ['class' => 'form-group'],
        ])->textInput([
            'maxlength' => true,
            'class' => 'form-control',
            'placeholder' => 'Enter Ephis code'
        ]) ?>
    </div>
    <div class="col-md-6">
        <!-- Empty column for layout balance -->
    </div>
</div>
```

## การทำงาน

### 1. การบันทึกข้อมูล
- ฟิลด์ใหม่ทั้งหมดเป็น **optional** (ไม่บังคับกรอก)
- ข้อมูลจะถูกบันทึกใน Active Directory เฉพาะเมื่อมีการกรอกข้อมูล
- การตรวจสอบข้อมูลจะทำงานตามกฎที่กำหนด

### 2. การตรวจสอบข้อมูล
- **เลขบัตรประชาชน**: ต้องเป็นตัวเลข 13 หลัก
- **ชื่อ-นามสกุล (อังกฤษ)**: สูงสุด 100 ตัวอักษร
- **ตำแหน่ง**: สูงสุด 50 ตัวอักษร
- **บริษัทที่ติดต่อ**: สูงสุด 100 ตัวอักษร
- **เลขรหัสจาก Ephis**: สูงสุด 20 ตัวอักษร

### 3. การแมปข้อมูลกับ Active Directory
- `given_name_en` → `givenName` (แทนที่ชื่อภาษาไทย)
- `title` → `title`
- `id_card` → `description`
- `company` → `company`
- `ephis_code` → `physicalDeliveryOfficeName`

## ผลลัพธ์

### ข้อดี
1. **ข้อมูลครบถ้วน**: บันทึกข้อมูลเพิ่มเติมใน Active Directory
2. **การตรวจสอบข้อมูล**: มีการตรวจสอบรูปแบบข้อมูลที่เหมาะสม
3. **ความยืดหยุ่น**: ฟิลด์ใหม่เป็น optional ไม่บังคับกรอก
4. **การจัดระเบียบ**: ข้อมูลถูกจัดเก็บในฟิลด์ที่เหมาะสมใน Active Directory

### การใช้งาน
1. **ผู้ใช้กรอกข้อมูล**: กรอกข้อมูลเพิ่มเติมในฟอร์มลงทะเบียน
2. **ระบบตรวจสอบ**: ตรวจสอบรูปแบบข้อมูลตามกฎที่กำหนด
3. **บันทึกข้อมูล**: บันทึกข้อมูลลงใน Active Directory
4. **แสดงผล**: ข้อมูลจะปรากฏใน Active Directory ตามที่กำหนด

## สรุป

การเพิ่มฟิลด์ใหม่นี้ทำให้:
- ระบบสามารถบันทึกข้อมูลเพิ่มเติมใน Active Directory
- มีการตรวจสอบข้อมูลที่เหมาะสม
- ฟิลด์ใหม่เป็น optional ไม่บังคับกรอก
- ข้อมูลถูกจัดเก็บในฟิลด์ที่เหมาะสมใน Active Directory
- ประสบการณ์ผู้ใช้ดีขึ้นด้วยข้อมูลที่ครบถ้วน
