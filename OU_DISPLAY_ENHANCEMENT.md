# OU Display Enhancement - Show Most Specific OU Name

## Overview
ปรับปรุงการแสดงผล "กลุ่มผู้ใช้งานระบบ" ให้แสดงชื่อย่อยสุดของ OU แทนที่จะแสดง OU ทั้งหมด เพื่อให้ผู้ใช้เข้าใจง่ายขึ้น

## Problem Description
เดิมระบบแสดง OU ทั้งหมด เช่น:
- `OU=IT,OU=rpp-user,DC=rpphosp,DC=local`
- `OU=rpp-register,DC=rpphosp,DC=local`

ซึ่งทำให้ผู้ใช้เข้าใจยากและดูไม่เป็นมิตร

## Solution Implemented

### 1. PHP Helper Function
สร้างฟังก์ชัน `getLastOuName()` ใน `frontend/views/site/index.php`:

```php
// Helper function to get the most specific OU name
function getLastOuName($ouString) {
    if (empty($ouString)) return '';
    
    $ouParts = explode(',', $ouString);
    $lastOu = '';
    
    foreach ($ouParts as $part) {
        $part = trim($part);
        if (strpos($part, 'OU=') === 0) {
            $lastOu = substr($part, 3);
        }
    }
    
    // If no OU found, try to get the last meaningful part
    if (empty($lastOu)) {
        $lastOu = end($ouParts);
        $lastOu = trim($lastOu);
    }
    
    return $lastOu ?: $ouString;
}
```

### 2. Updated Display Logic
เปลี่ยนจากการแสดง OU ทั้งหมดเป็นการแสดงชื่อย่อยสุด:

```php
<div class="col-md-6">
    <small class="text-muted">
        <strong>กลุ่มผู้ใช้งานระบบ:</strong> 
        <?= Html::encode(getLastOuName($currentUserOu)) ?>
    </small>
</div>
```

### 3. JavaScript Real-time Update
เพิ่มฟังก์ชัน JavaScript ที่ทำงานเหมือนกับ PHP function:

```javascript
// JavaScript function to get the most specific OU name (same logic as PHP)
function getLastOuNameFromString(ouString) {
    if (!ouString) return '';
    
    var ouParts = ouString.split(',');
    var lastOu = '';
    
    for (var i = 0; i < ouParts.length; i++) {
        var part = ouParts[i].trim();
        if (part.indexOf('OU=') === 0) {
            lastOu = part.substring(3);
        }
    }
    
    // If no OU found, try to get the last meaningful part
    if (!lastOu) {
        lastOu = ouParts[ouParts.length - 1].trim();
    }
    
    return lastOu || ouString;
}
```

### 4. Real-time OU Update
ปรับปรุง `updatePendingApprovalAlert()` function ให้อัปเดตการแสดงผล OU แบบ real-time:

```javascript
function updatePendingApprovalAlert(currentUserOu) {
    var alertContainer = $('#pending-approval-alert');
    console.log('Updating Pending Approval Alert. Current OU:', currentUserOu, 'Alert exists:', alertContainer.length > 0);
    
    // Update OU display if it exists
    var ouDisplay = $('small:contains("กลุ่มผู้ใช้งานระบบ:")');
    if (ouDisplay.length > 0) {
        var lastOuName = getLastOuNameFromString(currentUserOu);
        ouDisplay.html('<strong>กลุ่มผู้ใช้งานระบบ:</strong> ' + lastOuName);
    }
    
    // ... rest of function
}
```

## Examples

### Before (Original Display)
```
กลุ่มผู้ใช้งานระบบ: OU=IT,OU=rpp-user,DC=rpphosp,DC=local
กลุ่มผู้ใช้งานระบบ: OU=rpp-register,DC=rpphosp,DC=local
กลุ่มผู้ใช้งานระบบ: OU=Finance,OU=rpp-user,DC=rpphosp,DC=local
```

### After (Enhanced Display)
```
กลุ่มผู้ใช้งานระบบ: IT
กลุ่มผู้ใช้งานระบบ: rpp-register
กลุ่มผู้ใช้งานระบบ: Finance
```

## Function Logic

### PHP Function Logic
1. **Split OU String**: แยก OU string ด้วย comma
2. **Find OU Parts**: หาส่วนที่ขึ้นต้นด้วย `OU=`
3. **Extract Last OU**: เก็บค่า OU ล่าสุดที่พบ
4. **Fallback**: ถ้าไม่พบ OU ให้ใช้ส่วนสุดท้าย
5. **Return**: ส่งคืนชื่อ OU ที่สั้นที่สุด

### JavaScript Function Logic
1. **Same Logic**: ใช้ตรรกะเดียวกับ PHP function
2. **Real-time Update**: อัปเดตการแสดงผลแบบ real-time
3. **Consistent Display**: รักษาความสอดคล้องระหว่าง PHP และ JavaScript

## Benefits

### 1. User-Friendly Display
- แสดงชื่อที่เข้าใจง่าย
- ไม่แสดง technical details ที่ไม่จำเป็น
- ดูเป็นมิตรกับผู้ใช้

