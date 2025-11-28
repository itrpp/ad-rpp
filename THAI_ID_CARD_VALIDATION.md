# การตรวจสอบเลขบัตรประชาชนไทย

## การเปลี่ยนแปลงที่ดำเนินการ

### 1. เพิ่มการตรวจสอบใน Model (`common/models/AdUser.php`)

#### เพิ่มฟังก์ชันตรวจสอบเลขบัตรประชาชนไทย
```php
public function validateThaiIdCard($attribute, $params)
{
    if (empty($this->$attribute)) {
        return; // Skip validation if empty (field is optional)
    }
    
    $idCard = $this->$attribute;
    
    // Check if it's exactly 13 digits
    if (!preg_match('/^[0-9]{13}$/', $idCard)) {
        $this->addError($attribute, 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก');
        return;
    }
    
    // Thai ID card validation algorithm
    $sum = 0;
    $weights = [13, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2];
    
    for ($i = 0; $i < 12; $i++) {
        $sum += intval($idCard[$i]) * $weights[$i];
    }
    
    $remainder = $sum % 11;
    $checkDigit = (11 - $remainder) % 10;
    
    if (intval($idCard[12]) !== $checkDigit) {
        $this->addError($attribute, 'เลขบัตรประชาชนไม่ถูกต้องตามรูปแบบไทย');
    }
}
```

#### เพิ่มการตรวจสอบใน rules
```php
[['id_card'], 'validateThaiIdCard'],
```

### 2. เพิ่มการตรวจสอบแบบ Real-time ใน View (`frontend/views/ad-user/create.php`)

#### เพิ่ม ID สำหรับฟิลด์เลขบัตรประชาชน
```php
<?= $form->field($model, 'id_card', [
    'options' => ['class' => 'form-group'],
])->textInput([
    'maxlength' => 13,
    'class' => 'form-control',
    'placeholder' => '13-digit ID card number',
    'id' => 'thai-id-card'
]) ?>
```

#### เพิ่ม JavaScript สำหรับการตรวจสอบแบบ Real-time
```javascript
// Thai ID Card validation
function validateThaiIdCard(idCard) {
    if (!idCard || idCard.length !== 13) {
        return false;
    }
    
    // Check if all characters are digits
    if (!/^[0-9]{13}$/.test(idCard)) {
        return false;
    }
    
    // Thai ID card validation algorithm
    var sum = 0;
    var weights = [13, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2];
    
    for (var i = 0; i < 12; i++) {
        sum += parseInt(idCard[i]) * weights[i];
    }
    
    var remainder = sum % 11;
    var checkDigit = (11 - remainder) % 10;
    
    return parseInt(idCard[12]) === checkDigit;
}

$('#thai-id-card').on('input', function() {
    var idCard = $(this).val();
    var errorDiv = $(this).next('.invalid-feedback');
    
    if (!idCard) {
        $(this).removeClass('is-invalid is-valid');
        if (errorDiv.length) { errorDiv.text(''); }
        return;
    }
    
    if (idCard.length < 13) {
        $(this).removeClass('is-valid').addClass('is-invalid');
        if (errorDiv.length) { errorDiv.text('กรุณากรอกเลขบัตรประชาชน 13 หลัก'); }
        return;
    }
    
    if (!/^[0-9]{13}$/.test(idCard)) {
        $(this).removeClass('is-valid').addClass('is-invalid');
        if (errorDiv.length) { errorDiv.text('เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก'); }
        return;
    }
    
    if (validateThaiIdCard(idCard)) {
        $(this).removeClass('is-invalid').addClass('is-valid');
        if (errorDiv.length) { errorDiv.text(''); }
    } else {
        $(this).removeClass('is-valid').addClass('is-invalid');
        if (errorDiv.length) { errorDiv.text('เลขบัตรประชาชนไม่ถูกต้องตามรูปแบบไทย'); }
    }
});
```

### 3. เพิ่ม CSS สำหรับแสดงสถานะการตรวจสอบ

```css
.form-control.is-valid {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}
.form-control.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}
```

## อัลกอริทึมการตรวจสอบเลขบัตรประชาชนไทย