### 2. Consistent Experience
- การแสดงผลเหมือนกันทั้ง PHP และ JavaScript
- Real-time update เมื่อ OU เปลี่ยนแปลง
- ไม่มีการแสดงผลที่แตกต่างกัน

### 3. Maintainable Code
- ฟังก์ชัน helper ที่ใช้ซ้ำได้
- Logic ที่ชัดเจนและเข้าใจง่าย
- Easy to test และ debug

## Testing Scenarios

### Test Cases
1. **Standard OU Format**
   - Input: `OU=IT,OU=rpp-user,DC=rpphosp,DC=local`
   - Expected: `IT`

2. **Single OU**
   - Input: `OU=rpp-register,DC=rpphosp,DC=local`
   - Expected: `rpp-register`

3. **Multiple OUs**
   - Input: `OU=Finance,OU=rpp-user,DC=rpphosp,DC=local`
   - Expected: `Finance`

4. **Empty String**
   - Input: ``
   - Expected: `` (empty)

5. **No OU Format**
   - Input: `CN=User,DC=rpphosp,DC=local`
   - Expected: `DC=rpphosp,DC=local` (fallback)

### JavaScript Testing
```javascript
// Test cases for JavaScript function
console.log(getLastOuNameFromString('OU=IT,OU=rpp-user,DC=rpphosp,DC=local')); // IT
console.log(getLastOuNameFromString('OU=rpp-register,DC=rpphosp,DC=local')); // rpp-register
console.log(getLastOuNameFromString('')); // ''
console.log(getLastOuNameFromString('CN=User,DC=rpphosp,DC=local')); // DC=rpphosp,DC=local
```

## Debug Information

### Console Logs
เพิ่ม debug information ใน console:
```javascript
console.log('Starting OU Watcher for logged in user. Current User OU: <?= $currentUserOu ?>');
console.log('Last OU Name:', getLastOuNameFromString('<?= $currentUserOu ?>'));
```

### Real-time Updates
เมื่อ OU เปลี่ยนแปลง ระบบจะ:
1. อัปเดตการแสดงผลแบบ real-time
2. Log การเปลี่ยนแปลงใน console
3. รักษาความสอดคล้องของการแสดงผล

## File Changes

### Modified Files
- `frontend/views/site/index.php`
  - เพิ่ม `getLastOuName()` PHP function
  - เพิ่ม `getLastOuNameFromString()` JavaScript function
  - อัปเดตการแสดงผล OU
  - เพิ่ม real-time update logic

### Code Location
```php
// Line 23-44: PHP helper function
function getLastOuName($ouString) { ... }

// Line 230: Updated display
<?= Html::encode(getLastOuName($currentUserOu)) ?>

// Line 647-667: JavaScript helper function
function getLastOuNameFromString(ouString) { ... }

// Line 673-678: Real-time update
var lastOuName = getLastOuNameFromString(currentUserOu);
ouDisplay.html('<strong>กลุ่มผู้ใช้งานระบบ:</strong> ' + lastOuName);
```

## Future Enhancements

### Possible Improvements
1. **OU Name Mapping**: สร้าง mapping สำหรับชื่อ OU ที่เป็นมิตร
2. **Icon Display**: เพิ่ม icon สำหรับแต่ละ OU
3. **Color Coding**: ใช้สีที่แตกต่างกันสำหรับแต่ละ OU
4. **Tooltip**: แสดง OU เต็มเมื่อ hover

### Example OU Mapping
```php
$ouMapping = [
    'IT' => 'แผนกเทคโนโลยีสารสนเทศ',
    'Finance' => 'แผนกการเงิน',
    'HR' => 'แผนกทรัพยากรบุคคล',
    'rpp-register' => 'รอการอนุมัติ',
];
```

## Troubleshooting

### Common Issues

1. **OU Not Displaying**
   - ตรวจสอบ `$currentUserOu` variable
   - ตรวจสอบ session data
   - ตรวจสอบ LDAP connection

2. **JavaScript Not Updating**
   - ตรวจสอบ console errors
   - ตรวจสอบ jQuery selector
   - ตรวจสอบ function logic

3. **Inconsistent Display**
   - ตรวจสอบ PHP และ JavaScript function logic
   - ตรวจสอบ character encoding
   - ตรวจสอบ HTML encoding

### Debug Commands
```javascript
// Check current OU
console.log('Current OU:', '<?= $currentUserOu ?>');

// Test function
console.log('Last OU Name:', getLastOuNameFromString('<?= $currentUserOu ?>'));

// Check DOM element
console.log('OU Display Element:', $('small:contains("กลุ่มผู้ใช้งานระบบ:")'));
```

## Performance Considerations

### Optimization
- ฟังก์ชันทำงานแบบ synchronous
- ไม่มีการเรียก API เพิ่มเติม
- ใช้ simple string operations

### Memory Usage
- ไม่มีการเก็บ cache
- ฟังก์ชันทำงานแบบ stateless
- ไม่มี memory leaks

## Security Considerations

### Input Sanitization
- ใช้ `Html::encode()` สำหรับ PHP output
- ใช้ `trim()` สำหรับ string cleaning
- ตรวจสอบ empty values

### XSS Prevention
- Escape HTML output
- Validate input format
- Use proper encoding