### วิธีการคำนวณ
1. **น้ำหนัก (Weights)**: [13, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2]
2. **การคำนวณ**: 
   - คูณเลขแต่ละหลัก (12 หลักแรก) กับน้ำหนัก
   - รวมผลคูณทั้งหมด
   - หาเศษจากการหารด้วย 11
   - คำนวณเลขตรวจสอบ: (11 - เศษ) % 10
3. **การตรวจสอบ**: เปรียบเทียบเลขหลักที่ 13 กับเลขตรวจสอบ

### ตัวอย่างการคำนวณ
```
เลขบัตรประชาชน: 1234567890123
น้ำหนัก:        [13,12,11,10,9,8,7,6,5,4,3,2]
การคำนวณ:
1×13 + 2×12 + 3×11 + 4×10 + 5×9 + 6×8 + 7×7 + 8×6 + 9×5 + 0×4 + 1×3 + 2×2
= 13 + 24 + 33 + 40 + 45 + 48 + 49 + 48 + 45 + 0 + 3 + 4
= 350

เศษ = 350 % 11 = 9
เลขตรวจสอบ = (11 - 9) % 10 = 2

ตรวจสอบ: หลักที่ 13 (3) = เลขตรวจสอบ (2) ? ไม่ถูกต้อง
```

## การทำงาน

### 1. การตรวจสอบแบบ Real-time
- **ขณะกรอก**: ตรวจสอบความยาวและรูปแบบ
- **เมื่อครบ 13 หลัก**: ตรวจสอบความถูกต้องตามอัลกอริทึมไทย
- **แสดงผล**: สีเขียว (ถูกต้อง) หรือสีแดง (ไม่ถูกต้อง)

### 2. การตรวจสอบแบบ Server-side
- **ก่อนบันทึก**: ตรวจสอบความถูกต้องอีกครั้ง
- **ข้อความผิดพลาด**: แสดงข้อความที่เหมาะสม
- **การป้องกัน**: ป้องกันข้อมูลที่ไม่ถูกต้อง

### 3. ประสบการณ์ผู้ใช้
- **การตอบสนองทันที**: ตรวจสอบขณะกรอก
- **ข้อความชัดเจน**: แสดงข้อผิดพลาดที่เข้าใจง่าย
- **การแสดงผล**: สีและไอคอนที่ชัดเจน

## ข้อความผิดพลาด

### 1. ข้อความจาก JavaScript (Real-time)
- "กรุณากรอกเลขบัตรประชาชน 13 หลัก"
- "เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก"
- "เลขบัตรประชาชนไม่ถูกต้องตามรูปแบบไทย"

### 2. ข้อความจาก PHP (Server-side)
- "เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก"
- "เลขบัตรประชาชนไม่ถูกต้องตามรูปแบบไทย"

## ผลลัพธ์

### ข้อดี
1. **ความถูกต้อง**: ตรวจสอบเลขบัตรประชาชนตามมาตรฐานไทย
2. **การตอบสนองทันที**: ตรวจสอบขณะกรอกข้อมูล
3. **การป้องกัน**: ป้องกันข้อมูลที่ไม่ถูกต้อง
4. **ประสบการณ์ผู้ใช้**: ข้อความชัดเจนและเข้าใจง่าย

### การใช้งาน
1. **กรอกข้อมูล**: ผู้ใช้กรอกเลขบัตรประชาชน
2. **ตรวจสอบทันที**: ระบบตรวจสอบความถูกต้อง
3. **แสดงผล**: สีเขียว (ถูกต้อง) หรือสีแดง (ไม่ถูกต้อง)
4. **บันทึกข้อมูล**: ตรวจสอบอีกครั้งก่อนบันทึก

## สรุป

การเพิ่มการตรวจสอบเลขบัตรประชาชนไทยทำให้:
- ระบบสามารถตรวจสอบความถูกต้องของเลขบัตรประชาชน
- ผู้ใช้ได้รับข้อความชัดเจนเมื่อกรอกข้อมูลผิด
- ป้องกันข้อมูลที่ไม่ถูกต้องจากการบันทึก
- ปรับปรุงประสบการณ์ผู้ใช้ด้วยการตรวจสอบแบบ real-time
- ใช้มาตรฐานการตรวจสอบเลขบัตรประชาชนไทยที่ถูกต้อง
